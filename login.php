<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to_role_home();
}

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);

    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        flash('error', 'Informe um e-mail válido e a senha.');
        redirect('login.php');
    }

    $stmt = $pdo->prepare('SELECT id, nome, email, senha, role FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($senha, (string) $user['senha'])) {
        flash('error', 'Credenciais inválidas.');
        redirect('login.php');
    }

    login_user($user);

    redirect_to_role_home();
}

$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>
<div class="login-page">
    <div class="login-bg-circle login-bg-circle-1"></div>
    <div class="login-bg-circle login-bg-circle-2"></div>

    <div class="login-center">
        <img src="<?= e(url('assets/img/logo fl360.png')) ?>" alt="Logo FL360" class="login-logo-full">

        <div class="login-card">
            <div class="login-card-body">
                <h2>Acesse sua conta</h2>
                <p>Entre com seu e-mail e senha para continuar.</p>

                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <label for="email">E-mail</label>
                    <input id="email" type="email" name="email" required autocomplete="email" placeholder="voce@exemplo.com">

                    <label for="senha">Senha</label>
                    <input id="senha" type="password" name="senha" required autocomplete="current-password" placeholder="Sua senha">

                    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
