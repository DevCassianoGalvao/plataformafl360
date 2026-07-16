<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_student();

$materialSql = db_column_exists($pdo, 'materials', 'module_id')
    ? 'SELECT mat.id, mat.titulo, mat.arquivo, l.titulo AS aula_titulo, mo.titulo AS modulo_titulo,
              CASE WHEN mat.lesson_id IS NULL THEN 0 ELSE 1 END AS especifico_aula
       FROM materials mat LEFT JOIN lessons l ON l.id = mat.lesson_id
       INNER JOIN modules mo ON mo.id = COALESCE(mat.module_id, l.module_id)
       ORDER BY mo.ordem ASC, l.ordem ASC, mat.id DESC'
    : 'SELECT mat.id, mat.titulo, mat.arquivo, l.titulo AS aula_titulo, mo.titulo AS modulo_titulo,
              1 AS especifico_aula
       FROM materials mat INNER JOIN lessons l ON l.id = mat.lesson_id
       INNER JOIN modules mo ON mo.id = l.module_id
       ORDER BY mo.ordem ASC, l.ordem ASC, mat.id DESC';
$stmt = $pdo->query($materialSql);
$materials = $stmt->fetchAll();

$active_page = 'materiais';
$page_title = 'Biblioteca de Materiais';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area">
        <section class="panel">
            <div class="panel-header">
                <h1>Biblioteca de materiais</h1>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Material</th>
                        <th>Módulo</th>
                        <th>Aula</th>
                        <th>Arquivo</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$materials): ?>
                        <tr>
                            <td colspan="4">Nenhum material disponível.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($materials as $material): ?>
                            <tr>
                                <td><?= e($material['titulo']) ?></td>
                                <td><?= e($material['modulo_titulo']) ?></td>
                                <td><?= (int) $material['especifico_aula'] === 1 ? 'Aula: ' . e($material['aula_titulo']) : 'Módulo inteiro' ?></td>
                                <td>
                                    <a class="btn btn-ghost" href="<?= e(url('pages/download.php?id=' . (int) $material['id'])) ?>">
                                        Baixar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
