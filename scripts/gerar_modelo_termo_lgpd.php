<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Somente CLI.\n"); }

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/lgpd_registration_terms.php';

use Dompdf\Dompdf;

$sections = '';
foreach (lgpd_registration_sections() as $section) {
    $sections .= '<section><h2>' . htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') . '</h2><p>'
        . htmlspecialchars($section['body'], ENT_QUOTES, 'UTF-8') . '</p></section>';
}
$version = lgpd_registration_version();
$acceptanceHash = hash('sha256', 'MODELO|' . $version . '|' . lgpd_registration_manifestation());
$dataAceiteBrasil = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i:s') . ' (horário de Brasília)';
$html = '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><style>'
    . '@page{margin:32px 42px 42px}body{font-family:DejaVu Sans,Arial,sans-serif;color:#243244;font-size:10.5px;line-height:1.5}'
    . 'h1{color:#087ea4;font-size:21px;border-bottom:2px solid #087ea4;padding-bottom:9px;margin-bottom:5px}h2{font-size:12.5px;color:#0b6685;margin:12px 0 3px}'
    . '.version{color:#66758a;font-size:9px;margin-bottom:13px}.intro{background:#eaf7fc;border-left:4px solid #087ea4;padding:11px 13px;margin-bottom:13px}'
    . '.dados{background:#f4f7f8;border:1px solid #d8e1e5;padding:12px;margin:12px 0}.confirmacoes{background:#f7f9fc;border:1px solid #cfd9e4;padding:12px;margin-top:14px}'
    . '.check{margin:5px 0}.check strong{color:#087ea4}.rodape{margin-top:18px;font-size:8px;color:#66758a;word-break:break-all;border-top:1px solid #d8e1e5;padding-top:8px}'
    . '.page-footer{position:fixed;bottom:-25px;left:0;right:0;text-align:center;color:#8591a1;font-size:8px}section p{margin:0;text-align:left}'
    . '</style></head><body><div class="page-footer">App Controle de Contas - Termos de Uso e Aviso de Privacidade - versão ' . $version . '</div>'
    . '<h1>Termos de Uso e Aviso de Privacidade - LGPD</h1><div class="version">Documento versão ' . $version . ' - Lei nº 13.709/2018</div>'
    . '<div class="intro"><strong>Resumo:</strong> este comprovante registra, de forma clara e verificável, as condições do serviço, o tratamento dos dados pessoais e as escolhas feitas pelo titular no momento da criação da conta.</div>'
    . '<div class="dados"><strong>Titular:</strong> Nome apresentado no cadastro<br><strong>E-mail:</strong> titular@exemplo.com.br<br><strong>Documento:</strong> ***.***.***-**<br><strong>Data e hora do aceite:</strong> ' . htmlspecialchars($dataAceiteBrasil, ENT_QUOTES, 'UTF-8') . '<br><strong>IP de origem:</strong> endereço registrado no momento do aceite</div>'
    . $sections
    . '<div class="confirmacoes"><strong>Manifestação registrada:</strong><p>' . htmlspecialchars(lgpd_registration_manifestation(), ENT_QUOTES, 'UTF-8') . '</p>'
    . '<div class="check"><strong>[CONFIRMADO]</strong> Termos de Uso.</div><div class="check"><strong>[CONFIRMADO]</strong> Aviso de Privacidade e direitos LGPD.</div><div class="check"><strong>[CONFIRMADO]</strong> Veracidade e autorização para cadastro.</div><div class="check"><strong>[NÃO ACEITO]</strong> Comunicações promocionais opcionais.</div></div>'
    . '<div class="rodape"><strong>Identificador do aceite:</strong> ' . $acceptanceHash . '<br><strong>Observação:</strong> este arquivo é um modelo visual; os comprovantes reais recebem dados e identificadores próprios.</div></body></html>';

$outputDir = __DIR__ . '/../output/pdf';
if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) throw new RuntimeException('Nao foi possivel criar output/pdf.');
$output = $outputDir . '/modelo_termos_lgpd_v2.pdf';
$pdf = new Dompdf(); $pdf->loadHtml($html, 'UTF-8'); $pdf->setPaper('A4', 'portrait'); $pdf->render();
file_put_contents($output, $pdf->output());
echo $output . PHP_EOL;
