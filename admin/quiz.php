<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_content_manager();

$manager = current_user($pdo);
$managerId = (int) $manager['id'];
$isAdmin = $manager['role'] === 'admin';
$quizPath = content_manager_path('quiz.php');

if (!db_column_exists($pdo, 'quiz_questions', 'tipo')) {
    flash('error', 'A atualização segura do quiz precisa ser executada pelo administrador.');
    redirect($isAdmin ? 'admin/migracoes.php' : 'professor/dashboard.php');
}

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
        $liberacao = in_array(($_POST['liberacao'] ?? ''), ['sempre', 'apos_aulas'], true) ? (string) $_POST['liberacao'] : 'apos_aulas';

        if (!can_manage_module($pdo, $mId, $manager) || $titulo === '') {
            flash('error', 'Título do quiz é obrigatório.');
            redirect($quizPath . '?module_id=' . $mId);
        }

        $pdo->prepare('INSERT INTO quizzes (module_id, titulo, descricao, liberacao) VALUES (:m, :t, :d, :l)')
            ->execute([':m' => $mId, ':t' => $titulo, ':d' => $descricao, ':l' => $liberacao]);

        flash('success', 'Quiz criado com sucesso.');
        redirect($quizPath . '?module_id=' . $mId);
    }

    if ($action === 'update_quiz') {
        $quizId   = (int)    ($_POST['quiz_id']   ?? 0);
        $titulo   = trim((string) ($_POST['titulo']   ?? ''));
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $liberacao = in_array(($_POST['liberacao'] ?? ''), ['sempre', 'apos_aulas'], true) ? (string) $_POST['liberacao'] : 'apos_aulas';

        $stmt = $pdo->prepare('SELECT module_id FROM quizzes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $quizId]);
        $mId = (int) ($stmt->fetchColumn() ?: 0);

        if (!can_manage_module($pdo, $mId, $manager)) {
            http_response_code(403);
            exit('Você não tem permissão para alterar este quiz.');
        }

        if ($quizId <= 0 || $titulo === '' || $mId <= 0) {
            flash('error', 'Dados inválidos.');
            redirect($quizPath);
        }

        $pdo->prepare('UPDATE quizzes SET titulo = :t, descricao = :d, liberacao = :l WHERE id = :id')
            ->execute([':t' => $titulo, ':d' => $descricao, ':l' => $liberacao, ':id' => $quizId]);

        flash('success', 'Quiz atualizado.');
        redirect($quizPath . '?module_id=' . $mId);
    }

    if ($action === 'delete_quiz') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT module_id FROM quizzes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $quizId]);
        if (!can_manage_module($pdo, (int) ($stmt->fetchColumn() ?: 0), $manager)) {
            http_response_code(403);
            exit('Você não tem permissão para excluir este quiz.');
        }
        $pdo->prepare('DELETE FROM quizzes WHERE id = :id')->execute([':id' => $quizId]);
        flash('success', 'Quiz excluído.');
        redirect($quizPath);
    }

    if ($action === 'save_question') {
        $quizId     = (int)    ($_POST['quiz_id']     ?? 0);
        $questionId = (int)    ($_POST['question_id'] ?? 0);
        $pergunta   = trim((string) ($_POST['pergunta']   ?? ''));
        $tipo = in_array(($_POST['tipo'] ?? ''), ['multipla_escolha', 'texto'], true)
            ? (string) $_POST['tipo']
            : 'multipla_escolha';
        $correctIndex = filter_var($_POST['correct_index'] ?? null, FILTER_VALIDATE_INT);
        $submittedOptions = $_POST['options'] ?? [];
        $opts = is_array($submittedOptions)
            ? array_values(array_map(
                static fn ($option): string => is_scalar($option) ? trim((string) $option) : '',
                $submittedOptions
            ))
            : [];

        $stmt = $pdo->prepare('SELECT module_id FROM quizzes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $quizId]);
        $mId = (int) ($stmt->fetchColumn() ?: 0);

        if (!can_manage_module($pdo, $mId, $manager)) {
            http_response_code(403);
            exit('Você não tem permissão para alterar este quiz.');
        }

        $optionCount = count($opts);
        $invalidOptions = $tipo === 'multipla_escolha' && (
            $optionCount < 2
            || $optionCount > 20
            || in_array('', $opts, true)
            || $correctIndex === false
            || $correctIndex < 0
            || $correctIndex >= $optionCount
        );
        if ($pergunta === '') {
            flash('error', 'Informe o enunciado da pergunta.');
            redirect($quizPath . '?module_id=' . $mId);
        }
        if ($invalidOptions) {
            flash('error', 'Informe de 2 a 20 opções preenchidas e marque uma delas como correta.');
            redirect($quizPath . '?module_id=' . $mId);
        }
        if ($questionId > 0) {
            $questionCheck = $pdo->prepare('SELECT COUNT(*) FROM quiz_questions WHERE id = :id AND quiz_id = :quiz_id');
            $questionCheck->execute([':id' => $questionId, ':quiz_id' => $quizId]);
            if ((int) $questionCheck->fetchColumn() !== 1) {
                flash('error', 'Pergunta inválida para este quiz.');
                redirect($quizPath . '?module_id=' . $mId);
            }
        }

        $pdo->beginTransaction();
        try {
            if ($questionId > 0) {
                $pdo->prepare('UPDATE quiz_questions SET pergunta = :p, tipo = :tipo WHERE id = :id AND quiz_id = :qid')
                    ->execute([':p' => $pergunta, ':tipo' => $tipo, ':id' => $questionId, ':qid' => $quizId]);
                $pdo->prepare('DELETE FROM quiz_options WHERE question_id = :qid')
                    ->execute([':qid' => $questionId]);
            } else {
                $stmt = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) FROM quiz_questions WHERE quiz_id = :qid');
                $stmt->execute([':qid' => $quizId]);
                $ordem = ((int) $stmt->fetchColumn()) + 1;

                $pdo->prepare('INSERT INTO quiz_questions (quiz_id, pergunta, tipo, ordem) VALUES (:qid, :p, :tipo, :o)')
                    ->execute([':qid' => $quizId, ':p' => $pergunta, ':tipo' => $tipo, ':o' => $ordem]);
                $questionId = (int) $pdo->lastInsertId();
            }

            if ($tipo === 'multipla_escolha') {
                foreach ($opts as $index => $optionText) {
                    $pdo->prepare('INSERT INTO quiz_options (question_id, texto, correta) VALUES (:qid, :t, :c)')
                        ->execute([':qid' => $questionId, ':t' => $optionText, ':c' => ($correctIndex === $index ? 1 : 0)]);
                }
            }

            $pdo->commit();
            flash('success', 'Pergunta salva.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('error', 'Erro ao salvar pergunta.');
        }

        redirect($quizPath . '?module_id=' . $mId);
    }

    if ($action === 'delete_question') {
        $questionId = (int) ($_POST['question_id'] ?? 0);
        $quizId     = (int) ($_POST['quiz_id']     ?? 0);

        $stmt = $pdo->prepare('SELECT module_id FROM quizzes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $quizId]);
        $mId = (int) ($stmt->fetchColumn() ?: 0);

        if (!can_manage_module($pdo, $mId, $manager)) {
            http_response_code(403);
            exit('Você não tem permissão para alterar este quiz.');
        }

        $pdo->prepare('DELETE FROM quiz_questions WHERE id = :id AND quiz_id = :qid')
            ->execute([':id' => $questionId, ':qid' => $quizId]);

        flash('success', 'Pergunta excluída.');
        redirect($quizPath . '?module_id=' . $mId);
    }
}

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($moduleId > 0) {
    if (!can_manage_module($pdo, $moduleId, $manager)) {
        http_response_code(403);
        exit('Você não tem permissão para gerenciar este módulo.');
    }
    // ── Module quiz management ────────────────────────────────────────────────
    $moduleStmt = $pdo->prepare('SELECT id, titulo FROM modules WHERE id = :id LIMIT 1');
    $moduleStmt->execute([':id' => $moduleId]);
    $module = $moduleStmt->fetch();

    if (!$module) {
        flash('error', 'Módulo não encontrado.');
        redirect($quizPath);
    }

    $quizStmt = $pdo->prepare('SELECT id, titulo, descricao, liberacao FROM quizzes WHERE module_id = :mid LIMIT 1');
    $quizStmt->execute([':mid' => $moduleId]);
    $quiz = $quizStmt->fetch() ?: null;

    $questions   = [];
    $attempts    = [];
    $editQuestion = null;

    if ($quiz) {
        $quizId = (int) $quiz['id'];

        $qsStmt = $pdo->prepare(
            'SELECT id, pergunta, tipo, ordem FROM quiz_questions WHERE quiz_id = :qid ORDER BY ordem ASC, id ASC'
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
            'SELECT qa.id, qa.respostas, u.nome, u.email, qa.acertos, qa.total, qa.nota, qa.feito_em
             FROM quiz_attempts qa
             INNER JOIN users u ON u.id = qa.user_id
             WHERE qa.quiz_id = :qid
             ORDER BY qa.feito_em DESC'
        );
        $attStmt->execute([':qid' => $quizId]);
        $attempts = $attStmt->fetchAll();

        if ($editQuestionId > 0) {
            $eqStmt = $pdo->prepare(
                'SELECT id, pergunta, tipo FROM quiz_questions WHERE id = :id AND quiz_id = :qid LIMIT 1'
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
                    <a class="btn btn-ghost" href="<?= e(url($quizPath)) ?>">← Todos os módulos</a>
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

                        <label for="liberacao">Quando liberar para o aluno?</label>
                        <select id="liberacao" name="liberacao"><option value="apos_aulas">Após concluir todas as aulas</option><option value="sempre">Sempre disponível</option></select>

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

                        <label for="edit_liberacao">Quando liberar para o aluno?</label>
                        <select id="edit_liberacao" name="liberacao"><option value="apos_aulas" <?= $quiz['liberacao'] === 'apos_aulas' ? 'selected' : '' ?>>Após concluir todas as aulas</option><option value="sempre" <?= $quiz['liberacao'] === 'sempre' ? 'selected' : '' ?>>Sempre disponível</option></select>

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
                                    <div>
                                        <span class="badge badge-neutral"><?= $question['tipo'] === 'texto' ? 'Resposta aberta' : 'Múltipla escolha' ?></span>
                                        <strong><?= ($i + 1) ?>. <?= e($question['pergunta']) ?></strong>
                                    </div>
                                    <div class="inline-form">
                                        <a class="btn btn-ghost"
                                           href="<?= e(url($quizPath . '?module_id=' . $moduleId . '&edit_question=' . (int) $question['id'])) ?>">
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
                                <?php if ($question['tipo'] === 'texto'): ?>
                                    <p class="quiz-open-question-note">O aluno responderá em um campo de texto. Esta questão não entra na nota automática.</p>
                                <?php elseif ($question['options']): ?>
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
                            <a class="btn btn-ghost" href="<?= e(url($quizPath . '?module_id=' . $moduleId)) ?>">Cancelar edição</a>
                        <?php endif; ?>
                    </div>
                    <form method="post" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_question">
                        <input type="hidden" name="quiz_id" value="<?= e((string) $quiz['id']) ?>">
                        <input type="hidden" name="question_id" value="<?= $editQuestion ? e((string) $editQuestion['id']) : '0' ?>">

                        <label for="pergunta">Enunciado da pergunta</label>
                        <textarea id="pergunta" name="pergunta" rows="2" required><?= $editQuestion ? e($editQuestion['pergunta']) : '' ?></textarea>

                        <?php $questionType = $editQuestion['tipo'] ?? 'multipla_escolha'; ?>
                        <label for="questionType">Tipo de resposta</label>
                        <select id="questionType" name="tipo">
                            <option value="multipla_escolha" <?= $questionType === 'multipla_escolha' ? 'selected' : '' ?>>Múltipla escolha com correção automática</option>
                            <option value="texto" <?= $questionType === 'texto' ? 'selected' : '' ?>>Resposta aberta para opinião ou comentário</option>
                        </select>

                        <?php
                        $correctIndex = 0;
                        $optTexts = ['', ''];
                        if ($editQuestion && !empty($editQuestion['options'])) {
                            $optTexts = [];
                            foreach ($editQuestion['options'] as $index => $opt) {
                                $optTexts[] = (string) $opt['texto'];
                                if ((int) $opt['correta'] === 1) {
                                    $correctIndex = $index;
                                }
                            }
                        }
                        while (count($optTexts) < 2) {
                            $optTexts[] = '';
                        }
                        ?>

                        <div class="quiz-options-builder" id="quizOptionsBuilder" data-min="2" data-max="20">
                            <div class="quiz-options-heading">
                                <div><strong>Alternativas</strong><small>Adicione quantas precisar e marque a resposta correta.</small></div>
                                <button type="button" class="btn btn-ghost" id="addQuizOption">Adicionar opção</button>
                            </div>
                            <div class="quiz-option-rows" id="quizOptionRows">
                                <?php foreach ($optTexts as $index => $optionText): ?>
                                    <div class="quiz-option-editor">
                                        <label class="quiz-option-text">
                                            <span>Opção <strong data-option-letter><?= chr(65 + $index) ?></strong></span>
                                            <input type="text" name="options[]" value="<?= e($optionText) ?>" maxlength="500" required>
                                        </label>
                                        <label class="quiz-correct-choice">
                                            <input type="radio" name="correct_index" value="<?= $index ?>" <?= $correctIndex === $index ? 'checked' : '' ?> required>
                                            <span>Correta</span>
                                        </label>
                                        <button type="button" class="quiz-remove-option" data-remove-option aria-label="Remover esta opção">Remover</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <?= $editQuestion ? 'Salvar alterações' : 'Adicionar pergunta' ?>
                        </button>
                    </form>
                </section>
                <script>
                (() => {
                    const builder = document.getElementById('quizOptionsBuilder');
                    if (!builder) return;

                    const rows = document.getElementById('quizOptionRows');
                    const addButton = document.getElementById('addQuizOption');
                    const typeSelect = document.getElementById('questionType');
                    const min = Number(builder.dataset.min || 2);
                    const max = Number(builder.dataset.max || 20);

                    function updateRows() {
                        const items = [...rows.querySelectorAll('.quiz-option-editor')];
                        items.forEach((item, index) => {
                            item.querySelector('[data-option-letter]').textContent = String.fromCharCode(65 + index);
                            item.querySelector('input[type="radio"]').value = String(index);
                            item.querySelector('[data-remove-option]').disabled = items.length <= min;
                        });
                        addButton.disabled = items.length >= max;
                    }

                    function updateQuestionType() {
                        const isOpenQuestion = typeSelect.value === 'texto';
                        builder.hidden = isOpenQuestion;
                        builder.querySelectorAll('input, button').forEach((control) => {
                            control.disabled = isOpenQuestion;
                        });
                        if (!isOpenQuestion) {
                            updateRows();
                        }
                    }

                    function addOption() {
                        if (rows.children.length >= max) return;
                        const item = document.createElement('div');
                        item.className = 'quiz-option-editor';
                        item.innerHTML = `
                            <label class="quiz-option-text">
                                <span>Opção <strong data-option-letter></strong></span>
                                <input type="text" name="options[]" maxlength="500" required>
                            </label>
                            <label class="quiz-correct-choice">
                                <input type="radio" name="correct_index" required>
                                <span>Correta</span>
                            </label>
                            <button type="button" class="quiz-remove-option" data-remove-option aria-label="Remover esta opção">Remover</button>`;
                        rows.appendChild(item);
                        updateRows();
                        item.querySelector('input[type="text"]').focus();
                    }

                    addButton.addEventListener('click', addOption);
                    typeSelect.addEventListener('change', updateQuestionType);
                    rows.addEventListener('click', (event) => {
                        const removeButton = event.target.closest('[data-remove-option]');
                        if (!removeButton || rows.children.length <= min) return;

                        const item = removeButton.closest('.quiz-option-editor');
                        const removedCorrect = item.querySelector('input[type="radio"]').checked;
                        item.remove();
                        if (removedCorrect && !rows.querySelector('input[type="radio"]:checked')) {
                            rows.querySelector('input[type="radio"]').checked = true;
                        }
                        updateRows();
                    });

                    updateQuestionType();
                })();
                </script>

                <!-- ── Resultados dos alunos ──────────────────────────────── -->
                <section class="panel">
                    <div class="panel-header"><div><h2>Resultados dos alunos (<?= count($attempts) ?> tentativas)</h2><p>Abra “Ver respostas” para ler opiniões e comentários enviados.</p></div></div>
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
                                    <th>Respostas abertas</th>
                                    <th>Data</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($attempts as $att): ?>
                                    <?php $attemptAnswers = json_decode((string) ($att['respostas'] ?? '{}'), true) ?: []; ?>
                                    <tr>
                                        <td><?= e($att['nome']) ?></td>
                                        <td><?= e($att['email']) ?></td>
                                        <td><?= e((string) $att['acertos']) ?>/<?= e((string) $att['total']) ?></td>
                                        <td>
                                            <?php if ((int) $att['total'] > 0): ?>
                                                <span class="badge <?= (int) $att['nota'] >= 60 ? 'badge-success' : 'badge-danger' ?>"><?= e((string) $att['nota']) ?>%</span>
                                            <?php else: ?>
                                                <span class="badge badge-neutral">Sem avaliação</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php $hasOpenAnswers = false; ?>
                                            <?php foreach ($questions as $question): ?>
                                                <?php if ($question['tipo'] === 'texto' && trim((string) ($attemptAnswers[$question['id']] ?? '')) !== '') { $hasOpenAnswers = true; break; } ?>
                                            <?php endforeach; ?>
                                            <?php if ($hasOpenAnswers): ?>
                                                <details class="quiz-open-answers">
                                                    <summary>Ver respostas</summary>
                                                    <?php foreach ($questions as $question): ?>
                                                        <?php if ($question['tipo'] === 'texto'): ?>
                                                            <div><strong><?= e($question['pergunta']) ?></strong><p><?= nl2br(e((string) ($attemptAnswers[$question['id']] ?? 'Sem resposta.'))) ?></p></div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </details>
                                            <?php else: ?>
                                                <span class="muted">Nenhuma</span>
                                            <?php endif; ?>
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
    $modulesSql = 'SELECT m.id, m.titulo, m.ordem,
                q.id AS quiz_id,
                q.titulo AS quiz_titulo,
                (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS total_perguntas,
                (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id) AS total_tentativas
         FROM modules m
         LEFT JOIN quizzes q ON q.module_id = m.id';
    $modulesParams = [];
    if (!$isAdmin) {
        $modulesSql .= ' INNER JOIN module_professors mp ON mp.module_id = m.id WHERE mp.user_id = :professor_id';
        $modulesParams[':professor_id'] = $managerId;
    }
    $modulesSql .= ' ORDER BY m.ordem ASC, m.id ASC';
    $modulesStmt = $pdo->prepare($modulesSql);
    $modulesStmt->execute($modulesParams);
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
                                           href="<?= e(url($quizPath . '?module_id=' . (int) $mod['id'])) ?>">
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
