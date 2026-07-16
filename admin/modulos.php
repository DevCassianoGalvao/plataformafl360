<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_content_manager();

$manager = current_user($pdo);
$managerId = (int) $manager['id'];
$isAdmin = $manager['role'] === 'admin';
$returnPath = content_manager_path('modulos.php');
$schemaReady = db_column_exists($pdo, 'modules', 'professor_id');

if (!$schemaReady) {
    flash('error', 'Execute a atualização do banco antes de gerenciar professores.');
    redirect($isAdmin ? 'admin/migracoes.php' : 'professor/dashboard.php');
}

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
        $professorId = $isAdmin ? (int) ($_POST['professor_id'] ?? 0) : $managerId;
        $professorId = $professorId > 0 ? $professorId : null;

        if ($titulo === '') {
            flash('error', 'Informe o título do módulo.');
            redirect($returnPath);
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO modules (titulo, descricao, ordem, professor_id)
                 VALUES (:titulo, :descricao, :ordem, :professor_id)'
            );
            $stmt->execute([':titulo' => $titulo, ':descricao' => $descricao, ':ordem' => $ordem, ':professor_id' => $professorId]);
            create_notification($pdo, 'modulo', 'Novo módulo disponível', 'O módulo "' . $titulo . '" foi publicado.', 'pages/modulos.php');
            flash('success', 'Módulo criado com sucesso.');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE modules SET titulo = :titulo, descricao = :descricao, ordem = :ordem, professor_id = :professor_id
                 WHERE id = :id'
            );
            $stmt->execute([':titulo' => $titulo, ':descricao' => $descricao, ':ordem' => $ordem, ':professor_id' => $professorId, ':id' => $id]);
            flash('success', 'Módulo atualizado.');
        }
        redirect($returnPath);
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM modules WHERE id = :id');
        $stmt->execute([':id' => $id]);
        flash('success', 'Módulo excluído.');
        redirect($returnPath);
    }
}

$professors = $isAdmin
    ? $pdo->query("SELECT id, nome FROM users WHERE role = 'professor' ORDER BY nome")->fetchAll()
    : [];

if ($isAdmin) {
    $modules = $pdo->query(
        'SELECT m.id, m.titulo, m.descricao, m.ordem, m.professor_id, u.nome AS professor_nome,
                (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id) AS total_aulas
         FROM modules m LEFT JOIN users u ON u.id = m.professor_id
         ORDER BY m.ordem, m.id'
    )->fetchAll();
} else {
    $stmt = $pdo->prepare(
        'SELECT m.id, m.titulo, m.descricao, m.ordem, m.professor_id, u.nome AS professor_nome,
                (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id) AS total_aulas
         FROM modules m LEFT JOIN users u ON u.id = m.professor_id
         WHERE m.professor_id = :professor_id ORDER BY m.ordem, m.id'
    );
    $stmt->execute([':professor_id' => $managerId]);
    $modules = $stmt->fetchAll();
}

$active_page = 'modulos';
$page_title = 'Módulos';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <section class="page-heading">
            <div><span class="eyebrow">Gestão pedagógica</span><h1>Módulos</h1><p>Organize a jornada de aprendizagem em etapas claras.</p></div>
        </section>
        <section class="panel">
            <div class="panel-header"><h2>Novo módulo</h2></div>
            <form method="post" class="form-grid form-grid-columns">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                <label>Título<input type="text" name="titulo" maxlength="180" required></label>
                <label>Ordem<input type="number" name="ordem" value="1" min="0"></label>
                <?php if ($isAdmin): ?>
                    <label>Professor responsável<select name="professor_id"><option value="">Sem atribuição</option><?php foreach ($professors as $professor): ?><option value="<?= (int) $professor['id'] ?>"><?= e($professor['nome']) ?></option><?php endforeach; ?></select></label>
                <?php endif; ?>
                <label class="field-wide">Descrição<textarea name="descricao" rows="3"></textarea></label>
                <div class="field-wide"><button class="btn btn-primary" type="submit">Criar módulo</button></div>
            </form>
        </section>
        <section class="panel">
            <div class="panel-header"><h2>Módulos publicados</h2><span class="badge badge-neutral"><?= count($modules) ?> itens</span></div>
            <div class="management-list">
                <?php if (!$modules): ?><p>Nenhum módulo sob sua responsabilidade.</p><?php endif; ?>
                <?php foreach ($modules as $module): ?>
                    <article class="management-card">
                        <form method="post" class="management-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $module['id'] ?>">
                            <div class="management-card-head"><span class="badge badge-neutral"><?= (int) $module['total_aulas'] ?> aulas</span><span>Ordem <?= (int) $module['ordem'] ?></span></div>
                            <label>Título<input type="text" name="titulo" value="<?= e($module['titulo']) ?>" required></label>
                            <label>Descrição<textarea name="descricao" rows="2"><?= e($module['descricao']) ?></textarea></label>
                            <div class="inline-form wrap"><label>Ordem<input type="number" name="ordem" value="<?= (int) $module['ordem'] ?>" min="0"></label>
                            <?php if ($isAdmin): ?><label>Responsável<select name="professor_id"><option value="">Sem atribuição</option><?php foreach ($professors as $professor): ?><option value="<?= (int) $professor['id'] ?>" <?= (int) $module['professor_id'] === (int) $professor['id'] ? 'selected' : '' ?>><?= e($professor['nome']) ?></option><?php endforeach; ?></select></label><?php endif; ?></div>
                            <button class="btn btn-primary" type="submit">Salvar alterações</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Excluir este módulo e todo conteúdo relacionado?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $module['id'] ?>">
                            <button class="btn btn-danger btn-subtle" type="submit">Excluir módulo</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
