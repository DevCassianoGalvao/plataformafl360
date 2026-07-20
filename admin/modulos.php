<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_content_manager();

$manager = current_user($pdo);
$managerId = (int) $manager['id'];
$isAdmin = $manager['role'] === 'admin';
$returnPath = content_manager_path('modulos.php');

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if (in_array($action, ['update', 'delete'], true) && !can_manage_module($pdo, $id, $manager)) {
        flash('error', 'Você não tem permissão para alterar este módulo.');
        redirect($returnPath);
    }

    if ($action === 'create' || $action === 'update') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $ordem = max(0, (int) ($_POST['ordem'] ?? 0));
        $professorIds = $isAdmin ? array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['professor_ids'] ?? []))))) : [$managerId];

        if ($titulo === '') {
            flash('error', 'Informe o título do módulo.');
            redirect($returnPath);
        }

        $pdo->beginTransaction();
        try {
            $legacyProfessorId = $professorIds[0] ?? null;
            if ($action === 'create') {
                $stmt = $pdo->prepare('INSERT INTO modules (titulo, descricao, ordem, professor_id) VALUES (:titulo, :descricao, :ordem, :professor_id)');
                $stmt->execute([':titulo' => $titulo, ':descricao' => $descricao, ':ordem' => $ordem, ':professor_id' => $legacyProfessorId]);
                $id = (int) $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare('UPDATE modules SET titulo = :titulo, descricao = :descricao, ordem = :ordem, professor_id = :professor_id WHERE id = :id');
                $stmt->execute([':titulo' => $titulo, ':descricao' => $descricao, ':ordem' => $ordem, ':professor_id' => $legacyProfessorId, ':id' => $id]);
            }

            if ($isAdmin || $action === 'create') {
                $pdo->prepare('DELETE FROM module_professors WHERE module_id = :module_id')->execute([':module_id' => $id]);
                $assign = $pdo->prepare('INSERT INTO module_professors (module_id, user_id, assigned_by) VALUES (:module_id, :user_id, :assigned_by)');
                foreach ($professorIds as $professorId) {
                    $assign->execute([':module_id' => $id, ':user_id' => $professorId, ':assigned_by' => $managerId]);
                }
            }
            $pdo->commit();

            if ($action === 'create') {
                create_notification($pdo, 'modulo', 'Novo módulo disponível', 'O módulo "' . $titulo . '" foi publicado.', 'pages/modulo.php?id=' . $id);
            }
            flash('success', $action === 'create' ? 'Módulo criado com sucesso.' : 'Módulo atualizado.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            flash('error', 'Não foi possível salvar o módulo.');
        }
        redirect($returnPath);
    }

    if ($action === 'delete') {
        $videoStmt = $pdo->prepare('SELECT video_url FROM lessons WHERE module_id = :module_id');
        $videoStmt->execute([':module_id' => $id]);
        $moduleVideos = $videoStmt->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare('DELETE FROM modules WHERE id = :id')->execute([':id' => $id]);
        foreach ($moduleVideos as $moduleVideo) {
            delete_local_lesson_video((string) $moduleVideo);
        }
        flash('success', 'Módulo excluído.');
        redirect($returnPath);
    }
}

$professors = $pdo->query("SELECT id, nome FROM users WHERE role = 'professor' AND status = 'ativo' ORDER BY nome")->fetchAll();
$sql = 'SELECT m.id, m.titulo, m.descricao, m.ordem,
               COUNT(DISTINCT l.id) AS total_aulas,
               GROUP_CONCAT(DISTINCT u.nome ORDER BY u.nome SEPARATOR \'||\') AS professores,
               GROUP_CONCAT(DISTINCT mp.user_id ORDER BY mp.user_id) AS professor_ids
        FROM modules m
        LEFT JOIN module_professors mp ON mp.module_id = m.id
        LEFT JOIN users u ON u.id = mp.user_id
        LEFT JOIN lessons l ON l.module_id = m.id';
$params = [];
if (!$isAdmin) {
    $sql .= ' INNER JOIN module_professors access_mp ON access_mp.module_id = m.id AND access_mp.user_id = :manager_id';
    $params[':manager_id'] = $managerId;
}
$sql .= ' GROUP BY m.id, m.titulo, m.descricao, m.ordem ORDER BY m.ordem, m.id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$modules = $stmt->fetchAll();

$active_page = 'modulos';
$page_title = 'Módulos';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <section class="page-heading"><div><span class="eyebrow">Gestão pedagógica</span><h1>Módulos</h1><p>Organize a jornada e atribua professores colaboradores.</p></div></section>
        <details class="panel disclosure-panel" open>
            <summary><span><strong>Novo módulo</strong><small>Crie uma etapa da trilha de aprendizagem.</small></span><span class="disclosure-icon">+</span></summary>
            <form method="post" class="form-grid form-grid-columns disclosure-content">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
                <label>Título<input type="text" name="titulo" maxlength="180" required></label><label>Ordem<input type="number" name="ordem" value="1" min="0"></label>
                <label class="field-wide">Descrição<textarea name="descricao" rows="3"></textarea></label>
                <?php if ($isAdmin): ?><fieldset class="field-wide collaborator-picker"><legend>Professores colaboradores</legend><p>Selecione todos que poderão editar o módulo e seus conteúdos.</p><div><?php foreach ($professors as $professor): ?><label><input type="checkbox" name="professor_ids[]" value="<?= (int) $professor['id'] ?>"> <span><?= e($professor['nome']) ?></span></label><?php endforeach; ?></div></fieldset><?php endif; ?>
                <div class="field-wide"><button class="btn btn-primary">Criar módulo</button></div>
            </form>
        </details>
        <section class="panel"><div class="panel-header"><h2>Módulos publicados</h2><span class="badge badge-neutral"><?= count($modules) ?> itens</span></div><div class="management-list">
            <?php if (!$modules): ?><div class="empty-state"><strong>Nenhum módulo encontrado</strong><p>Crie o primeiro módulo para começar.</p></div><?php endif; ?>
            <?php foreach ($modules as $module): $selectedIds = array_filter(array_map('intval', explode(',', (string) $module['professor_ids']))); ?>
                <article class="management-card"><form method="post" class="management-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $module['id'] ?>">
                    <div class="management-card-head"><div><span class="badge badge-neutral"><?= (int) $module['total_aulas'] ?> aulas</span><h3><?= e($module['titulo']) ?></h3><small><?= $module['professores'] ? e(str_replace('||', ', ', $module['professores'])) : 'Sem professor atribuído' ?></small></div></div>
                    <div class="form-grid form-grid-columns"><label>Título<input name="titulo" value="<?= e($module['titulo']) ?>" required></label><label>Ordem<input type="number" name="ordem" value="<?= (int) $module['ordem'] ?>" min="0"></label><label class="field-wide">Descrição<textarea name="descricao" rows="2"><?= e($module['descricao']) ?></textarea></label>
                    <?php if ($isAdmin): ?><fieldset class="field-wide collaborator-picker"><legend>Professores colaboradores</legend><div><?php foreach ($professors as $professor): ?><label><input type="checkbox" name="professor_ids[]" value="<?= (int) $professor['id'] ?>" <?= in_array((int) $professor['id'], $selectedIds, true) ? 'checked' : '' ?>> <span><?= e($professor['nome']) ?></span></label><?php endforeach; ?></div></fieldset><?php endif; ?></div>
                    <div class="management-actions"><button class="btn btn-primary">Salvar alterações</button></div>
                </form><form method="post" onsubmit="return confirm('Excluir este módulo, aulas, materiais e quizzes relacionados?');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $module['id'] ?>"><button class="btn btn-danger">Excluir módulo</button></form></article>
            <?php endforeach; ?>
        </div></section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
