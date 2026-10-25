<?php
/**
 * Delegação de gestão do Horacius: um gestor passa a gestão para outra pessoa
 * (o delegado) até uma data, ou sem prazo. Na cobrança, quem recebe como
 * gestor/líder e tem delegação vigente faz o delegado receber JUNTO (os dois no
 * "Para") — é ele quem está revisando no lugar do gestor.
 *
 * No Horacius: pes_delegacao_gestao (codpessoa = gestor, datafim; vencida some
 * da tabela) e pes_delegacao_gestao_gestoresdelegados (codpessoa →
 * codgestordelegado). O Censo lê a cada atualização do andamento e guarda uma
 * cópia (tabela delegacoes); a cobrança usa a cópia.
 */
require_once __DIR__ . '/fontes.php';
require_once __DIR__ . '/funcionarios.php';

function delegacoes_estrutura(): void
{
    static $ok = false;
    if ($ok) return;
    $ok = true;
    db()->exec("
        CREATE TABLE IF NOT EXISTS delegacoes (
            gestor_cod     INTEGER NOT NULL,
            gestor_cpf     TEXT NOT NULL DEFAULT '',
            gestor_email   TEXT NOT NULL DEFAULT '',
            gestor_nome    TEXT NOT NULL DEFAULT '',
            delegado_cod   INTEGER NOT NULL,
            delegado_cpf   TEXT NOT NULL DEFAULT '',
            delegado_email TEXT NOT NULL DEFAULT '',
            delegado_nome  TEXT NOT NULL DEFAULT '',
            datafim        TEXT
        );
        CREATE INDEX IF NOT EXISTS idx_deleg_cpf ON delegacoes(gestor_cpf);
        CREATE INDEX IF NOT EXISTS idx_deleg_email ON delegacoes(gestor_email);
        CREATE TABLE IF NOT EXISTS ajustes (chave TEXT PRIMARY KEY, valor TEXT);
    ");
}

/** Situação da última leitura: ['em','banco','vigentes','sem_email','erro'] ou null. */
function delegacoes_leitura(): ?array
{
    delegacoes_estrutura();
    $v = db()->query("SELECT valor FROM ajustes WHERE chave = 'delegacoes'")->fetchColumn();
    return $v !== false ? json_decode((string) $v, true) : null;
}

function delegacoes_marcar(array $info): void
{
    db()->prepare("INSERT INTO ajustes (chave, valor) VALUES ('delegacoes', ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor")
        ->execute([json_encode($info + ['em' => date('Y-m-d H:i:s'), 'banco' => horacius_rotulo()], JSON_UNESCAPED_UNICODE)]);
}

/**
 * Relê as delegações vigentes no Horacius. Só entram delegados ATIVOS
 * (pessoa.estado = 1) — delegação para desligado não deve receber e-mail.
 * O e-mail do delegado é o do Horacius; se faltar (ou for pessoal), o da
 * BaseFull pelo CPF. Nunca lança: devolve ['status','texto'].
 */
function delegacoes_atualizar(): array
{
    delegacoes_estrutura();
    try {
        $linhas = horacius_db()->query("
            SELECT d.codpessoa AS gestor_cod, g.cpf AS gestor_cpf, g.email AS gestor_email, g.nome AS gestor_nome,
                   dd.codgestordelegado AS delegado_cod, x.cpf AS delegado_cpf, x.email AS delegado_email, x.nome AS delegado_nome,
                   d.datafim
              FROM pes_delegacao_gestao d
              JOIN pes_delegacao_gestao_gestoresdelegados dd ON dd.codpessoa = d.codpessoa
              JOIN pessoa g ON g.codpessoa = d.codpessoa
              JOIN pessoa x ON x.codpessoa = dd.codgestordelegado
             WHERE (d.datafim IS NULL OR d.datafim >= CURDATE())
               AND x.estado = 1")->fetchAll();
    } catch (Throwable $e) {
        delegacoes_marcar(['erro' => $e->getMessage()] + (delegacoes_leitura() ?? []));
        return ['status' => 'erro', 'texto' => 'Não foi possível ler as delegações no Horacius: ' . $e->getMessage() . '. Seguiu com a última leitura.'];
    }

    $semEmail = 0;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM delegacoes');
        $ins = $pdo->prepare('INSERT INTO delegacoes (gestor_cod, gestor_cpf, gestor_email, gestor_nome, delegado_cod, delegado_cpf, delegado_email, delegado_nome, datafim)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($linhas as $l) {
            $dCpf = so_digitos(utf8((string) $l['delegado_cpf']), 11);
            $email = strtolower(trim((string) $l['delegado_email']));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || email_pessoal($email)) {
                $f = $dCpf !== '' ? funcionario_por('cpf', $dCpf) : null;
                $email = $f ? email_institucional($f) : '';
            }
            if ($email === '') $semEmail++;
            $ins->execute([(int) $l['gestor_cod'], so_digitos((string) $l['gestor_cpf'], 11), strtolower(trim((string) $l['gestor_email'])),
                           utf8((string) $l['gestor_nome']), (int) $l['delegado_cod'], $dCpf, $email, utf8((string) $l['delegado_nome']),
                           $l['datafim'] ?: null]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['status' => 'erro', 'texto' => 'Não foi possível guardar as delegações: ' . $e->getMessage() . '.'];
    }
    delegacoes_marcar(['vigentes' => count($linhas), 'sem_email' => $semEmail, 'erro' => null]);
    return ['status' => 'ok', 'texto' => count($linhas) . ' delegação(ões) de gestão vigente(s) no Horacius'
        . ($semEmail ? " ({$semEmail} sem e-mail do delegado — não recebem)" : '') . '.'];
}

/**
 * Delegados vigentes de um gestor/líder (pelo CPF; sem CPF, pelo e-mail).
 * @return array<int, array{email:string, nome:string, datafim:?string}>
 */
function delegados_de(array $lider): array
{
    static $cache = [];
    delegacoes_estrutura();
    $cpf = so_digitos((string) ($lider['cpf'] ?? ''), 11);
    $email = strtolower(trim((string) ($lider['email'] ?? '')));
    $k = $cpf . '|' . $email;
    if (isset($cache[$k])) return $cache[$k];
    if ($cpf === '' && $email === '') return $cache[$k] = [];
    $st = db()->prepare("SELECT delegado_email AS email, delegado_nome AS nome, datafim FROM delegacoes
                          WHERE delegado_email <> '' AND ((? <> '' AND gestor_cpf = ?) OR (? <> '' AND gestor_email = ?))
                          GROUP BY delegado_email ORDER BY delegado_nome");
    $st->execute([$cpf, $cpf, $email, $email]);
    return $cache[$k] = array_values(array_filter($st->fetchAll(), fn($d) => $d['email'] !== $email));
}
