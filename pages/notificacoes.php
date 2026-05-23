<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'aluno') {
    redirect('admin/dashboard.php');
}

$user = current_user($pdo);
$userId = (int) $user['id'];

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'mark_all') {
        mark_all_notifications_read($pdo, $userId);
        flash('success', 'Todas as novidades foram marcadas como lidas.');
        redirect('pages/notificacoes.php');
    }

    if ($action === 'mark_one') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            mark_notification_read($pdo, $notificationId, $userId);
        }
        redirect('pages/notificacoes.php');
    }
}

$stmt = $pdo->prepare(
    'SELECT n.id, n.tipo, n.titulo, n.mensagem, n.url, n.criado_em,
            CASE WHEN nr.id IS NULL THEN 0 ELSE 1 END AS lida
     FROM notifications n
     LEFT JOIN notification_reads nr
       ON nr.notification_id = n.id AND nr.user_id = :user_id
     ORDER BY n.criado_em DESC'
);
$stmt->execute([':user_id' => $userId]);
$notifications = $stmt->fetchAll();

$active_page = 'notificacoes';
$page_title = 'Novidades';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header">
                <h1>Novidades da plataforma</h1>
                <form method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="mark_all">
                    <button type="submit" class="btn btn-ghost">Marcar todas como lidas</button>
                </form>
            </div>

            <div class="notice-list">
                <?php if (!$notifications): ?>
                    <p>Nenhuma novidade registrada.</p>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <article class="notice-item <?= ((int) $notification['lida'] === 0) ? 'notice-unread' : '' ?>">
                            <h3><?= e($notification['titulo']) ?></h3>
                            <small><?= e(date('d/m/Y H:i', strtotime((string) $notification['criado_em']))) ?></small>
                            <p><?= nl2br(e($notification['mensagem'])) ?></p>

                            <div class="inline-form wrap">
                                <?php if (!empty($notification['url'])): ?>
                                    <a class="btn btn-primary" href="<?= e(url((string) $notification['url'])) ?>">Abrir novidade</a>
                                <?php endif; ?>

                                <?php if ((int) $notification['lida'] === 0): ?>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="mark_one">
                                        <input type="hidden" name="notification_id" value="<?= e((string) $notification['id']) ?>">
                                        <button type="submit" class="btn btn-ghost">Marcar como lida</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>