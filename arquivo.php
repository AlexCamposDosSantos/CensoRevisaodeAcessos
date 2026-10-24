<?php
/**
 * Download do arquivo ORIGINAL enviado numa revisão (?id=). Servido pelo PHP,
 * nunca por URL direta: a pasta dados/ é fechada pelo .htaccess.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/revisao.php';
require_login();

$a = revisao_arquivo((int) ($_GET['id'] ?? 0));
$caminho = $a ? pasta_revisao((int) $a['revisao_id']) . '/' . basename($a['arquivo']) : null;
if (!$a || !is_file($caminho)) { http_response_code(404); exit('Arquivo não encontrado.'); }

$ext = strtolower(pathinfo($a['nome_original'], PATHINFO_EXTENSION));
header('Content-Type: ' . ($ext === 'csv' ? 'text/csv; charset=utf-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $a['nome_original']) . '"');
header('Content-Length: ' . filesize($caminho));
header('X-Content-Type-Options: nosniff');
readfile($caminho);
