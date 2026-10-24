# Instalação limpa do Censo

O pacote do servidor não contém usuários, revisões, base de funcionários,
modelos personalizados, histórico de cobranças, uploads ou credenciais locais.
A pasta `dados/` contém somente a proteção `.htaccess`. O banco é criado no primeiro acesso.

1. Extraia a pasta `Censo` para o servidor. O caminho padrão é
   `horacius-tools/funcoes/Censo/` dentro da raiz web.
2. Use PHP 8.1+ com as extensões `pdo_sqlite`, `pdo_mysql`, `mbstring`, `curl` e `zip`.
   Não é necessário executar Composer nem compilar o front-end.
3. O `.env` fornecido no ZIP é um modelo sem credenciais. Preencha os bancos do Horacius,
   a aplicação Microsoft Graph e os remetentes do servidor. O arquivo `.env.example`
   documenta os campos. Não copie o `config.local.php` nem o `.env` da máquina de desenvolvimento.
4. Se o domínio ou caminho forem diferentes do padrão, defina `CENSO_BASE_URL` no `.env`
   com o endereço completo do Censo, sem barra final.
5. Permita que o processo PHP grave em `dados/`. Preserve todos os arquivos `.htaccess`,
   incluindo os de `config/`, `includes/` e da raiz. No Apache, as regras precisam estar
   habilitadas. Se usar outro servidor HTTP, aplique bloqueios equivalentes.
6. Abra `login.php` e crie o primeiro administrador antes de liberar o acesso aos demais.
   Nenhum usuário ou senha padrão é incluído no pacote.
7. Em **Configurações**, confira as integrações. Depois importe a base de funcionários
   e inicie a primeira revisão com suas planilhas.

O cookie de sessão do pacote tem um nome exclusivo, definido em `CENSO_SESSION_NAME`.
A limpeza local também troca esse identificador para invalidar as sessões antigas.
Backups locais e arquivos de teste ficam fora do pacote de implantação.
