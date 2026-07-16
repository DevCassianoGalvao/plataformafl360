<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_professor();

$user = current_user($pdo);
$userId = (int) $user['id'];
$schemaReady = db_table_exists($pdo, 'module_professors')
    && db_column_exists($pdo, 'materials', 'module_id');

$moduleCount = 0;
$lessonCount = 0;
$materialCount = 0;
$quizCount = 0;

if ($schemaReady) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM module_professors WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $moduleCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM lessons l INNER JOIN module_professors mp ON mp.module_id = l.module_id WHERE mp.user_id = :id');
    $stmt->execute([':id' => $userId]);
    $lessonCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM materials mat
         LEFT JOIN lessons l ON l.id = mat.lesson_id
         INNER JOIN module_professors mp ON mp.module_id = COALESCE(mat.module_id, l.module_id)
         WHERE mp.user_id = :id'
    );
    $stmt->execute([':id' => $userId]);
    $materialCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM quizzes q INNER JOIN module_professors mp ON mp.module_id = q.module_id WHERE mp.user_id = :id');
    $stmt->execute([':id' => $userId]);
    $quizCount = (int) $stmt->fetchColumn();
}

$active_page = 'dashboard';
$page_title = 'Painel do Professor';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <section class="hero-card dashboard-hero">
            <div>
                <span class="eyebrow">Área pedagógica</span>
                <h1>Olá, <?= e($user['nome']) ?>.</h1>
                <p>Organize sua trilha, publique aulas e acompanhe o conteúdo sob sua responsabilidade.</p>
            </div>
            <a class="btn btn-primary" href="<?= e(url('professor/aulas.php')) ?>">Criar nova aula</a>
        </section>

        <?php if (!$schemaReady): ?>
            <section class="panel"><p>A estrutura de professores ainda não foi ativada. Peça ao administrador para executar a atualização do banco.</p></section>
        <?php else: ?>
            <section class="stats-grid stats-grid-compact">
                <article class="stat-card"><span class="stat-icon">M</span><h3>Módulos</h3><strong><?= $moduleCount ?></strong></article>
                <article class="stat-card"><span class="stat-icon">A</span><h3>Aulas</h3><strong><?= $lessonCount ?></strong></article>
                <article class="stat-card"><span class="stat-icon">D</span><h3>Materiais</h3><strong><?= $materialCount ?></strong></article>
                <article class="stat-card"><span class="stat-icon">Q</span><h3>Quizzes</h3><strong><?= $quizCount ?></strong></article>
            </section>
            <section class="panel">
                <div class="panel-header"><div><span class="eyebrow">Atalhos</span><h2>Gestão de conteúdo</h2></div></div>
                <div class="quick-grid">
                    <a href="<?= e(url('professor/modulos.php')) ?>"><strong>Módulos</strong><span>Estruture etapas da formação.</span></a>
                    <a href="<?= e(url('professor/aulas.php')) ?>"><strong>Aulas</strong><span>Publique vídeos e descrições.</span></a>
                    <a href="<?= e(url('professor/materiais.php')) ?>"><strong>Materiais</strong><span>Anexe arquivos a módulos ou aulas.</span></a>
                    <a href="<?= e(url('professor/quiz.php')) ?>"><strong>Quizzes</strong><span>Crie avaliações por módulo.</span></a>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
