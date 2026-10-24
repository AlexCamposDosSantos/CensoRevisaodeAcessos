<?php
/**
 * Envia o próximo lote de e-mails de uma cobrança (POST, JSON). A tela da
 * cobrança chama em sequência até não sobrar pendente — cada chamada é curta,
 * então não esbarra no tempo máximo de execução do PHP.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/services/cobranca.php';

header('Content-Type: application/json; charset=utf-8');
if (!is_logged_in()) { http_response_code(401); exit(json_encode(['ok' => false, 'erro' => 'Sessão encerrada. Entre de novo.'])); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) { http_response_code(403); exit(json_encode(['ok' => false, 'erro' => 'Requisição inválida.'])); }

try {
    set_time_limit(120);
    echo json_encode(['ok' => true] + cobranca_enviar_lote((int) ($_GET['id'] ?? 0), 5));
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
