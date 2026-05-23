<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'aluno') {
    redirect('admin/dashboard.php');
}

$stmt = $pdo->query(
    'SELECT m.id, m.titulo, m.arquivo, l.titulo AS aula_titulo, mo.titulo AS modulo_titulo
     FROM materials m
     INNER JOIN lessons l ON l.id = m.lesson_id
     INNER JOIN modules mo ON mo.id = l.module_id
     ORDER BY mo.ordem ASC, l.ordem ASC, m.id DESC'
);
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
                                <td><?= e($material['aula_titulo']) ?></td>
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
