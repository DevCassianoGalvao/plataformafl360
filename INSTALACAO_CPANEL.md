# Instalação no cPanel (Passo a Passo de Iniciante) - FL360

## 1) Preparar o pacote no seu computador
1. Abra a pasta do projeto FL360.
2. Selecione todos os arquivos e pastas do sistema (não compacte a pasta "pai", compacte o conteúdo).
3. Clique com botão direito e escolha **Enviar para > Pasta compactada (.zip)**.
4. Nomeie como `plataformafl360.zip`.

## 2) Enviar para o cPanel
1. Entre no cPanel.
2. Abra **Gerenciador de Arquivos**.
3. Entre em `public_html`.
4. Crie a pasta `plataformafl360` (se ainda não existir).
5. Entre nela e clique em **Upload**.
6. Envie o arquivo `plataformafl360.zip`.
7. Volte para a pasta e clique em **Extrair**.
8. Confirme que os arquivos ficaram direto em:
   - `/public_html/plataformafl360/login.php`
   - `/public_html/plataformafl360/includes/db.php`

## 3) Criar banco e usuário MySQL
1. No cPanel, abra **MySQL Database Wizard**.
2. Crie o banco (exemplo: `xdigcomb_plataformafl360`).
3. Crie o usuário MySQL.
4. Defina uma senha forte.
5. Marque **ALL PRIVILEGES** para este usuário no banco.

## 4) Importar SQL
1. Abra **phpMyAdmin**.
2. Clique no banco criado.
3. Vá em **Importar**.
4. Importe `database_cpanel.sql`.
5. Aguarde a mensagem de sucesso.

## 5) Configurar o `db.php`
Arquivo: `includes/db.php`

Use este padrão:

```php
<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

define('APP_NAME', 'Portal do Aluno - FL360');
define('BASE_PATH', '/plataformafl360');

$dbHost = 'localhost';
$dbName = 'SEU_BANCO';
$dbUser = 'SEU_USUARIO';
$dbPass = 'SUA_SENHA';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $exception) {
    http_response_code(500);
    die('Erro ao conectar com o banco de dados. Verifique o arquivo includes/db.php.');
}
```

## 6) Permissões recomendadas
- Pastas: `755`
- Arquivos: `644`
- Pasta `uploads`: `755` (ou `775` se o servidor exigir escrita)

## 7) Primeiro acesso
URL:
`https://maicongoncalves.com.br/plataformafl360/login.php`

Use o e-mail e a senha segura definidos por você no `install.php`. O sistema não possui credenciais padrão.

## 8) Checklist final (rápido)
1. Login funciona.
2. Dashboard carrega sem erro 500.
3. Módulos e aulas abrem normalmente.
4. Materiais baixam pelo botão **Baixar**.
5. Foto de perfil sobe e aparece redonda.
6. Notificações (sino) aparecem no topo.

## 9) Se der erro 500
1. Abra o arquivo `error_log` no Gerenciador de Arquivos.
2. Copie as últimas linhas.
3. Corrija com base na linha/arquivo indicado no log.
