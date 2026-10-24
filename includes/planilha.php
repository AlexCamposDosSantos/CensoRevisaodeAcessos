<?php
/**
 * Planilhas da revisão: o catálogo de arquivos que uma revisão recebe, a
 * leitura do que o usuário envia (.xlsx ou .csv) e o MODELO para download.
 *
 * O modelo é gerado aqui (xlsx mínimo, sem biblioteca): cabeçalho na 1ª linha,
 * e cada coluna com uma "mensagem de entrada" — o Excel mostra a descrição da
 * coluna assim que se clica numa célula dela. Colunas de valor fechado vêm com
 * lista suspensa. Uma 2ª aba, "Instruções", repete tudo com exemplo.
 */

/**
 * Arquivos que uma revisão recebe. Cada coluna: cabeçalho do modelo, apelidos
 * aceitos na leitura (cabeçalhos normalizados), obrigatoriedade, descrição,
 * exemplo e, quando há, a lista de valores aceitos.
 */
const PLANILHAS = [
    'base' => [
        'titulo'      => 'Base de pessoas da revisão',
        'descricao'   => 'Quem entra na revisão e com qual vínculo. É o que define o universo do painel.',
        'obrigatoria' => true,
        'colunas'     => [
            'codigo' => [
                'cabecalho' => 'codigo', 'apelidos' => ['codigo', 'cod pessoa', 'codpessoa', 'codigo pessoa'],
                'obrigatoria' => true, 'exemplo' => '12345',
                'descricao' => 'Código da pessoa no Horacius (pessoa.codpessoa). Só números. É por ele que o Censo sabe se a pessoa já revisou.',
            ],
            'nome' => [
                'cabecalho' => 'nome-pessoa', 'apelidos' => ['nome pessoa', 'nome', 'nome da pessoa'],
                'obrigatoria' => true, 'exemplo' => 'MARIA DA SILVA',
                'descricao' => 'Nome completo da pessoa, como no Horacius.',
            ],
            'id_unico' => [
                'cabecalho' => 'id-unico', 'apelidos' => ['id unico', 'cpf', 'identificador', 'identificador unico'],
                'obrigatoria' => false, 'exemplo' => '12345678901',
                'descricao' => 'CPF da pessoa. É por ele que o Censo acha o LÍDER na base de funcionários — sem CPF, a pessoa não entra nas cobranças.',
            ],
            'enviado' => [
                'cabecalho' => 'Foi enviado para revisao?', 'apelidos' => ['foi enviado para revisao', 'enviado para revisao', 'enviado'],
                'obrigatoria' => false, 'exemplo' => 'SIM', 'valores' => ['SIM', 'NÃO'],
                'descricao' => 'SIM se a pessoa entra na revisão; NÃO se fica de fora (explique na coluna seguinte). Em branco conta como SIM.',
            ],
            'justificativa' => [
                'cabecalho' => 'Justificativa para não ir para revisao', 'apelidos' => ['justificativa para nao ir para revisao', 'justificativa'],
                'obrigatoria' => false, 'exemplo' => 'OK - Pessoa enviada para revisão',
                'descricao' => 'Por que a pessoa NÃO foi enviada para revisão (obrigatória quando a coluna anterior é NÃO).',
            ],
            'vinculo' => [
                'cabecalho' => 'Vinculo', 'apelidos' => ['vinculo', 'tipo de vinculo'],
                'obrigatoria' => true, 'exemplo' => 'Empregado', 'valores' => ['Empregado', 'Terceirizado', 'Polo', 'Professor'],
                'descricao' => 'Vínculo da pessoa: Empregado, Terceirizado, Polo ou Professor. Define em que grupo do painel ela aparece ("0" ou vazio conta como Polo).',
            ],
            'lider_nome' => [
                'cabecalho' => 'Nome do lider', 'apelidos' => ['nome do lider', 'nome lider', 'lider'],
                'obrigatoria' => false, 'exemplo' => 'JOÃO PEREIRA',
                'descricao' => 'Opcional. Quem recebe a cobrança desta pessoa. Preenchido, vale ANTES da base de funcionários (útil quando o líder não está lá, como costuma acontecer com professores).',
            ],
            'lider_email' => [
                'cabecalho' => 'E-mail do lider', 'apelidos' => ['e mail do lider', 'email do lider', 'email lider', 'e mail lider'],
                'obrigatoria' => false, 'exemplo' => 'joao.pereira@animaeducacao.com.br',
                'descricao' => 'Opcional. E-mail de quem recebe a cobrança desta pessoa. Preenchido, vale antes da base de funcionários.',
            ],
        ],
    ],

    // Não pertence a uma revisão: é a hierarquia (quem é líder de quem), usada
    // nas cobranças. Os cabeçalhos do modelo são os da BaseFull do RH — o
    // arquivo diário serve direto, sem ajuste.
    'funcionarios' => [
        'titulo'      => 'Base de funcionários (hierarquia)',
        'descricao'   => 'Quem é o líder de cada pessoa e o e-mail dele. É de onde saem os destinatários das cobranças. A BaseFull do RH serve direto.',
        'obrigatoria' => false,
        'escopo'      => 'global',
        'colunas'     => [
            'cpf' => [
                'cabecalho' => 'CPF_FUNCIONARIO', 'apelidos' => ['cpf funcionario', 'cpf'],
                'obrigatoria' => true, 'exemplo' => '12345678901',
                'descricao' => 'CPF do funcionário, só números. Liga o funcionário à pessoa da revisão (coluna id-unico da base da revisão).',
            ],
            'matricula' => [
                'cabecalho' => 'NUM_MATRICULA', 'apelidos' => ['num matricula', 'matricula'],
                'obrigatoria' => true, 'exemplo' => '100708344',
                'descricao' => 'Matrícula do funcionário. É por ela que um funcionário é achado como líder de outro.',
            ],
            'nome' => [
                'cabecalho' => 'NOM_FUNCIONARIO', 'apelidos' => ['nom funcionario', 'nome funcionario', 'nome'],
                'obrigatoria' => true, 'exemplo' => 'MARIA DA SILVA',
                'descricao' => 'Nome completo do funcionário.',
            ],
            'email' => [
                'cabecalho' => 'EML_FUNCIONARIO_EMPRESA', 'apelidos' => ['eml funcionario empresa', 'email empresa', 'email corporativo'],
                'obrigatoria' => false, 'exemplo' => 'maria.silva@animaeducacao.com.br',
                'descricao' => 'E-mail corporativo. É o usado quando a pessoa é líder e recebe a cobrança.',
            ],
            'email_alt' => [
                'cabecalho' => 'EML_FUNCIONARIO', 'apelidos' => ['eml funcionario', 'email'],
                'obrigatoria' => false, 'exemplo' => 'maria.silva@animaeducacao.com.br',
                'descricao' => 'Outro e-mail. Só é usado se o corporativo estiver vazio e este NÃO for de provedor pessoal (gmail, hotmail…).',
            ],
            'login_ad' => [
                'cabecalho' => 'SGL_AD_FUNCIONARIO', 'apelidos' => ['sgl ad funcionario', 'login', 'login ad'],
                'obrigatoria' => false, 'exemplo' => 'maria.silva',
                'descricao' => 'Login de rede (opcional).',
            ],
            'matricula_lider' => [
                'cabecalho' => 'NUM_MATRICULA_LIDER', 'apelidos' => ['num matricula lider', 'matricula lider', 'matricula do lider'],
                'obrigatoria' => true, 'exemplo' => '001000875',
                'descricao' => 'Matrícula do líder imediato. O e-mail do líder vem da linha em que ele aparece como funcionário.',
            ],
            'nome_lider' => [
                'cabecalho' => 'NOM_LIDER', 'apelidos' => ['nom lider', 'nome lider', 'nome do lider'],
                'obrigatoria' => false, 'exemplo' => 'JOÃO PEREIRA',
                'descricao' => 'Nome do líder imediato.',
            ],
            'ativo' => [
                'cabecalho' => 'IND_ATIVO', 'apelidos' => ['ind ativo', 'ativo'],
                'obrigatoria' => false, 'exemplo' => 'S', 'valores' => ['S', 'N'],
                'descricao' => 'S se o vínculo está ativo. Quando o mesmo CPF tem várias linhas, vale a ativa. Em branco conta como ativo.',
            ],
            'admissao' => [
                'cabecalho' => 'DAT_ADMISSAO', 'apelidos' => ['dat admissao', 'admissao', 'data de admissao'],
                'obrigatoria' => false, 'exemplo' => '05/10/2026',
                'descricao' => 'Data de admissão (desempate entre linhas ativas do mesmo CPF: vale a mais recente).',
            ],
        ],
    ],
];

const UPLOAD_MAX_MB = 50;

/** Planilhas que uma revisão recebe (as de escopo "global" vivem fora dela). */
function planilhas_da_revisao(): array
{
    return array_filter(PLANILHAS, fn($p) => ($p['escopo'] ?? 'revisao') === 'revisao');
}

function normalizar_cabecalho(string $s): string
{
    $s = strtr(mb_strtolower(trim($s)), ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ç'=>'c']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}

// ─── Leitura ─────────────────────────────────────────────────────────────────

/** Linhas cruas (cabeçalho normalizado => valor) de um .xlsx (1ª aba) ou .csv. */
function planilha_ler(string $caminho, string $nomeOriginal): array
{
    $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    if ($ext === 'csv') return csv_linhas($caminho);
    if ($ext === 'xlsx') return xlsx_linhas($caminho);
    throw new RuntimeException('Formato não aceito: envie .xlsx ou .csv.');
}

function xlsx_linhas(string $caminho): array
{
    $z = new ZipArchive();
    if ($z->open($caminho) !== true) throw new RuntimeException('Arquivo .xlsx ilegível.');

    $ss = [];
    if (($x = $z->getFromName('xl/sharedStrings.xml')) !== false) {
        foreach (simplexml_load_string($x)->si as $si) {
            $t = isset($si->t) ? (string) $si->t : '';
            if (!isset($si->t)) foreach ($si->r as $r) $t .= (string) $r->t;
            $ss[] = $t;
        }
    }
    // Primeira aba pela ordem do workbook (não pelo nome do arquivo interno)
    $alvo = 'xl/worksheets/sheet1.xml';
    $wb = $z->getFromName('xl/workbook.xml');
    $rels = $z->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb !== false && $rels !== false) {
        $primeira = simplexml_load_string($wb)->sheets->sheet[0] ?? null;
        $rid = $primeira ? (string) $primeira->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : '';
        foreach (simplexml_load_string($rels)->Relationship as $r) {
            if ((string) $r['Id'] === $rid) $alvo = 'xl/' . ltrim(preg_replace('#^/?xl/#', '', (string) $r['Target']), '/');
        }
    }
    $aba = $z->getFromName($alvo);
    $z->close();
    if ($aba === false) throw new RuntimeException('Não achei a primeira aba do .xlsx.');

    $linhas = []; $cab = null;
    foreach (simplexml_load_string($aba)->sheetData->row as $row) {
        $v = [];
        foreach ($row->c as $c) {
            $col = preg_replace('/\d+/', '', (string) $c['r']);
            $val = (string) $c->v;
            if ((string) $c['t'] === 's') $val = $ss[(int) $val] ?? '';
            elseif ((string) $c['t'] === 'inlineStr') $val = (string) $c->is->t;
            $v[$col] = trim($val);
        }
        if ($cab === null) { $cab = array_map('normalizar_cabecalho', $v); continue; }
        if (!array_filter($v, fn($x) => $x !== '')) continue;   // linha em branco
        $l = ['_linha' => (int) $row['r']];
        foreach ($cab as $col => $nome) $l[$nome] = $v[$col] ?? '';
        $linhas[] = $l;
    }
    return $linhas;
}

/**
 * Percorre um CSV grande linha a linha (sem carregar o arquivo na memória),
 * chamando $fn com cada linha (cabeçalho normalizado => valor).
 */
function csv_percorrer(string $caminho, callable $fn): void
{
    $h = fopen($caminho, 'r');
    if (!$h) throw new RuntimeException('Não consegui ler o CSV.');
    $primeira = (string) fgets($h);
    $primeira = preg_replace('/^\xEF\xBB\xBF/', '', $primeira);
    $latin = !mb_check_encoding($primeira, 'UTF-8');
    $sep = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';
    $conv = fn($s) => $latin ? mb_convert_encoding($s, 'UTF-8', 'Windows-1252') : $s;
    $cab = array_map(fn($c) => normalizar_cabecalho($conv($c)), str_getcsv(trim($primeira), $sep, '"', ''));
    $n = 1;
    while (($v = fgetcsv($h, 0, $sep, '"', '')) !== false) {
        $n++;
        if ($v === [null]) continue;
        $l = ['_linha' => $n];
        foreach ($cab as $i => $nome) $l[$nome] = $conv(trim((string) ($v[$i] ?? '')));
        $fn($l);
    }
    fclose($h);
}

function csv_linhas(string $caminho): array
{
    $txt = (string) file_get_contents($caminho);
    $txt = preg_replace('/^\xEF\xBB\xBF/', '', $txt);
    if (!mb_check_encoding($txt, 'UTF-8')) $txt = mb_convert_encoding($txt, 'UTF-8', 'Windows-1252');
    $primeira = strtok($txt, "\n");
    $sep = substr_count($primeira, ';') >= substr_count($primeira, ',') ? ';' : ',';

    $h = fopen('php://temp', 'r+'); fwrite($h, $txt); rewind($h);
    $cab = null; $linhas = []; $n = 0;
    while (($v = fgetcsv($h, 0, $sep, '"', '')) !== false) {
        $n++;
        if ($cab === null) { $cab = array_map('normalizar_cabecalho', $v); continue; }
        if (!array_filter($v, fn($x) => trim((string) $x) !== '')) continue;
        $l = ['_linha' => $n];
        foreach ($cab as $i => $nome) $l[$nome] = trim((string) ($v[$i] ?? ''));
        $linhas[] = $l;
    }
    fclose($h);
    return $linhas;
}

/**
 * Lê e VALIDA uma planilha do catálogo. Devolve [linhas mapeadas, erros, avisos].
 * Linhas mapeadas usam as chaves do catálogo (codigo, nome, vinculo…).
 */
function planilha_validar(string $tipo, string $caminho, string $nomeOriginal): array
{
    $def = PLANILHAS[$tipo];
    $cru = planilha_ler($caminho, $nomeOriginal);
    if (!$cru) return [[], ['A planilha não tem nenhuma linha de dados abaixo do cabeçalho.'], []];

    // Qual cabeçalho da planilha corresponde a cada coluna do catálogo
    $presentes = array_keys($cru[0]);
    $mapa = []; $erros = []; $avisos = [];
    foreach ($def['colunas'] as $chave => $col) {
        foreach ($col['apelidos'] as $ap) if (in_array($ap, $presentes, true)) { $mapa[$chave] = $ap; break; }
        if (!isset($mapa[$chave]) && $col['obrigatoria']) $erros[] = 'Falta a coluna obrigatória "' . $col['cabecalho'] . '".';
    }
    if ($erros) return [[], $erros, []];

    $linhas = []; $vistos = [];
    foreach ($cru as $l) {
        $m = ['_linha' => $l['_linha']];
        foreach ($mapa as $chave => $cabec) $m[$chave] = $l[$cabec] ?? '';
        $linhas[] = $m;
    }

    if ($tipo === 'base') {
        foreach ($linhas as &$m) {
            $ref = 'Linha ' . $m['_linha'];
            $cod = preg_replace('/\.0+$/', '', trim($m['codigo']));
            if (!ctype_digit($cod) || (int) $cod <= 0) { $erros[] = "{$ref}: código \"{$m['codigo']}\" não é um código de pessoa."; continue; }
            $m['codigo'] = (int) $cod;
            if (isset($vistos[$m['codigo']])) $avisos[] = "{$ref}: código {$m['codigo']} repetido (linha {$vistos[$m['codigo']]}); vale a última.";
            $vistos[$m['codigo']] = $m['_linha'];
            if (trim($m['nome']) === '') $erros[] = "{$ref}: nome em branco.";
            $env = mb_strtoupper(trim($m['enviado'] ?? ''));
            $m['enviado'] = in_array($env, ['NÃO', 'NAO', 'N'], true) ? 0 : 1;
            if (!$m['enviado'] && trim($m['justificativa'] ?? '') === '') $avisos[] = "{$ref}: marcada como NÃO enviada, sem justificativa.";
            $m['vinculo'] = normalizar_vinculo($m['vinculo']);
            if (!array_key_exists($m['vinculo'], VINCULOS)) $avisos[] = "{$ref}: vínculo \"{$m['vinculo']}\" fora da lista — vai aparecer à parte no painel.";
            $le = trim($m['lider_email'] ?? '');
            if ($le !== '' && !filter_var($le, FILTER_VALIDATE_EMAIL)) { $avisos[] = "{$ref}: e-mail do líder \"{$le}\" inválido — vai valer a base de funcionários."; $m['lider_email'] = ''; }
            if (count($erros) >= 30) { $erros[] = '…e há mais erros; corrija estes e envie de novo.'; break; }
        }
        unset($m);
    }
    // Avisos repetidos demais viram um resumo
    if (count($avisos) > 15) $avisos = array_merge(array_slice($avisos, 0, 15), ['…e mais ' . (count($avisos) - 15) . ' aviso(s) do mesmo tipo.']);
    return [$linhas, $erros, $avisos];
}

// ─── Modelo (.xlsx) para download ────────────────────────────────────────────

function modelo_xlsx(string $tipo): string
{
    $def  = PLANILHAS[$tipo];
    $cols = array_values($def['colunas']);
    $letra = fn(int $i) => chr(65 + $i);
    $x = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $cel = fn(string $ref, string $v, int $estilo = 0) =>
        '<c r="' . $ref . '" t="inlineStr"' . ($estilo ? ' s="' . $estilo . '"' : '') . '><is><t xml:space="preserve">' . $x($v) . '</t></is></c>';

    // Aba 1: só o cabeçalho, com descrição (mensagem de entrada) e lista onde houver
    $cab = ''; $larg = ''; $valid = '';
    foreach ($cols as $i => $c) {
        $L = $letra($i);
        $cab  .= $cel($L . '1', $c['cabecalho'], $c['obrigatoria'] ? 1 : 2);
        $larg .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . max(16, min(46, mb_strlen($c['cabecalho']) + 6)) . '" customWidth="1"/>';
        $prompt = mb_substr(($c['obrigatoria'] ? 'OBRIGATÓRIA. ' : 'Opcional. ') . $c['descricao'] . ' Ex.: ' . $c['exemplo'], 0, 250);
        $titulo = mb_substr($c['cabecalho'], 0, 32);
        if (!empty($c['valores'])) {
            $valid .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" errorTitle="Valor não aceito"'
                    . ' error="' . $x('Use um destes: ' . implode(', ', $c['valores'])) . '"'
                    . ' promptTitle="' . $x($titulo) . '" prompt="' . $x($prompt) . '" sqref="' . $L . '1:' . $L . '50000">'
                    . '<formula1>"' . $x(implode(',', $c['valores'])) . '"</formula1></dataValidation>';
        } else {
            $valid .= '<dataValidation allowBlank="1" showInputMessage="1" promptTitle="' . $x($titulo) . '" prompt="' . $x($prompt) . '" sqref="' . $L . '1:' . $L . '50000"/>';
        }
    }
    $aba1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
          . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
          . '<cols>' . $larg . '</cols><sheetData><row r="1">' . $cab . '</row></sheetData>'
          . '<dataValidations count="' . count($cols) . '">' . $valid . '</dataValidations></worksheet>';

    // Aba 2: instruções
    $linhas = '<row r="1">' . $cel('A1', $def['titulo'], 3) . '</row>'
            . '<row r="2">' . $cel('A2', $def['descricao']) . '</row>'
            . '<row r="3">' . $cel('A3', 'Preencha a aba "dados" a partir da linha 2. Não renomeie nem apague o cabeçalho. Clique numa célula para ver a descrição da coluna.') . '</row>'
            . '<row r="5">' . $cel('A5', 'Coluna', 1) . $cel('B5', 'Obrigatória', 1) . $cel('C5', 'O que preencher', 1) . $cel('D5', 'Exemplo', 1) . $cel('E5', 'Valores aceitos', 1) . '</row>';
    foreach ($cols as $i => $c) {
        $r = 6 + $i;
        $linhas .= '<row r="' . $r . '">' . $cel("A{$r}", $c['cabecalho'], 3) . $cel("B{$r}", $c['obrigatoria'] ? 'Sim' : 'Não')
                 . $cel("C{$r}", $c['descricao']) . $cel("D{$r}", $c['exemplo']) . $cel("E{$r}", implode(', ', $c['valores'] ?? [])) . '</row>';
    }
    $aba2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
          . '<cols><col min="1" max="1" width="38" customWidth="1"/><col min="2" max="2" width="12" customWidth="1"/>'
          . '<col min="3" max="3" width="90" customWidth="1"/><col min="4" max="4" width="30" customWidth="1"/><col min="5" max="5" width="40" customWidth="1"/></cols>'
          . '<sheetData>' . $linhas . '</sheetData></worksheet>';

    // Estilos: 1 = cabeçalho obrigatório (azul-petróleo), 2 = cabeçalho opcional (cinza), 3 = negrito
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F6E8C"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF7A8796"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="4"><xf/>'
            . '<xf fontId="1" fillId="2" applyFont="1" applyFill="1"/>'
            . '<xf fontId="1" fillId="3" applyFont="1" applyFill="1"/>'
            . '<xf fontId="2" applyFont="1"/></cellXfs></styleSheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'mod');
    $z = new ZipArchive();
    $z->open($tmp, ZipArchive::OVERWRITE);
    $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="dados" sheetId="1" r:id="rId1"/><sheet name="Instruções" sheetId="2" r:id="rId2"/></sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $z->addFromString('xl/worksheets/sheet1.xml', $aba1);
    $z->addFromString('xl/worksheets/sheet2.xml', $aba2);
    $z->addFromString('xl/styles.xml', $styles);
    $z->close();
    $bin = (string) file_get_contents($tmp);
    @unlink($tmp);
    return $bin;
}
