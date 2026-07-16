# Atualização profissional FL360 no cPanel

Esta versão preserva os dados existentes. Ela altera somente a estrutura necessária para professores e materiais por módulo.

## Antes de atualizar

1. No cPanel, abra **phpMyAdmin**.
2. Selecione o banco atual.
3. Clique em **Exportar**, escolha o modo rápido e salve o arquivo SQL.
4. Confirme que `includes/db.php` continua no servidor. Esse arquivo é ignorado pelo Git e não será substituído pelo pull.

## Atualizar pelo Git Version Control

1. No cPanel, abra **Git Version Control**.
2. Entre no repositório da plataforma.
3. Clique em **Update from Remote**.
4. Clique em **Update from Remote** novamente para confirmar.
5. Acesse `https://maicongoncalves.com.br/plataformafl360/` e entre como administrador.
6. No menu, abra **Atualização**.
7. Clique em **Executar atualização** uma única vez.

## Configurar professores

1. Abra **Usuários** no painel administrativo.
2. Crie ou edite uma conta e selecione o tipo **Professor**.
3. Abra **Módulos**.
4. Atribua cada módulo antigo ao professor responsável.
5. O professor entrará pelo login normal e será enviado automaticamente para `/professor/dashboard.php`.

## O que a atualização faz

- adiciona o papel `professor` sem alterar contas existentes;
- adiciona professor responsável aos módulos;
- permite material ligado ao módulo inteiro ou a uma aula;
- mantém IDs, aulas, progresso, avisos e arquivos existentes;
- mantém links antigos do painel administrativo.

## Teste rápido após atualizar

1. Entre como aluno e abra uma aula.
2. Baixe um material existente.
3. Entre como professor e crie um módulo de teste.
4. Crie uma aula nesse módulo.
5. Envie um material para o módulo e outro para a aula.
6. Confirme que outro professor não consegue editar o módulo pela URL.

Se a atualização mostrar erro, não repita várias vezes. Consulte `error_log` no Gerenciador de Arquivos e restaure o backup somente se necessário.
