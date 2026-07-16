<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to_role_home();
}

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);

    $nome   = trim((string) ($_POST['nome']   ?? ''));
    $email  = trim((string) ($_POST['email']  ?? ''));
    $senha  = (string) ($_POST['senha']  ?? '');
    $senha2 = (string) ($_POST['senha2'] ?? '');

    $erros = [];

    if ($nome === '') {
        $erros[] = 'Informe seu nome completo.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }

    if (strlen($senha) < 6) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    }

    if ($senha !== $senha2) {
        $erros[] = 'As senhas não coincidem.';
    }

    if (empty($erros)) {
        $chk = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $chk->execute([':email' => $email]);
        if ($chk->fetchColumn()) {
            $erros[] = 'Este e-mail já está cadastrado.';
        }
    }

    if (empty($erros)) {
        $ins = $pdo->prepare(
            'INSERT INTO users (nome, email, senha, role, criado_em)
             VALUES (:nome, :email, :senha, :role, NOW())'
        );
        $ins->execute([
            ':nome'  => $nome,
            ':email' => $email,
            ':senha' => password_hash($senha, PASSWORD_DEFAULT),
            ':role'  => 'aluno',
        ]);

        flash('success', 'Cadastro realizado com sucesso! Faça login para acessar.');
        redirect('login.php');
    }

    $erroJoin = implode(' ', $erros);
    flash('error', $erroJoin);
    redirect('register.php');
}

$page_title = 'Criar conta';
require_once __DIR__ . '/includes/header.php';
?>
<div class="login-page">
    <div class="login-bg-circle login-bg-circle-1"></div>
    <div class="login-bg-circle login-bg-circle-2"></div>

    <div class="login-center">
        <img src="<?= e(url('assets/img/logo fl360.png')) ?>" alt="Logo FL360" class="login-logo-full">

        <div class="login-card">
            <div class="login-card-body">
                <h2>Criar conta</h2>
                <p>Preencha os dados abaixo para se cadastrar.</p>

                <form method="post" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <label for="nome">Nome completo</label>
                    <input id="nome" type="text" name="nome" required autocomplete="name" placeholder="Seu nome completo">

                    <label for="email">E-mail</label>
                    <input id="email" type="email" name="email" required autocomplete="email" placeholder="voce@exemplo.com">

                    <label for="senha">Senha</label>
                    <input id="senha" type="password" name="senha" required autocomplete="new-password" placeholder="Mínimo 6 caracteres">

                    <label for="senha2">Confirmar senha</label>
                    <input id="senha2" type="password" name="senha2" required autocomplete="new-password" placeholder="Repita a senha">

                    <button type="submit" class="btn btn-primary btn-block">Criar conta</button>
                </form>

                <p style="margin-top:1.1rem;text-align:center;font-size:.9rem;color:#44596a;">
                    Já tem conta?
                    <a href="<?= e(url('login.php')) ?>" style="color:#1593A8;font-weight:600;">Faça login</a>
                </p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
