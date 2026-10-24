<?php
/**
 * Formato legado: prefira copiar .env.example para .env na raiz.
 * Para compatibilidade, este exemplo pode ser copiado para config.local.php na raiz.
 * O config.local.php NÃO vai para o git (ver .gitignore): é onde moram as senhas.
 * O Censo não lê configuração de nenhum outro sistema.
 */

// ─── Banco do Horacius (MySQL, só leitura) — andamento da revisão ───────────
// Um ou os dois ambientes. O switch do painel (administrador) escolhe qual está
// em uso; o primeiro da lista é o padrão. No servidor que só alcança produção,
// deixe só 'producao' (ou ponha produção primeiro).
const CENSO_BANCOS = [
    'producao' => [
        'rotulo' => 'Produção',
        'host'   => '10.0.0.1',
        'port'   => '3306',
        'name'   => 'horacius',
        'user'   => 'usuario_somente_leitura',
        'pass'   => 'senha',
    ],
    'homologacao' => [
        'rotulo' => 'Homologação',
        'host'   => '10.0.0.2',
        'port'   => '3306',
        'name'   => 'horacius',
        'user'   => 'usuario_somente_leitura',
        'pass'   => 'senha',
    ],
];

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
