<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_content_manager();

$manager = current_user($pdo);
$managerId = (int) $manager['id'];
$isAdmin = $manager['role'] === 'admin';
$returnPath = content_manager_path('aulas.php');

if (!db_column_exists($pdo, 'modules', 'professor_id')) {
    flash('error', 'Execute a atualização do banco antes de gerenciar professores.');
    redirect($isAdmin ? 'admin/migracoes.php' : 'professor/dashboard.php');
}

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create' || $action === 'update') {
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
        $ordem = max(0, (int) ($_POST['ordem'] ?? 0));

        if (!can_manage_module($pdo, $moduleId, $manager) || $titulo === '' || get_youtube_embed_url($videoUrl) === '') {
            flash('error', 'Selecione um módulo permitido e informe título e URL do YouTube.');
            redirect($returnPath);
        }

        if ($action === 'update') {
            $find = $pdo->prepare('SELECT module_id FROM lessons WHERE id = :id');
            $find->execute([':id' => $id]);
            $oldModuleId = (int) ($find->fetchColumn() ?: 0);
            if ($id <= 0 || !can_manage_module($pdo, $oldModuleId, $manager)) {
                flash('error', 'Você não tem permissão para alterar esta aula.');
                redirect($returnPath);
            }
            $stmt = $pdo->prepare(
                'UPDATE lessons SET module_id = :module_id, titulo = :titulo, descricao = :descricao,
                 video_url = :video_url, ordem = :ordem WHERE id = :id'
            );
            $stmt->execute([':module_id' => $moduleId, ':titulo' => $titulo, ':descricao' => $descricao, ':video_url' => $videoUrl, ':ordem' => $ordem, ':id' => $id]);
            flash('success', 'Aula atualizada.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO lessons (module_id, titulo, descricao, video_url, ordem)
                 VALUES (:module_id, :titulo, :descricao, :video_url, :ordem)'
            );
            $stmt->execute([':module_id' => $moduleId, ':titulo' => $titulo, ':descricao' => $descricao, ':video_url' => $videoUrl, ':ordem' => $ordem]);
            $lessonId = (int) $pdo->lastInsertId();
            create_notification($pdo, 'aula', 'Nova aula liberada', 'A aula "' . $titulo . '" já está disponível.', 'pages/aula.php?id=' . $lessonId);
            flash('success', 'Aula criada com sucesso.');
        }
        redirect($returnPath);
    }

    if ($action === 'delete') {
        $find = $pdo->prepare('SELECT module_id FROM lessons WHERE id = :id');
        $find->execute([':id' => $id]);
        if (!can_manage_module($pdo, (int) ($find->fetchColumn() ?: 0), $manager)) {
            flash('error', 'Você não tem permissão para excluir esta aula.');
            redirect($returnPath);
        }
        $stmt = $pdo->prepare('DELETE FROM lessons WHERE id = :id');
        $stmt->execute([':id' => $id]);
        flash('success', 'Aula excluída.');
        redirect($returnPath);
    }
}

if ($isAdmin) {
    $modules = $pdo->query('SELECT id, titulo FROM modules ORDER BY ordem, id')->fetchAll();
    $lessons = $pdo->query(
        'SELECT l.id, l.module_id, l.titulo, l.descricao, l.video_url, l.ordem, m.titulo AS modulo_titulo
         FROM lessons l INNER JOIN modules m ON m.id = l.module_id ORDER BY m.ordem, l.ordem, l.id'
    )->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT m.id, m.titulo FROM modules m INNER JOIN module_professors mp ON mp.module_id = m.id WHERE mp.user_id = :id ORDER BY m.ordem, m.id');
    $stmt->execute([':id' => $managerId]);
    $modules = $stmt->fetchAll();
    $stmt = $pdo->prepare(
        'SELECT l.id, l.module_id, l.titulo, l.descricao, l.video_url, l.ordem, m.titulo AS modulo_titulo
         FROM lessons l INNER JOIN modules m ON m.id = l.module_id
         INNER JOIN module_professors mp ON mp.module_id = m.id
         WHERE mp.user_id = :id ORDER BY m.ordem, l.ordem, l.id'
    );
    $stmt->execute([':id' => $managerId]);
    $lessons = $stmt->fetchAll();
}

$active_page = 'aulas';
$page_title = 'Aulas';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <section class="page-heading"><div><span class="eyebrow">Gestão pedagógica</span><h1>Aulas</h1><p>Publique vídeos do YouTube e organize a sequência de estudo.</p></div></section>
        <section class="panel">
            <div class="panel-header"><h2>Nova aula</h2></div>
            <?php if (!$modules): ?><p>Crie ou atribua um módulo antes de publicar aulas.</p><?php else: ?>
            <form method="post" class="form-grid form-grid-columns">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
                <label>Módulo<select name="module_id" required><option value="">Selecione</option><?php foreach ($modules as $module): ?><option value="<?= (int) $module['id'] ?>"><?= e($module['titulo']) ?></option><?php endforeach; ?></select></label>
                <label>Ordem<input type="number" name="ordem" value="1" min="0"></label>
                <label class="field-wide">Título<input type="text" name="titulo" maxlength="180" required></label>
                <label class="field-wide">Descrição<textarea name="descricao" rows="3"></textarea></label>
                <label class="field-wide">URL do YouTube<input type="url" name="video_url" placeholder="https://www.youtube.com/watch?v=..." required></label>
                <div class="field-wide"><button class="btn btn-primary" type="submit">Publicar aula</button></div>
            </form><?php endif; ?>
        </section>
        <section class="panel">
            <div class="panel-header"><h2>Aulas publicadas</h2><span class="badge badge-neutral"><?= count($lessons) ?> itens</span></div>
            <div class="management-list">
            <?php if (!$lessons): ?><p>Nenhuma aula cadastrada.</p><?php endif; ?>
            <?php foreach ($lessons as $lesson): ?>
                <article class="management-card">
                    <form method="post" class="management-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>">
                        <div class="management-card-head"><span class="badge badge-neutral"><?= e($lesson['modulo_titulo']) ?></span><span>Ordem <?= (int) $lesson['ordem'] ?></span></div>
                        <label>Título<input type="text" name="titulo" value="<?= e($lesson['titulo']) ?>" required></label>
                        <label>Descrição<textarea name="descricao" rows="2"><?= e($lesson['descricao']) ?></textarea></label>
                        <label>URL do YouTube<input type="url" name="video_url" value="<?= e($lesson['video_url']) ?>" required></label>
                        <div class="inline-form wrap"><label>Módulo<select name="module_id"><?php foreach ($modules as $module): ?><option value="<?= (int) $module['id'] ?>" <?= (int) $lesson['module_id'] === (int) $module['id'] ? 'selected' : '' ?>><?= e($module['titulo']) ?></option><?php endforeach; ?></select></label><label>Ordem<input type="number" name="ordem" value="<?= (int) $lesson['ordem'] ?>" min="0"></label></div>
                        <button class="btn btn-primary" type="submit">Salvar alterações</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Excluir esta aula?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $lesson['id'] ?>"><button class="btn btn-danger btn-subtle" type="submit">Excluir aula</button></form>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
