<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$currentAdminId = (int) ($_SESSION['user_id'] ?? 0);

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $senha = (string) ($_POST['senha'] ?? '');
        $role = (string) ($_POST['role'] ?? 'aluno');

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '' || !in_array($role, ['admin', 'aluno'], true)) {
            flash('error', 'Dados invalidos para criar usuario.');
            redirect('admin/usuarios.php');
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (nome, email, senha, role, criado_em)
                 VALUES (:nome, :email, :senha, :role, NOW())'
            );
            $stmt->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                ':role' => $role,
            ]);

            flash('success', 'Usuario criado com sucesso.');
        } catch (PDOException $exception) {
            flash('error', 'Nao foi possivel criar o usuario (email pode ja existir).');
        }

        redirect('admin/usuarios.php');
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = (string) ($_POST['role'] ?? 'aluno');
        $novaSenha = (string) ($_POST['nova_senha'] ?? '');

        if ($id <= 0 || $nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['admin', 'aluno'], true)) {
            flash('error', 'Dados invalidos para atualizar usuario.');
            redirect('admin/usuarios.php');
        }

        try {
            if ($novaSenha !== '') {
                $stmt = $pdo->prepare('UPDATE users SET nome = :nome, email = :email, role = :role, senha = :senha WHERE id = :id');
                $stmt->execute([
                    ':nome' => $nome,
                    ':email' => $email,
                    ':role' => $role,
                    ':senha' => password_hash($novaSenha, PASSWORD_DEFAULT),
                    ':id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET nome = :nome, email = :email, role = :role WHERE id = :id');
                $stmt->execute([
                    ':nome' => $nome,
                    ':email' => $email,
                    ':role' => $role,
                    ':id' => $id,
                ]);
            }

            flash('success', 'Usuario atualizado.');
        } catch (PDOException $exception) {
            flash('error', 'Falha ao atualizar usuario (email duplicado?).');
        }

        redirect('admin/usuarios.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === $currentAdminId) {
            flash('error', 'Voce nao pode remover o proprio usuario logado.');
            redirect('admin/usuarios.php');
        }

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);

        flash('success', 'Usuario excluido.');
        redirect('admin/usuarios.php');
    }
}

$usersStmt = $pdo->query('SELECT id, nome, email, role, criado_em FROM users ORDER BY criado_em DESC');
$users = $usersStmt->fetchAll();

$active_page = 'usuarios';
$page_title = 'Gerenciar Usuarios';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header">
                <h2>Link de auto-cadastro para alunos</h2>
            </div>
            <p style="margin-bottom:.75rem;">Compartilhe este link com seus alunos para que eles se cadastrem diretamente:</p>
            <?php $registerUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('register.php'); ?>
            <div class="inline-form wrap" style="gap:.5rem;align-items:center;">
                <input id="registerLink" type="text" value="<?= e($registerUrl) ?>" readonly
                       style="flex:1;min-width:260px;" onclick="this.select()">
                <button type="button" class="btn btn-ghost"
                        onclick="navigator.clipboard.writeText(document.getElementById('registerLink').value).then(()=>alert('Link copiado!'))">
                    Copiar link
                </button>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h1>Novo Usuario</h1>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">

                <label for="nome">Nome</label>
                <input id="nome" type="text" name="nome" required>

                <label for="email">Email</label>
                <input id="email" type="email" name="email" required>

                <label for="senha">Senha</label>
                <input id="senha" type="password" name="senha" required>

                <label for="role">Tipo de usuario</label>
                <select id="role" name="role" required>
                    <option value="aluno">Aluno</option>
                    <option value="admin">Admin</option>
                </select>

                <button type="submit" class="btn btn-primary">Criar usuario</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Lista de Usuarios</h2>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Dados</th>
                        <th>Tipo</th>
                        <th>Criado em</th>
                        <th>Acoes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$users): ?>
                        <tr><td colspan="5">Nenhum usuario cadastrado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= e((string) $user['id']) ?></td>
                                <td>
                                    <form method="post" class="inline-form wrap">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">

                                        <input type="text" name="nome" value="<?= e($user['nome']) ?>" required>
                                        <input type="email" name="email" value="<?= e($user['email']) ?>" required>
                                        <input type="password" name="nova_senha" placeholder="Nova senha (opcional)">
                                        <select name="role" required>
                                            <option value="aluno" <?= $user['role'] === 'aluno' ? 'selected' : '' ?>>Aluno</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        </select>
                                        <button class="btn btn-primary" type="submit">Salvar</button>
                                    </form>
                                </td>
                                <td><span class="badge badge-neutral"><?= e($user['role']) ?></span></td>
                                <td><?= e(date('d/m/Y H:i', strtotime((string) $user['criado_em']))) ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Deseja realmente excluir este usuario?');">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $user['id']) ?>">
                                        <button class="btn btn-danger" type="submit">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
