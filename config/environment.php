<?php
/** Leitura local de .env; sem interpolação de variáveis nem exposição em $_SERVER. */
function censo_env_ler(string $arquivo): array
{
    if (!is_file($arquivo)) return [];
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
    if ($linhas === false) throw new RuntimeException('Não foi possível ler o arquivo de ambiente do Censo.');
    $out = [];
    foreach ($linhas as $numero => $linha) {
        if ($numero === 0) $linha = preg_replace('/^\xEF\xBB\xBF/', '', $linha);
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#')) continue;
        if (!preg_match('/^(?:export\s+)?([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $linha, $m)) {
            throw new RuntimeException('Formato inválido no .env, linha ' . ($numero + 1) . '.');
        }
        $valor = trim($m[2]);
        if (str_starts_with($valor, "'")) {
            // Aspas simples preservam literalmente $, #, barras e espaços.
            if (!preg_match("/^'([^']*)'\\s*(?:#.*)?$/", $valor, $c)) throw new RuntimeException('Aspas inválidas no .env, linha ' . ($numero + 1) . '.');
            $valor = $c[1];
        } elseif (str_starts_with($valor, '"')) {
            if (!preg_match('/^("(?:[^"\\\\]|\\\\.)*")\s*(?:#.*)?$/', $valor, $c)) throw new RuntimeException('Aspas inválidas no .env, linha ' . ($numero + 1) . '.');
            $valor = json_decode($c[1], true);
            if (!is_string($valor)) throw new RuntimeException('Escape inválido no .env, linha ' . ($numero + 1) . '.');
        } else {
            $valor = rtrim(preg_replace('/\s+#.*$/', '', $valor));
        }
        $out[$m[1]] = $valor;
    }
    return $out;
}

/** Processo/servidor > .env do Censo > padrão. String vazia é um valor explícito. */
function censo_env(string $chave, ?string $padrao = null): ?string
{
    $valor = getenv($chave);
    if ($valor !== false) return $valor;
    static $local = null;
    $local ??= censo_env_ler(dirname(__DIR__) . '/.env');
    return $local[$chave] ?? $padrao;
}

/** Identificadores separados por vírgulas; a ordem define o ambiente inicial. */
function censo_env_lista(string $chave): array
{
    $lista = array_values(array_unique(array_filter(array_map('trim', explode(',', censo_env($chave, ''))), fn($v) => $v !== '')));
    foreach ($lista as $id) {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $id)) throw new RuntimeException('Identificador inválido na variável ' . $chave . ' do ambiente.');
    }
    return $lista;
}

function censo_env_bancos(): ?array
{
    if (censo_env('CENSO_DB_AMBIENTES') === null) return null;
    $out = [];
    foreach (censo_env_lista('CENSO_DB_AMBIENTES') as $id) {
        $prefixo = 'CENSO_DB_' . strtoupper($id) . '_';
        $out[$id] = [
            'rotulo' => censo_env($prefixo . 'ROTULO', $id === 'producao' ? 'Produção' : ($id === 'homologacao' ? 'Homologação' : $id)),
            'host' => censo_env($prefixo . 'HOST', ''), 'port' => censo_env($prefixo . 'PORT', '3306'),
            'name' => censo_env($prefixo . 'NAME', 'horacius'), 'user' => censo_env($prefixo . 'USER', ''),
            'pass' => censo_env($prefixo . 'PASS', ''),
        ];
    }
    return $out;
}

function censo_env_remetentes(): ?array
{
    if (censo_env('CENSO_REMETENTES') === null) return null;
    $out = [];
    foreach (censo_env_lista('CENSO_REMETENTES') as $id) {
        $prefixo = 'CENSO_REMETENTE_' . strtoupper($id) . '_';
        $email = strtolower(trim(censo_env($prefixo . 'EMAIL', '')));
        if ($email === '') continue;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('E-mail inválido na variável ' . $prefixo . 'EMAIL do ambiente.');
        $out[$email] = ['nome' => censo_env($prefixo . 'NOME', $email), 'secret' => censo_env($prefixo . 'SECRET', '')];
    }
    return $out;
}
