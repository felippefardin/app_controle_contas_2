<?php
// Inicia o buffer para evitar que espaços em branco corrompam o arquivo
ob_start();

require_once '../includes/session_init.php';
require '../vendor/autoload.php';
require_once '../database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// 1. VERIFICA O LOGIN
if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    die("Erro: Usuário não logado.");
}

$conn = getTenantConnection();
if ($conn === null) {
    die("Erro: Falha na conexão com o banco de dados.");
}

// 2. DADOS DA SESSÃO E FILTROS
$usuarioId = $_SESSION['usuario_id'];
$formato = $_GET['formato'] ?? 'excel';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$status = $_GET['status'] ?? 'pendente';

// 3. QUERY DE DADOS
$params = [];
$types = '';

// Define qual campo de data usar
$dateField = ($status === 'baixada') ? 'cp.data_baixa' : 'cp.data_vencimento';

// Monta a consulta
$sql = "SELECT cp.*, 
               pf.nome as nome_fornecedor, 
               c.nome as nome_categoria,
               u.nome as nome_quem_baixou
        FROM contas_pagar cp
        LEFT JOIN pessoas_fornecedores pf ON cp.id_pessoa_fornecedor = pf.id
        LEFT JOIN categorias c ON cp.id_categoria = c.id
        LEFT JOIN usuarios u ON cp.baixado_por = u.id
        WHERE cp.usuario_id = ?";

$params[] = $usuarioId;
$types .= 'i';

// Filtro de Status
$sql .= " AND cp.status = ?";
$params[] = $status;
$types .= 's';

// Filtro de Data (apenas se as datas forem válidas)
if (!empty($data_inicio) && !empty($data_fim)) {
    $sql .= " AND $dateField BETWEEN ? AND ?";
    $params[] = $data_inicio;
    $params[] = $data_fim;
    $types .= 'ss';
}

$sql .= " ORDER BY $dateField ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erro na preparação da query: " . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// 4. CRIA A PLANILHA
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$tituloRelatorio = ($status === 'baixada') ? 'Relatório de Contas Pagas' : 'Relatório de Contas a Pagar';
$sheet->setTitle(substr($tituloRelatorio, 0, 30)); // Excel limita nomes de abas a 31 chars

// --- CABEÇALHOS ---
$headers = [
    'A' => 'Fornecedor',
    'B' => 'Número/Doc',
    'C' => 'Descrição',
    'D' => 'Categoria',
    'E' => 'Vencimento',
    'F' => 'Valor'
];

if ($status === 'baixada') {
    $headers['G'] = 'Data Pagto';
    $headers['H'] = 'Baixado Por';
}

foreach ($headers as $col => $text) {
    $sheet->setCellValue($col . '1', $text);
}

// --- DADOS ---
$rowNum = 2;
$total = 0.0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $fornecedor = !empty($row['nome_fornecedor']) ? $row['nome_fornecedor'] : ($row['fornecedor'] ?? 'N/D');
        $vencimento = $row['data_vencimento'] ? date('d/m/Y', strtotime($row['data_vencimento'])) : '-';
        $valor = (float)$row['valor'];
        $total += $valor;

        $sheet->setCellValue('A' . $rowNum, $fornecedor);
        $sheet->setCellValue('B' . $rowNum, $row['numero'] ?? '');
        $sheet->setCellValue('C' . $rowNum, $row['descricao'] ?? '');
        $sheet->setCellValue('D' . $rowNum, $row['nome_categoria'] ?? '');
        $sheet->setCellValue('E' . $rowNum, $vencimento);
        $sheet->setCellValue('F' . $rowNum, $valor);

        if ($status === 'baixada') {
            $pagto = $row['data_baixa'] ? date('d/m/Y', strtotime($row['data_baixa'])) : '-';
            $quem = $row['nome_quem_baixou'] ?? 'Sistema';
            $sheet->setCellValue('G' . $rowNum, $pagto);
            $sheet->setCellValue('H' . $rowNum, $quem);
        }
        $rowNum++;
    }
    
    // Linha de Total
    $colTotalLabel = ($status === 'baixada') ? 'E' : 'E';
    $colTotalValue = ($status === 'baixada') ? 'F' : 'F';
    
    $sheet->setCellValue($colTotalLabel . $rowNum, 'TOTAL:');
    $sheet->setCellValue($colTotalValue . $rowNum, $total);
    $sheet->getStyle($colTotalLabel . $rowNum . ':' . $colTotalValue . $rowNum)->getFont()->setBold(true);
}

// 5. ESTILIZAÇÃO (Apenas se não for CSV)
if ($formato !== 'csv') {
    $lastCol = ($status === 'baixada') ? 'H' : 'F';
    
    // Cabeçalho Azul
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '007ACC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
    ];
    $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);

    // Largura Automática
    foreach (range('A', $lastCol) as $colID) {
        $sheet->getColumnDimension($colID)->setAutoSize(true);
    }

    // Formato de Moeda na Coluna de Valor (F)
    $sheet->getStyle('F2:F' . $rowNum)
          ->getNumberFormat()
          ->setFormatCode('R$ #,##0.00');
}

// 6. LIMPEZA DE BUFFER E GERAÇÃO DO ARQUIVO
// Limpa qualquer HTML, espaço ou erro que tenha sido gerado antes deste ponto
ob_end_clean(); 

$filename = "relatorio_" . $status . "_" . date('d-m-Y') . "_" . uniqid();

if ($formato === 'pdf') {
    // Configura PDF usando Dompdf
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="' . $filename . '.pdf"');
    header('Cache-Control: max-age=0');

    // Configurações de Página para PDF
    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
    $sheet->setShowGridlines(true);
    
    // Centralizar
    $sheet->getPageSetup()->setHorizontalCentered(true);

    // Registra o Dompdf explicitamente
    $className = \PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf::class;
    IOFactory::registerWriter('Pdf', $className);
    
    $writer = IOFactory::createWriter($spreadsheet, 'Pdf');
    $writer->save('php://output');

} elseif ($formato === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');

    $writer = new Csv($spreadsheet);
    $writer->setDelimiter(';');
    $writer->setEnclosure('"');
    $writer->setLineEnding("\r\n");
    $writer->setSheetIndex(0);
    $writer->save('php://output');

} else {
    // Excel (XLSX)
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

$stmt->close();
exit;
?>