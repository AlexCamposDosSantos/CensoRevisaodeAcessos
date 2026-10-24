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
  **Pendentes** com filtro e exportação; **Dados e arquivos** de cada revisão, com o
  download do arquivo original enviado.

## Instalação

1. Copie a pasta para `horacius-tools/funcoes/Censo/`. PHP 8.1+ com `pdo_sqlite`,
   `pdo_mysql` e `zip`.
2. Credenciais do banco do Horacius: por padrão são lidas do `.env` do Prumo
   (`../divergencia-ia/.env`, chaves `DB_PROD_*`). Para outra conexão, crie
   `config.local.php` definindo `CENSO_DB_HOST`, `CENSO_DB_PORT`, `CENSO_DB_NAME`,
   `CENSO_DB_USER` e `CENSO_DB_PASS`.
3. A pasta `dados/` precisa aceitar gravação pelo servidor web. O `.htaccess` dela
   bloqueia o acesso por URL (banco SQLite e arquivos enviados).
4. No primeiro acesso, a tela de login cria o administrador.

No Horacius Tools, o acesso ao menu depende da permissão `view_censo`.
