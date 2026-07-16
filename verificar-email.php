<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$token = (string) ($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    flash('error', 'Link de confirmação inválido ou expirado.');
    redirect('login.php');
}

$stmt = $pdo->prepare(
    'SELECT id FROM users
     WHERE email_verification_hash = :token_hash
       AND email_verification_expires >= NOW()
       AND email_verificado_em IS NULL
     LIMIT 1'
);
$stmt->execute([':token_hash' => hash('sha256', $token)]);
$userId = (int) ($stmt->fetchColumn() ?: 0);

if ($userId <= 0) {
    flash('error', 'Link de confirmação inválido ou expirado.');
    redirect('login.php');
}

$stmt = $pdo->prepare(
    'UPDATE users SET email_verificado_em = NOW(), email_verification_hash = NULL, email_verification_expires = NULL WHERE id = :id'
);
$stmt->execute([':id' => $userId]);
flash('success', 'E-mail confirmado. Agora aguarde a aprovação da administração.');
redirect('login.php');
