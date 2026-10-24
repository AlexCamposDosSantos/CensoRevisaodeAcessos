<?php
/** Download do modelo .xlsx de uma planilha do catálogo (?tipo=base). */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/revisao.php';
require_login();

$tipo = (string) ($_GET['tipo'] ?? '');
if (!isset(PLANILHAS[$tipo])) { http_response_code(404); exit('Modelo não encontrado.'); }

$bin = modelo_xlsx($tipo);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="modelo_' . $tipo . '_censo.xlsx"');
header('Content-Length: ' . strlen($bin));
header('X-Content-Type-Options: nosniff');
echo $bin;
