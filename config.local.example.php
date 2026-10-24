<?php
/**
 * Configuração local do Censo — copie para config.local.php e preencha.
 * O config.local.php NÃO vai para o git (ver .gitignore): é onde moram as senhas.
 * O Censo não lê configuração de nenhum outro sistema.
 */

// ─── Banco do Horacius (MySQL, só leitura) — andamento da revisão ───────────
define('CENSO_DB_HOST',   '10.0.0.0');
define('CENSO_DB_PORT',   '3306');
define('CENSO_DB_NAME',   'horacius');
define('CENSO_DB_USER',   'usuario_somente_leitura');
define('CENSO_DB_PASS',   'senha');
define('CENSO_DB_ROTULO', 'produção');      // aparece na tela: "andamento lido no Horacius (produção)"

// ─── Microsoft Graph — envio das cobranças por e-mail ────────────────────────
// Aplicação do Entra ID com a permissão de APLICAÇÃO Mail.Send (consentida).
define('CENSO_GRAPH_TENANT_ID', '00000000-0000-0000-0000-000000000000');
define('CENSO_GRAPH_CLIENT_ID', '00000000-0000-0000-0000-000000000000');

// Remetentes disponíveis: e-mail => nome exibido + segredo do cliente.
// (Um segredo por remetente permite usar aplicações diferentes por caixa;
//  se for a mesma aplicação para todos, repita o mesmo segredo.)
const CENSO_REMETENTES = [
    'seginfo@animaeducacao.com.br' => [
        'nome'   => 'Seg. Info — Ânima Educação',
        'secret' => 'segredo-do-cliente',
    ],
    'gestao.acessos@animaeducacao.com.br' => [
        'nome'   => 'Gestão de Acessos — Ânima Educação',
        'secret' => 'segredo-do-cliente',
    ],
];

// Link que entra na variável {{link_revisao}} dos modelos de cobrança
define('CENSO_LINK_REVISAO', 'https://horacius.ecossistemaanima.com.br/portal/?menu=access-review');
