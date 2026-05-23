# Portal do Aluno - FL360

Sistema EAD em PHP puro + MySQL com área do aluno e CMS administrativo.

## Stack
- PHP puro (sem framework)
- MySQL
- HTML5
- CSS3
- JavaScript Vanilla

## Funcionalidades
- Login seguro com `password_hash()` e `password_verify()`
- Sessão com `$_SESSION['user_id']` e `$_SESSION['role']`
- Dashboard do aluno com progresso, avisos e últimas aulas
- Módulos e aulas com vídeo do YouTube (iframe responsivo)
- Biblioteca de materiais com download seguro via PHP
- Perfil do aluno com foto (upload, troca, exclusão e recorte automático)
- Notificações com sino no topo, contador e página de novidades
- Fórum da comunidade (tópicos e respostas)
- CMS admin para usuários, módulos, aulas, materiais e avisos
- Dark mode em todo o portal

## Segurança
- Prepared statements (PDO)
- Proteção contra CSRF
- Sanitização com `htmlspecialchars()`
- Validação de upload por extensão, MIME e tamanho
- Bloqueio de acesso por sessão e perfil

## Estrutura
```
/fl360
  /assets
    /css
    /js
    /img
  /uploads
  /includes
  /pages
    download.php
  /admin
  index.php
  login.php
  logout.php
  database.sql
  database_cpanel.sql
```

## Instalação rápida
1. Importe `database.sql` (local) ou `database_cpanel.sql` (cPanel/phpMyAdmin).
2. Configure `includes/db.php` com os dados reais do banco.
3. Ajuste `BASE_PATH` para a subpasta publicada (ex.: `/plataformafl360`).
4. Garanta permissão de escrita em `uploads/`.
5. Acesse `login.php`.

## Acesso inicial admin
- E-mail: `admin@fl360.local`
- Senha: `33222`

O admin inicial é criado automaticamente apenas se não existir nenhum administrador no banco.
