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
  a pessoa da revisão (pelo CPF) ao líder e ao e-mail dele.

## Instalação

1. Copie a pasta para `horacius-tools/funcoes/Censo/`. PHP 8.1+ com `pdo_sqlite`,
   `pdo_mysql` e `zip`.
2. Copie `config.local.example.php` para `config.local.php` e preencha: banco do Horacius
   (`CENSO_DB_*`), Microsoft Graph (`CENSO_GRAPH_*`, aplicação com permissão de aplicação
   `Mail.Send`) e os remetentes (`CENSO_REMETENTES`). O Censo não lê configuração de nenhum
   outro sistema. Em **Configurações** dá para testar o banco e enviar um e-mail de teste.
3. A pasta `dados/` precisa aceitar gravação pelo servidor web. O `.htaccess` dela
   bloqueia o acesso por URL (banco SQLite e arquivos enviados).
4. No primeiro acesso, a tela de login cria o administrador.

No Horacius Tools, o acesso ao menu depende da permissão `view_censo`.
