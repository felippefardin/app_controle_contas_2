<?php
ob_start(); // Limpa buffer
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

if (!isset($_SESSION['usuario_logado'])) die("Acesso negado.");
$conn = getTenantConnection();
$usuarioId = get_data_owner_id();

$formato = $_GET['formato'] ?? 'excel';
$status = $_GET['status'] ?? 'pendente';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$termo = trim($_GET['termo'] ?? '');

if (!in_array($formato, ['excel', 'pdf', 'xml'], true)) $formato = 'excel';
if (!in_array($status, ['pendente', 'baixada'], true)) $status = 'pendente';

// Query
$dateField = ($status === 'baixada') ? 'cr.data_baixa' : 'cr.data_vencimento';
$sql = "SELECT cr.*, pf.nome as nome_cliente, c.nome as nome_categoria, u.nome as nome_quem_baixou
        FROM contas_receber cr
        LEFT JOIN pessoas_fornecedores pf ON cr.id_pessoa_fornecedor = pf.id
        LEFT JOIN categorias c ON cr.id_categoria = c.id
        LEFT JOIN usuarios u ON cr.baixado_por = u.id
        WHERE cr.usuario_id = ? AND cr.deleted_at IS NULL AND cr.status = ?";

$params = [$usuarioId, $status];
$types = 'is';

if (!empty($data_inicio)) {
    $sql .= " AND $dateField >= ?";
    $params[] = $data_inicio;
    $types .= 's';
}
if (!empty($data_fim)) {
    $sql .= " AND $dateField <= ?";
    $params[] = $data_fim;
    $types .= 's';
}
if ($termo !== '') {
    $like = '%' . $termo . '%';
    $sql .= " AND (pf.nome LIKE ? OR cr.responsavel LIKE ? OR cr.numero LIKE ?
              OR cr.descricao LIKE ? OR c.nome LIKE ? OR u.nome LIKE ?
              OR CAST(cr.valor AS CHAR) LIKE ?)";
    for ($i = 0; $i < 7; $i++) $params[] = $like;
    $types .= 'sssssss';
}
$sql .= " ORDER BY $dateField ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$titulo = ($status === 'baixada') ? 'Relatório de Recebimentos' : 'Contas a Receber';
$sheet->setTitle(substr($titulo, 0, 30));

$headers = ['A'=>'Cliente', 'B'=>'Número', 'C'=>'Descrição', 'D'=>'Categoria', 'E'=>'Vencimento', 'F'=>'Valor'];
if ($status === 'baixada') {
    $headers['G'] = 'Data Receb.';
    $headers['H'] = 'Recebido Por';
}

foreach ($headers as $col => $text) $sheet->setCellValue($col . '1', $text);

$rowNum = 2;
$total = 0;
$exportRows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $exportRows[] = [
            'cliente' => $row['nome_cliente'] ?? 'N/D',
            'numero' => $row['numero'] ?? '',
            'descricao' => $row['descricao'] ?? '',
            'categoria' => $row['nome_categoria'] ?? '',
            'vencimento' => $row['data_vencimento'] ?? '',
            'valor' => number_format((float)$row['valor'], 2, '.', ''),
            'status' => $status,
            'data_baixa' => $row['data_baixa'] ?? '',
            'recebido_por' => $row['nome_quem_baixou'] ?? ''
        ];
        $sheet->setCellValue('A'.$rowNum, $row['nome_cliente'] ?? 'N/D');
        $sheet->setCellValue('B'.$rowNum, $row['numero'] ?? '');
        $sheet->setCellValue('C'.$rowNum, $row['descricao'] ?? '');
        $sheet->setCellValue('D'.$rowNum, $row['nome_categoria'] ?? '');
        $sheet->setCellValue('E'.$rowNum, date('d/m/Y', strtotime($row['data_vencimento'])));
        $sheet->setCellValue('F'.$rowNum, $row['valor']);
        $total += $row['valor'];

        if ($status === 'baixada') {
            $sheet->setCellValue('G'.$rowNum, $row['data_baixa'] ? date('d/m/Y', strtotime($row['data_baixa'])) : '-');
            $sheet->setCellValue('H'.$rowNum, $row['nome_quem_baixou'] ?? 'Sistema');
        }
        $rowNum++;
    }
}

// Totalização e Estilo
$lastCol = ($status === 'baixada') ? 'H' : 'F';
$sheet->setCellValue('E'.$rowNum, 'TOTAL:');
$sheet->setCellValue('F'.$rowNum, $total);
$sheet->getStyle('E'.$rowNum.':F'.$rowNum)->getFont()->setBold(true);

if ($formato !== 'csv') {
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '27AE60']], // Verde para Receber
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ];
    $sheet->getStyle('A1:'.$lastCol.'1')->applyFromArray($headerStyle);
    foreach(range('A',$lastCol) as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
    $sheet->getStyle('F2:F'.$rowNum)->getNumberFormat()->setFormatCode('R$ #,##0.00');
}

ob_end_clean(); // Limpa lixo
$filename = "receber_" . $status . "_" . date('Ymd_His');

if ($formato === 'xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment;filename="'.$filename.'.xml"');
    header('Cache-Control: max-age=0');
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $root = $xml->createElement('contas_receber');
    $root->setAttribute('status', $status);
    $root->setAttribute('total', number_format((float)$total, 2, '.', ''));
    $xml->appendChild($root);
    foreach ($exportRows as $dados) {
        $conta = $xml->createElement('conta');
        foreach ($dados as $campo => $valorCampo) {
            $conta->appendChild($xml->createElement($campo))
                  ->appendChild($xml->createTextNode((string)$valorCampo));
        }
        $root->appendChild($conta);
    }
    echo $xml->saveXML();
} elseif ($formato === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment;filename="'.$filename.'.pdf"');
    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setFitToWidth(1);
    if (!class_exists(\Mpdf\Mpdf::class)) IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf::class);
    $writer = IOFactory::createWriter($spreadsheet, 'Pdf');
    $writer->save('php://output');
} else {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}
exit;
?>
