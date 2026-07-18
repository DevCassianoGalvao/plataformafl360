<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$currentAdminId = (int) ($_SESSION['user_id'] ?? 0);
$returnPath = 'admin/usuarios.php';

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'approve' || $action === 'reject') {
        if ($id <= 0) {
            flash('error', 'Usuário inválido.');
            redirect($returnPath);
        }
        if ($action === 'approve') {
            $pdo->prepare("UPDATE users SET status = 'ativo' WHERE id = :id")->execute([':id' => $id]);
            flash('success', 'Cadastro aprovado. O usuário já pode entrar.');
        } else {
            $pdo->prepare("UPDATE users SET status = 'rejeitado' WHERE id = :id")->execute([':id' => $id]);
            flash('success', 'Cadastro rejeitado.');
        }
        redirect($returnPath);
    }

    if ($action === 'create' || $action === 'update') {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = (string) ($_POST['role'] ?? 'aluno');
        $password = (string) ($_POST[$action === 'create' ? 'senha' : 'nova_senha'] ?? '');

        if (mb_strlen($nome) < 3 || !email_has_valid_domain($email) || !in_array($role, ['admin', 'professor', 'aluno'], true)) {
            flash('error', 'Revise nome, e-mail e tipo de usuário.');
            redirect($returnPath);
        }
        if (($action === 'create' || $password !== '') && ($passwordError = password_validation_error($password))) {
            flash('error', $passwordError);
            redirect($returnPath);
        }
        if ($action === 'update' && $id === $currentAdminId && $role !== 'admin') {
            flash('error', 'Você não pode remover seu próprio acesso administrativo.');
            redirect($returnPath);
        }

        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (nome, email, senha, role, status, email_verificado_em, criado_em)
                     VALUES (:nome, :email, :senha, :role, 'ativo', NOW(), NOW())"
                );
                $stmt->execute([':nome' => $nome, ':email' => $email, ':senha' => password_hash($password, PASSWORD_DEFAULT), ':role' => $role]);
                flash('success', 'Usuário criado e liberado com sucesso.');
            } else {
                $sql = 'UPDATE users SET nome = :nome, email = :email, role = :role';
                $params = [':nome' => $nome, ':email' => $email, ':role' => $role, ':id' => $id];
                if ($password !== '') {
                    $sql .= ', senha = :senha';
                    $params[':senha'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id = :id';
                $pdo->prepare($sql)->execute($params);
                flash('success', 'Usuário atualizado.');
            }
        } catch (PDOException $exception) {
            flash('error', 'Não foi possível salvar. Verifique se o e-mail já está cadastrado.');
        }
        redirect($returnPath);
    }

    if ($action === 'delete') {
        if ($id === $currentAdminId) {
            flash('error', 'Você não pode excluir o próprio usuário.');
            redirect($returnPath);
        }
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
        flash('success', 'Usuário excluído.');
        redirect($returnPath);
    }
}

$users = $pdo->query(
    'SELECT id, nome, email, role, status, email_verificado_em, criado_em
     FROM users ORDER BY (status = \'pendente\') DESC, criado_em DESC'
)->fetchAll();
$pendingCount = count(array_filter($users, static fn(array $user): bool => $user['status'] === 'pendente'));
$registerUrl = absolute_url('register.php');

$active_page = 'usuarios';
$page_title = 'Gestão de usuários';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <section class="page-heading">
            <div><span class="eyebrow">Acessos e segurança</span><h1>Usuários</h1><p>Aprove cadastros externos e gerencie a equipe do portal.</p></div>
            <?php if ($pendingCount): ?><span class="badge badge-warning"><?= $pendingCount ?> pendente<?= $pendingCount > 1 ? 's' : '' ?></span><?php endif; ?>
        </section>

        <section class="panel compact-panel">
            <div class="panel-header"><h2>Link de cadastro</h2><span class="badge badge-neutral">Requer aprovação</span></div>
            <div class="copy-field"><input id="registerLink" type="text" value="<?= e($registerUrl) ?>" readonly><button type="button" class="btn btn-ghost" data-copy-target="registerLink">Copiar</button></div>
        </section>

        <details class="panel disclosure-panel">
            <summary><span><strong>Criar usuário manualmente</strong><small>O acesso criado aqui já fica confirmado e ativo.</small></span><span class="disclosure-icon">+</span></summary>
            <form method="post" class="form-grid form-grid-columns disclosure-content">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
                <label>Nome completo<input type="text" name="nome" maxlength="150" required></label>
                <label>E-mail<input type="email" name="email" maxlength="180" required></label>
                <label>Senha inicial<input type="password" name="senha" minlength="12" maxlength="72" required placeholder="Mínimo de 12 caracteres"></label>
                <label>Tipo<select name="role" required><option value="aluno">Aluno</option><option value="professor">Professor</option><option value="admin">Administrador</option></select></label>
                <div class="field-wide"><button class="btn btn-primary" type="submit">Criar usuário</button></div>
            </form>
        </details>

        <section class="panel">
            <div class="panel-header"><h2>Cadastros e acessos</h2><span class="badge badge-neutral"><?= count($users) ?> usuários</span></div>
            <div class="user-admin-grid">
                <?php foreach ($users as $user): ?>
                    <article class="user-admin-card <?= $user['status'] === 'pendente' ? 'is-pending' : '' ?>">
                        <div class="user-admin-head">
                            <div class="user-admin-identity"><strong><?= e($user['nome']) ?></strong><small><?= e($user['email']) ?></small></div>
                            <span class="badge badge-neutral"><?= e($user['role']) ?></span>
                        </div>
                        <div class="user-status-row">
                            <span class="badge <?= $user['status'] === 'ativo' ? 'badge-success' : ($user['status'] === 'pendente' ? 'badge-warning' : 'badge-danger') ?>"><?= e($user['status']) ?></span>
                        </div>
                        <?php if ($user['status'] === 'pendente'): ?>
                            <div class="inline-form wrap">
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="btn btn-primary">Aprovar</button></form>
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="btn btn-danger">Rejeitar</button></form>
                            </div>
                        <?php endif; ?>
                        <details class="user-edit"><summary>Editar dados</summary>
                            <form method="post" class="form-grid">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <label>Nome<input type="text" name="nome" value="<?= e($user['nome']) ?>" required></label><label>E-mail<input type="email" name="email" value="<?= e($user['email']) ?>" required></label>
                                <label>Nova senha<input type="password" name="nova_senha" minlength="12" maxlength="72" placeholder="Deixe em branco para manter"></label>
                                <label>Tipo<select name="role"><option value="aluno" <?= $user['role'] === 'aluno' ? 'selected' : '' ?>>Aluno</option><option value="professor" <?= $user['role'] === 'professor' ? 'selected' : '' ?>>Professor</option><option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option></select></label>
                                <button class="btn btn-primary">Salvar alterações</button>
                            </form>
                            <?php if ((int) $user['id'] !== $currentAdminId): ?><form method="post" onsubmit="return confirm('Excluir este usuário e todos os dados relacionados?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="btn btn-danger">Excluir usuário</button></form><?php endif; ?>
                        </details>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
