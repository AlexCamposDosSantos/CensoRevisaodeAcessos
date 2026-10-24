# Censo — Revisão de Acessos

Acompanhamento da revisão de acessos e cobrança de quem ainda não revisou.
Faz parte do Horacius Tools (`funcoes/Censo/`), mas tem login e usuários próprios.

## Como funciona

- **Nova revisão** (administrador): nome, data de início e upload da **base de pessoas**
  (.xlsx ou .csv). O modelo da planilha é baixado na própria tela — cada coluna traz a
  descrição do que preencher, e há uma aba "Instruções".
- **Andamento automático**: lido no banco do Horacius (`ac_revisao`) desde a data de
  início. Concluído = a pessoa tem ao menos uma revisão registrada; pendente = nenhuma.
  Nada vem do SharePoint.
- **Painel** por grupo (Empregado, Terceirizado e Polo / Professor) e por vínculo;
  **Pendentes** com líder e histórico de cobranças; **Dados e arquivos** de cada revisão,
  com o download do arquivo original enviado.
- **Cobranças**: modelos de e-mail (topo, corpo e rodapé) e o assistente que pergunta quem
  cobrar (o gestor, ou o líder do gestor em escalonamento, com o outro em cópia), mostra a
  prévia (destinatários, o e-mail montado e quem ficou sem destinatário) e envia em lotes
  pela Microsoft Graph, registrando cada e-mail.
- **Base de funcionários** (hierarquia): upload da BaseFull do RH (.csv, .xlsx ou .zip). Liga
  a pessoa da revisão (pelo CPF) ao líder e ao e-mail dele. A BaseFull importada mais recente
  é priorizada; o líder da planilha da revisão é uma alternativa quando a BaseFull não
  encontra um contato válido. Os campos originais enviados na revisão são preservados.

## Atualizar andamento e identificar líderes

No painel, **Atualizar andamento** consulta o Horacius e cruza os participantes da revisão
com a última BaseFull importada em **Base de funcionários**. A consulta usa CPF e matrícula
do líder, com a mesma preferência por vínculos ativos usada no restante do sistema.
O cruzamento é feito em lotes; não é necessário reler o CSV original a cada atualização.
Para usar uma nova BaseFull, importe o arquivo atualizado antes de clicar no botão.

Durante a consulta, o botão mostra **Atualizando…**, bloqueia cliques repetidos e exibe
uma mensagem de atividade. Ao concluir, o painel recarrega e informa o resultado das duas
consultas. Se a BaseFull não estiver importada ou o Horacius falhar, a tela informa essa
limitação e preserva os dados disponíveis. Sem JavaScript, o formulário mantém o envio normal.

## Estrutura dos arquivos

```text
Censo/
├── .env.example                # Modelo sem credenciais reais
├── .env                        # Configuração local privada (ignorada pelo Git)
├── .htaccess                   # Bloqueio HTTP de configurações e metadados Git
├── config.php                  # Entrada compatível da configuração
├── config.local.php            # Fallback legado opcional; excluído do pacote do servidor
├── config/
│   ├── app.php                 # Configuração central da aplicação
│   ├── environment.php         # Leitura do ambiente e do .env
│   └── legacy.example.php      # Exemplo do formato PHP anterior
├── includes/
│   ├── auth.php                # Autenticação, sessão e permissões
│   ├── db.php                  # Conexão e estrutura SQLite
│   ├── services/               # Revisões, hierarquia, cobranças, e-mail e indicadores
│   └── views/                  # Cabeçalho, rodapé e componentes do painel
├── assets/
│   ├── css/                    # Estilos compartilhados
│   ├── js/                     # Interações do front-end
│   ├── img/                    # Marca, logotipo e favicon
│   └── email/                  # Imagens dos modelos de e-mail
├── dados/                      # Banco local e arquivos enviados; acesso HTTP bloqueado
├── users/                      # Páginas de administração de usuários
└── *.php                       # Páginas e endpoints com os endereços originais
```

## Instalação limpa no servidor

Consulte [o guia de instalação](docs/INSTALACAO_SERVIDOR.md). O pacote limpo inclui
um `.env` sem credenciais e a pasta `dados/` somente com `.htaccess`. No primeiro
acesso, o próprio sistema cria o banco e solicita o cadastro do administrador.

## Instalação e configuração

1. Copie a pasta para `horacius-tools/funcoes/Censo/`. PHP 8.1+ com `pdo_sqlite`,
   `pdo_mysql`, `mbstring`, `curl` e `zip`. Não há dependências Composer ou etapa de build.
2. Copie `.env.example` para `.env` e preencha os ambientes do Horacius e a integração
   Microsoft Graph. Na instalação existente, o `.env` foi gerado com os valores locais anteriores.
3. Configure `CENSO_DB_AMBIENTES` com os identificadores separados por vírgula, por exemplo
   `producao,homologacao`. Cada identificador corresponde a `CENSO_DB_<ID>_HOST`, `PORT`,
   `NAME`, `USER`, `PASS` e `ROTULO`. O primeiro é o padrão inicial; a escolha salva no painel
   continua tendo efeito enquanto o ambiente existir.
4. Preencha `CENSO_GRAPH_TENANT_ID`, `CENSO_GRAPH_CLIENT_ID` e `CENSO_REMETENTES`.
   Cada remetente tem um identificador, com os campos `CENSO_REMETENTE_<ID>_EMAIL`,
   `NOME` e `SECRET`. O segredo é o da aplicação Entra ID com permissão de aplicação
   `Mail.Send`, conforme a configuração anterior.
5. A pasta `dados/` precisa aceitar gravação pelo servidor web. Não altere sua localização:
   ela contém os usuários, as revisões e os arquivos já enviados.
6. No primeiro acesso, a tela de login cria o administrador. Em **Configurações** é possível
   testar a conexão com o banco e, por ação explícita, enviar um e-mail de teste.

### Como os valores são escolhidos

- Variáveis do processo/servidor prevalecem sobre o `.env`.
- O `.env` é exclusivo do Censo. Valores legados de `config.local.php` são usados como fallback.
- `CENSO_DB_AMBIENTES` e `CENSO_REMETENTES`, quando definidos, determinam integralmente
  suas respectivas listas. Um valor vazio desativa a lista, sem recorrer à configuração antiga.
- Sem configuração local, permanecem os padrões do sistema. `CENSO_BASE_URL` é opcional:
  quando ausente, o endereço continua sendo detectado pela requisição.
- Alterações no `.env` são lidas na próxima requisição. Alterações em variáveis do serviço
  podem exigir reiniciar o PHP/servidor.

Use valores em uma linha. Aspas simples preservam o conteúdo literal; aspas duplas usam
escapes JSON (`\n`, `\t`, `\\`, `\"`). Não há interpolação de `$VAR`: caracteres como `$`,
`#` e `=` dentro de aspas fazem parte do valor. O `.env` gerado usa aspas duplas com escapes.

### Arquivos privados

`.env`, suas variações e `config.local.php` são ignorados pelo Git. Apenas `.env.example`,
sem valores reais, deve ser versionado. As regras `.htaccess` bloqueiam a leitura HTTP do
`.env`, de `config/`, de `includes/` e dos metadados `.git` no Apache/Laragon. Em outros
servidores, aplique regras equivalentes antes de disponibilizar o sistema.

No Horacius Tools, o acesso ao menu depende da permissão `view_censo`.
