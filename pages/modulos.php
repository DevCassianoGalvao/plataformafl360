<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_student();

$user = current_user($pdo);
$userId = (int) $user['id'];
$modules = $pdo->query(
    'SELECT m.id, m.titulo, m.descricao, m.ordem,
            COUNT(DISTINCT l.id) AS total_aulas,
            GROUP_CONCAT(DISTINCT u.nome ORDER BY u.nome SEPARATOR \'||\') AS professores
     FROM modules m
     LEFT JOIN lessons l ON l.module_id = m.id
     LEFT JOIN module_professors mp ON mp.module_id = m.id
     LEFT JOIN users u ON u.id = mp.user_id
     GROUP BY m.id, m.titulo, m.descricao, m.ordem
     ORDER BY m.ordem, m.id'
)->fetchAll();

$active_page = 'modulos';
$page_title = 'Módulos do curso';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <section class="page-heading"><div><span class="eyebrow">Sua trilha</span><h1>Módulos do curso</h1><p>Escolha um módulo para acessar aulas, materiais e avaliação.</p></div></section>
        <?php if (!$modules): ?><section class="panel empty-state"><strong>Nenhum módulo disponível</strong><p>Novos conteúdos aparecerão aqui quando forem publicados.</p></section><?php endif; ?>
        <section class="student-module-grid">
            <?php foreach ($modules as $index => $module): $progress = module_progress_percent($pdo, $userId, (int) $module['id']); ?>
                <a class="student-module-card" href="<?= e(url('pages/modulo.php?id=' . (int) $module['id'])) ?>">
                    <div class="module-card-index"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></div>
                    <div class="student-module-body"><span class="eyebrow"><?= (int) $module['total_aulas'] ?> aula<?= (int) $module['total_aulas'] === 1 ? '' : 's' ?></span><h2><?= e($module['titulo']) ?></h2><p><?= e(mb_strimwidth((string) $module['descricao'], 0, 150, '...')) ?></p>
                        <div class="module-teachers"><span>Com</span><strong><?= $module['professores'] ? e(str_replace('||', ', ', $module['professores'])) : 'Equipe FL360' ?></strong></div>
                    </div>
                    <div class="student-module-progress"><strong><?= $progress ?>%</strong><div class="progress-bar"><span style="width: <?= $progress ?>%"></span></div><span>Abrir módulo →</span></div>
                </a>
            <?php endforeach; ?>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
