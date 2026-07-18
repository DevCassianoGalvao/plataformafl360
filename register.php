<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to_role_home();
}

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');
    $senha2 = (string) ($_POST['senha2'] ?? '');
    $errors = [];
    $nameLength = function_exists('mb_strlen') ? mb_strlen($nome) : strlen($nome);

    if ($nameLength < 3 || $nameLength > 150) {
        $errors[] = 'Informe seu nome completo.';
    }
    if (!email_has_valid_domain($email)) {
        $errors[] = 'Informe um e-mail válido.';
    }
    if ($passwordError = password_validation_error($senha)) {
        $errors[] = $passwordError;
    }
    if ($senha !== $senha2) {
        $errors[] = 'As senhas não coincidem.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetchColumn()) {
            $errors[] = 'Não foi possível criar outro cadastro com esses dados. Se você já enviou sua solicitação, aguarde a aprovação e depois vá para o login.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users
                 (nome, email, senha, role, status, criado_em)
                 VALUES (:nome, :email, :senha, 'aluno', 'pendente', NOW())"
            );
            $stmt->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':senha' => password_hash($senha, PASSWORD_DEFAULT),
            ]);

            redirect('register.php?enviado=1');
        } catch (Throwable $exception) {
            error_log('Falha no autocadastro FL360. Código: ' . $exception->getCode());
            $errors[] = 'Não foi possível concluir o cadastro agora. Tente novamente em alguns minutos.';
        }
    }

    flash('error', implode(' ', $errors));
    redirect('register.php');
}

$page_title = 'Criar conta';
require_once __DIR__ . '/includes/header.php';
$registrationSent = ($_GET['enviado'] ?? '') === '1';
?>
<main class="auth-page">
    <section class="auth-brand" aria-label="Programa Friburgo Líder 360">
        <img src="<?= e(url('assets/img/logo fl360.png')) ?>" alt="FL360 - Friburgo Líder 360" class="auth-logo">
        <div>
            <span class="eyebrow">Formação cidadã</span>
            <h1>Seu próximo passo começa aqui.</h1>
            <p>Crie sua conta. Por segurança, o acesso será analisado e aprovado pela equipe FL360.</p>
        </div>
    </section>

    <section class="auth-form-area">
        <div class="auth-card">
            <?php if ($registrationSent): ?>
                <div class="auth-confirmation" role="status">
                    <span class="confirmation-mark" aria-hidden="true">OK</span>
                    <span class="eyebrow">Cadastro enviado</span>
                    <h2>Agora aguarde a aprovação</h2>
                    <p>Seu cadastro foi recebido. Aguarde a equipe FL360 analisar e aprovar sua entrada.</p>
                    <ol class="confirmation-steps">
                        <li>Cadastro recebido pela plataforma.</li>
                        <li>Administrador analisa e aprova o acesso.</li>
                        <li>Depois da aprovação, volte ao login.</li>
                    </ol>
                    <a class="btn btn-primary btn-block" href="<?= e(url('login.php')) ?>">Ir para o login</a>
                </div>
            <?php else: ?>
                <a class="auth-back" href="<?= e(url('login.php')) ?>">Voltar ao login</a>
                <span class="eyebrow">Novo aluno</span>
                <h2>Criar conta</h2>
                <p>Use um e-mail que você acessa e escolha uma frase-senha exclusiva.</p>

                <form method="post" class="form-grid auth-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="nome">Nome completo</label>
                <input id="nome" type="text" name="nome" maxlength="150" required autocomplete="name" placeholder="Seu nome completo">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" maxlength="180" required autocomplete="email" placeholder="voce@exemplo.com.br">
                <label for="senha">Senha</label>
                <input id="senha" type="password" name="senha" minlength="12" maxlength="72" required autocomplete="new-password" placeholder="Mínimo de 12 caracteres" data-password-strength>
                <div class="password-strength" data-password-feedback>Use 12 ou mais caracteres. Uma frase longa é mais segura.</div>
                <label for="senha2">Confirmar senha</label>
                <input id="senha2" type="password" name="senha2" minlength="12" maxlength="72" required autocomplete="new-password" placeholder="Repita a senha">
                <button type="submit" class="btn btn-primary btn-block">Enviar cadastro</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
