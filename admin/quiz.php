<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$moduleId      = isset($_GET['module_id'])    ? (int) $_GET['module_id']    : 0;
$editQuestionId = isset($_GET['edit_question']) ? (int) $_GET['edit_question'] : 0;

// ─── POST ─────────────────────────────────────────────────────────────────────
if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_quiz') {
        $mId      = (int)    ($_POST['module_id'] ?? 0);
        $titulo   = trim((string) ($_POST['titulo']   ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));

        if ($mId <= 0 || $titulo === '') {
            flash('error', 'Título do quiz é obrigatório.');
            redirect('admin/quiz.php?module_id=' . $mId);
        }

        $pdo->prepare('INSERT INTO quizzes (module_id, titulo, descricao) VALUES (:m, :t, :d)')
            ->execute([':m' => $mId, ':t' => $titulo, ':d' => $descricao]);

        flash('success', 'Quiz criado com sucesso.');
        redirect('admin/quiz.php?module_id=' . $mId);
    }

    if ($action === 'update_quiz') {
        $quizId   = (int)    ($_POST['quiz_id']   ?? 0);
        $titulo   = trim((string) ($_POST['titulo']   ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));

        $stmt = $pdo->prepare('SELECT module_id FROM quizzes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $quizId]);
        $mId = (int) ($stmt->fetchColumn() ?: 0);

        if ($quizId <= 0 || $titulo === '' || $mId <= 0) {
            flash('error', 'Dados inválidos.');
            redirect('admin/quiz.php');
        }

        $pdo->prepare('UPDATE quizzes SET titulo = :t, descricao = :d WHERE id = :id')
            ->execute([':t' => $titulo, ':d' => $descricao, ':id' => $quizId]);

        flash('success', 'Quiz atualizado.');
        redirect('admin/quiz.php?module_id=' . $mId);
    }

    if ($action === 'delete_quiz') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $pdo->prepare('DELETE FROM quizzes WHERE id = :id')->execute([':id' => $quizId]);
        flash('success', 'Quiz excluído.');
        redirect('admin/quiz.php');
    }

    if ($action === 'save_question') {
        $quizId     = (int)    ($_POST['quiz_id']     ?? 0);
        $questionId = (int)    ($_POST['question_id'] ?? 0);
        $pergunta   = trim((string) ($_POST['pergunta']   ?? ''));
        $correta    = (string) ($_POST['correta']    ?? 'a');
        $opts = [
            'a' => trim((string) ($_POST['opt_a'] ?? '')),
            'b' => trim((string) ($_POST['opt_b'] ?? '')),
            'c' => trim((string) ($_POST['opt_c'] ?? '')),
            'd' => trim((string) ($_POST['opt_d'] ?? '')),
        ];

        $stmt = $pdo->prepare('SELECT module_id FROM quizzes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $quizId]);
        $mId = (int) ($stmt->fetchColumn() ?: 0);

        if ($pergunta === '' || in_array('', $opts, true) || !in_array($correta, ['a','b','c','d'], true)) {
            flash('error', 'Preencha a pergunta, todas as 4 opções e marque a correta.');
            redirect('admin/quiz.php?module_id=' . $mId);
        }

        $pdo->beginTransaction();
        try {
            if ($questionId > 0) {
                $pdo->prepare('UPDATE quiz_questions SET pergunta = :p WHERE id = :id AND quiz_id = :qid')
                    ->execute([':p' => $pergunta, ':id' => $questionId, ':qid' => $quizId]);
                $pdo->prepare('DELETE FROM quiz_options WHERE question_id = :qid')
                    ->execute([':qid' => $questionId]);
            } else {
                $stmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM quiz_questions WHERE quiz_id = :qid');
                $stmt->execute([':qid' => $quizId]);
                $ordem = ((int) $stmt->fetchColumn()) + 1;

                $pdo->prepare('INSERT INTO quiz_questions (quiz_id, pergunta, ordem) VALUES (:qid, :p, :o)')
                    ->execute([':qid' => $quizId, ':p' => $pergunta, ':o' => $ordem]);
                $questionId = (int) $pdo->lastInsertId();
            }

            foreach (['a', 'b', 'c', 'd'] as $letter) {
                $pdo->prepare('INSERT INTO quiz_options (question_id, texto, correta) VALUES (:qid, :t, :c)')
                    ->execute([':qid' => $questionId, ':t' => $opts[$letter], ':c' => ($correta === $letter ? 1 : 0)]);
            }

            $pdo->commit();
            flash('success', 'Pergunta salva.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'Erro ao salvar pergunta.');
        }

        redirect('admin/quiz.php?module_id=' . $mId);
    }

    if ($action === 'delete_question') {
        $questionId = (int) ($_POST['question_id'] ?? 0);
        $quizId     = (int) ($_POST['quiz_id']     ?? 0);

        $stmt = $pdo->prepare('SELECT module_id FROM quizzes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $quizId]);
        $mId = (int) ($stmt->fetchColumn() ?: 0);

        $pdo->prepare('DELETE FROM quiz_questions WHERE id = :id AND quiz_id = :qid')
            ->execute([':id' => $questionId, ':qid' => $quizId]);

        flash('success', 'Pergunta excluída.');
        redirect('admin/quiz.php?module_id=' . $mId);
    }
}

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($moduleId > 0) {
    // ── Module quiz management ────────────────────────────────────────────────
    $moduleStmt = $pdo->prepare('SELECT id, titulo FROM modules WHERE id = :id LIMIT 1');
    $moduleStmt->execute([':id' => $moduleId]);
    $module = $moduleStmt->fetch();

    if (!$module) {
        flash('error', 'Módulo não encontrado.');
        redirect('admin/quiz.php');
    }

    $quizStmt = $pdo->prepare('SELECT id, titulo, descricao FROM quizzes WHERE module_id = :mid LIMIT 1');
    $quizStmt->execute([':mid' => $moduleId]);
    $quiz = $quizStmt->fetch() ?: null;

    $questions   = [];
    $attempts    = [];
    $editQuestion = null;

    if ($quiz) {
        $quizId = (int) $quiz['id'];

        $qsStmt = $pdo->prepare(
            'SELECT id, pergunta, ordem FROM quiz_questions WHERE quiz_id = :qid ORDER BY ordem ASC, id ASC'
        );
        $qsStmt->execute([':qid' => $quizId]);
        $questions = $qsStmt->fetchAll();

        foreach ($questions as &$question) {
            $opStmt = $pdo->prepare(
                'SELECT id, texto, correta FROM quiz_options WHERE question_id = :qid ORDER BY id ASC'
            );
            $opStmt->execute([':qid' => (int) $question['id']]);
            $question['options'] = $opStmt->fetchAll();
        }
        unset($question);

        $attStmt = $pdo->prepare(
            'SELECT u.nome, u.email, qa.acertos, qa.total, qa.nota, qa.feito_em
             FROM quiz_attempts qa
             INNER JOIN users u ON u.id = qa.user_id
             WHERE qa.quiz_id = :qid
             ORDER BY qa.feito_em DESC'
        );
        $attStmt->execute([':qid' => $quizId]);
        $attempts = $attStmt->fetchAll();

        if ($editQuestionId > 0) {
            $eqStmt = $pdo->prepare(
                'SELECT id, pergunta FROM quiz_questions WHERE id = :id AND quiz_id = :qid LIMIT 1'
            );
            $eqStmt->execute([':id' => $editQuestionId, ':qid' => $quizId]);
            $editQuestion = $eqStmt->fetch() ?: null;

            if ($editQuestion) {
                $eopStmt = $pdo->prepare(
                    'SELECT id, texto, correta FROM quiz_options WHERE question_id = :qid ORDER BY id ASC'
                );
                $eopStmt->execute([':qid' => $editQuestionId]);
                $editQuestion['options'] = $eopStmt->fetchAll();
            }
        }
    }

    $active_page = 'quiz';
    $page_title  = 'Quiz — ' . e($module['titulo']);
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="app-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="content-area">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h1><?= e($module['titulo']) ?></h1>
                        <p>Gerenciamento do quiz deste módulo</p>
                    </div>
                    <a class="btn btn-ghost" href="<?= e(url('admin/quiz.php')) ?>">← Todos os módulos</a>
                </div>
            </section>

            <?php if (!$quiz): ?>
                <!-- ── Criar quiz ─────────────────────────────────────────── -->
                <section class="panel">
                    <div class="panel-header"><h2>Criar quiz para este módulo</h2></div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="create_quiz">
                        <input type="hidden" name="module_id" value="<?= e((string) $moduleId) ?>">

                        <label for="titulo">Título do quiz</label>
                        <input id="titulo" type="text" name="titulo" required>

                        <label for="descricao">Descrição (opcional)</label>
                        <textarea id="descricao" name="descricao" rows="2"></textarea>

                        <button type="submit" class="btn btn-primary">Criar quiz</button>
                    </form>
                </section>
            <?php else: ?>
                <!-- ── Editar quiz ────────────────────────────────────────── -->
                <section class="panel">
                    <div class="panel-header">
                        <h2>Dados do quiz</h2>
                        <form method="post" onsubmit="return confirm('Excluir quiz e todas as perguntas?');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_quiz">
                            <input type="hidden" name="quiz_id" value="<?= e((string) $quiz['id']) ?>">
                            <button type="submit" class="btn btn-danger">Excluir quiz</button>
                        </form>
                    </div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_quiz">
                        <input type="hidden" name="quiz_id" value="<?= e((string) $quiz['id']) ?>">

                        <label for="edit_titulo">Título</label>
                        <input id="edit_titulo" type="text" name="titulo" value="<?= e($quiz['titulo']) ?>" required>

                        <label for="edit_descricao">Descrição</label>
                        <textarea id="edit_descricao" name="descricao" rows="2"><?= e($quiz['descricao'] ?? '') ?></textarea>

                        <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    </form>
                </section>

                <!-- ── Perguntas existentes ───────────────────────────────── -->
                <section class="panel">
                    <div class="panel-header"><h2>Perguntas (<?= count($questions) ?>)</h2></div>

                    <?php if (!$questions): ?>
                        <p>Nenhuma pergunta cadastrada ainda.</p>
                    <?php else: ?>
                        <?php foreach ($questions as $i => $question): ?>
                            <div class="quiz-admin-question">
                                <div class="quiz-admin-question-head">
                                    <strong><?= ($i + 1) ?>. <?= e($question['pergunta']) ?></strong>
                                    <div class="inline-form">
                                        <a class="btn btn-ghost"
                                           href="<?= e(url('admin/quiz.php?module_id=' . $moduleId . '&edit_question=' . (int) $question['id'])) ?>">
                                            Editar
                                        </a>
                                        <form method="post" onsubmit="return confirm('Excluir esta pergunta?');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_question">
                                            <input type="hidden" name="question_id" value="<?= e((string) $question['id']) ?>">
                                            <input type="hidden" name="quiz_id" value="<?= e((string) $quiz['id']) ?>">
                                            <button type="submit" class="btn btn-danger">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                                <?php if ($question['options']): ?>
                                    <ul class="quiz-admin-options">
                                        <?php foreach ($question['options'] as $li => $opt): ?>
                                            <li class="<?= (int) $opt['correta'] === 1 ? 'quiz-admin-opt-correct' : '' ?>">
                                                <strong><?= chr(65 + $li) ?>.</strong>
                                                <?= e($opt['texto']) ?>
                                                <?php if ((int) $opt['correta'] === 1): ?>
                                                    <span class="badge badge-success">Correta</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- ── Adicionar / Editar pergunta ───────────────────────── -->
                <section class="panel">
                    <div class="panel-header">
                        <h2><?= $editQuestion ? 'Editar pergunta' : 'Nova pergunta' ?></h2>
                        <?php if ($editQuestion): ?>
                            <a class="btn btn-ghost" href="<?= e(url('admin/quiz.php?module_id=' . $moduleId)) ?>">Cancelar edição</a>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_question">
                        <input type="hidden" name="quiz_id" value="<?= e((string) $quiz['id']) ?>">
                        <input type="hidden" name="question_id" value="<?= $editQuestion ? e((string) $editQuestion['id']) : '0' ?>">

                        <label for="pergunta">Enunciado da pergunta</label>
                        <textarea id="pergunta" name="pergunta" rows="2" required><?= $editQuestion ? e($editQuestion['pergunta']) : '' ?></textarea>

                        <?php
                        $letters = ['a', 'b', 'c', 'd'];
                        $correctLetter = 'a';
                        $optTexts = ['a' => '', 'b' => '', 'c' => '', 'd' => ''];
                        if ($editQuestion && !empty($editQuestion['options'])) {
                            foreach ($editQuestion['options'] as $li => $opt) {
                                $letter = $letters[$li] ?? 'a';
                                $optTexts[$letter] = (string) $opt['texto'];
                                if ((int) $opt['correta'] === 1) {
                                    $correctLetter = $letter;
                                }
                            }
                        }
                        ?>

                        <?php foreach ($letters as $letter): ?>
                            <label for="opt_<?= $letter ?>">Opção <?= strtoupper($letter) ?></label>
                            <input id="opt_<?= $letter ?>" type="text" name="opt_<?= $letter ?>"
                                   value="<?= e($optTexts[$letter]) ?>" required>
                        <?php endforeach; ?>

                        <label>Opção correta</label>
                        <div class="inline-form wrap">
                            <?php foreach ($letters as $letter): ?>
                                <label class="radio-label">
                                    <input type="radio" name="correta" value="<?= $letter ?>"
                                        <?= $correctLetter === $letter ? 'checked' : '' ?> required>
                                    <?= strtoupper($letter) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <?= $editQuestion ? 'Salvar alterações' : 'Adicionar pergunta' ?>
                        </button>
                    </form>
                </section>

                <!-- ── Resultados dos alunos ──────────────────────────────── -->
                <section class="panel">
                    <div class="panel-header"><h2>Resultados dos alunos (<?= count($attempts) ?> tentativas)</h2></div>
                    <?php if (!$attempts): ?>
                        <p>Nenhum aluno realizou este quiz ainda.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                <tr>
                                    <th>Aluno</th>
                                    <th>E-mail</th>
                                    <th>Acertos</th>
                                    <th>Nota</th>
                                    <th>Data</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($attempts as $att): ?>
                                    <tr>
                                        <td><?= e($att['nome']) ?></td>
                                        <td><?= e($att['email']) ?></td>
                                        <td><?= e((string) $att['acertos']) ?>/<?= e((string) $att['total']) ?></td>
                                        <td>
                                            <span class="badge <?= (int) $att['nota'] >= 60 ? 'badge-success' : 'badge-danger' ?>">
                                                <?= e((string) $att['nota']) ?>%
                                            </span>
                                        </td>
                                        <td><?= e((string) $att['feito_em']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <?php
} else {
    // ── Module list ───────────────────────────────────────────────────────────
    $modulesStmt = $pdo->query(
        'SELECT m.id, m.titulo, m.ordem,
                q.id AS quiz_id,
                q.titulo AS quiz_titulo,
                (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS total_perguntas,
                (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id) AS total_tentativas
         FROM modules m
         LEFT JOIN quizzes q ON q.module_id = m.id
         ORDER BY m.ordem ASC, m.id ASC'
    );
    $modules = $modulesStmt->fetchAll();

    $active_page = 'quiz';
    $page_title  = 'Gerenciar Quizzes';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="app-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="content-area">
            <section class="panel">
                <div class="panel-header"><h1>Quizzes por módulo</h1></div>

                <?php if (!$modules): ?>
                    <p>Nenhum módulo cadastrado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                            <tr>
                                <th>Módulo</th>
                                <th>Quiz</th>
                                <th>Perguntas</th>
                                <th>Tentativas</th>
                                <th>Ação</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($modules as $mod): ?>
                                <tr>
                                    <td><?= e($mod['titulo']) ?></td>
                                    <td>
                                        <?php if ($mod['quiz_id']): ?>
                                            <span class="badge badge-success">Criado</span>
                                            <small><?= e($mod['quiz_titulo']) ?></small>
                                        <?php else: ?>
                                            <span class="badge badge-neutral">Sem quiz</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $mod['quiz_id'] ? e((string) $mod['total_perguntas']) : '—' ?></td>
                                    <td><?= $mod['quiz_id'] ? e((string) $mod['total_tentativas']) : '—' ?></td>
                                    <td>
                                        <a class="btn btn-ghost"
                                           href="<?= e(url('admin/quiz.php?module_id=' . (int) $mod['id'])) ?>">
                                            Gerenciar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <?php
}

require_once __DIR__ . '/../includes/footer.php';
