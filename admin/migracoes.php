<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

const PROFESSIONAL_SCHEMA_VERSION = '2026-07-quiz-respostas-abertas';

function db_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = :table_name AND index_name = :index_name'
    );
    $stmt->execute([':table_name' => $table, ':index_name' => $index]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function db_constraint_exists(PDO $pdo, string $constraint): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.referential_constraints
         WHERE constraint_schema = DATABASE() AND constraint_name = :constraint_name'
    );
    $stmt->execute([':constraint_name' => $constraint]);
    return ((int) $stmt->fetchColumn()) > 0;
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        versao VARCHAR(100) PRIMARY KEY,
        executado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);

    try {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','professor','aluno') NOT NULL DEFAULT 'aluno'");

        if (!db_column_exists($pdo, 'modules', 'professor_id')) {
            $pdo->exec('ALTER TABLE modules ADD COLUMN professor_id INT UNSIGNED NULL AFTER ordem');
        }
        if (!db_index_exists($pdo, 'modules', 'idx_modules_professor')) {
            $pdo->exec('ALTER TABLE modules ADD INDEX idx_modules_professor (professor_id)');
        }
        if (!db_constraint_exists($pdo, 'fk_modules_professor')) {
            $pdo->exec(
                'ALTER TABLE modules ADD CONSTRAINT fk_modules_professor
                 FOREIGN KEY (professor_id) REFERENCES users(id)
                 ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }

        if (!db_column_exists($pdo, 'materials', 'module_id')) {
            $pdo->exec('ALTER TABLE materials ADD COLUMN module_id INT UNSIGNED NULL AFTER id');
        }
        $pdo->exec('ALTER TABLE materials MODIFY lesson_id INT UNSIGNED NULL');
        if (!db_index_exists($pdo, 'materials', 'idx_materials_module')) {
            $pdo->exec('ALTER TABLE materials ADD INDEX idx_materials_module (module_id)');
        }
        if (!db_constraint_exists($pdo, 'fk_materials_module')) {
            $pdo->exec(
                'ALTER TABLE materials ADD CONSTRAINT fk_materials_module
                 FOREIGN KEY (module_id) REFERENCES modules(id)
                 ON DELETE CASCADE ON UPDATE CASCADE'
            );
        }

        if (!db_column_exists($pdo, 'quiz_questions', 'tipo')) {
            $pdo->exec("ALTER TABLE quiz_questions ADD COLUMN tipo ENUM('multipla_escolha','texto') NOT NULL DEFAULT 'multipla_escolha' AFTER pergunta");
        }

        $stmt = $pdo->prepare(
            'INSERT INTO schema_migrations (versao, executado_em) VALUES (:versao, NOW())
             ON DUPLICATE KEY UPDATE executado_em = VALUES(executado_em)'
        );
        $stmt->execute([':versao' => PROFESSIONAL_SCHEMA_VERSION]);

        flash('success', 'Banco atualizado. Dados existentes foram preservados.');
    } catch (Throwable $exception) {
        error_log('Falha na migração FL360: ' . $exception->getMessage());
        flash('error', 'Não foi possível atualizar o banco. Consulte o error_log antes de tentar novamente.');
    }

    redirect('admin/migracoes.php');
}

$ready = db_column_exists($pdo, 'modules', 'professor_id')
    && db_column_exists($pdo, 'materials', 'module_id')
    && db_table_exists($pdo, 'module_professors')
    && db_column_exists($pdo, 'users', 'status')
    && db_column_exists($pdo, 'quizzes', 'liberacao')
    && db_column_exists($pdo, 'quiz_questions', 'tipo')
    && db_column_exists($pdo, 'forum_topics', 'fixado');

$active_page = 'migracoes';
$page_title = 'Atualização do sistema';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="content-area">
        <section class="panel panel-narrow">
            <span class="eyebrow">Manutenção segura</span>
            <h1>Atualização do banco</h1>
            <p>Esta atualização preserva os dados existentes e adiciona suporte seguro a perguntas abertas nos quizzes.</p>
            <div class="migration-status <?= $ready ? 'is-ready' : 'is-pending' ?>">
                <strong><?= $ready ? 'Banco atualizado' : 'Atualização pendente' ?></strong>
                <span><?= $ready ? 'A estrutura profissional já está disponível.' : 'Faça um backup no cPanel antes de continuar.' ?></span>
            </div>
            <?php if (!$ready): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-primary" type="submit">Executar atualização</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
