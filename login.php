<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to_role_home();
}

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');

    if (login_is_rate_limited($pdo, $email, $ip)) {
        flash('error', 'Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.');
        redirect('login.php');
    }

    $stmt = $pdo->prepare('SELECT id, nome, email, senha, role, status, email_verificado_em FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    $valid = $user
        && password_verify($senha, (string) $user['senha'])
        && ($user['status'] ?? 'ativo') === 'ativo'
        && !empty($user['email_verificado_em']);

    record_login_attempt($pdo, $email, $ip, (bool) $valid);

    if (!$valid) {
        flash('error', 'Não foi possível entrar. Confira os dados ou aguarde a aprovação da sua conta.');
        redirect('login.php');
    }

    login_user($user);
    redirect_to_role_home();
}

$page_title = 'Entrar';
require_once __DIR__ . '/includes/header.php';
?>
<main class="auth-page">
    <section class="auth-brand" aria-label="Programa Friburgo Líder 360">
        <img src="<?= e(url('assets/img/logo fl360.png')) ?>" alt="FL360 - Friburgo Líder 360" class="auth-logo">
        <div>
            <span class="eyebrow">Portal do Aluno</span>
            <h1>Conhecimento para transformar Friburgo.</h1>
            <p>Acesse aulas, materiais e discussões da sua jornada de formação cidadã.</p>
        </div>
    </section>

    <section class="auth-form-area">
        <div class="auth-card">
            <span class="eyebrow">Bem-vindo de volta</span>
            <h2>Acesse sua conta</h2>
            <p>Entre com seu e-mail e senha para continuar.</p>
            <form method="post" class="form-grid auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" maxlength="180" required autocomplete="email" placeholder="voce@exemplo.com.br">
                <label for="senha">Senha</label>
                <input id="senha" type="password" name="senha" required autocomplete="current-password" placeholder="Sua senha">
                <button type="submit" class="btn btn-primary btn-block">Entrar no portal</button>
            </form>
            <p class="auth-register">Ainda não tem acesso? <a href="<?= e(url('register.php')) ?>">Solicite seu cadastro</a></p>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
