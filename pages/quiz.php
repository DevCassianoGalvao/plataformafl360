<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'aluno') {
    redirect('admin/dashboard.php');
}

$user      = current_user($pdo);
$userId    = (int) $user['id'];
$moduleId  = isset($_GET['module_id'])  ? (int) $_GET['module_id']  : 0;
$attemptId = isset($_GET['attempt_id']) ? (int) $_GET['attempt_id'] : 0;

if ($moduleId <= 0) {
    flash('error', 'Módulo inválido.');
    redirect('pages/modulos.php');
}

// ─── Load module + quiz ────────────────────────────────────────────────────────
$modStmt = $pdo->prepare('SELECT id, titulo FROM modules WHERE id = :id LIMIT 1');
$modStmt->execute([':id' => $moduleId]);
$module = $modStmt->fetch();

if (!$module) {
    flash('error', 'Módulo não encontrado.');
    redirect('pages/modulos.php');
}

$quizStmt = $pdo->prepare('SELECT id, titulo, descricao FROM quizzes WHERE module_id = :mid LIMIT 1');
$quizStmt->execute([':mid' => $moduleId]);
$quiz = $quizStmt->fetch();

if (!$quiz) {
    flash('error', 'Este módulo não possui quiz.');
    redirect('pages/modulos.php');
}

$quizId = (int) $quiz['id'];

// ─── Load questions + options ──────────────────────────────────────────────────
$qsStmt = $pdo->prepare(
    'SELECT id, pergunta, ordem FROM quiz_questions WHERE quiz_id = :qid ORDER BY ordem ASC, id ASC'
);
$qsStmt->execute([':qid' => $quizId]);
$questions = $qsStmt->fetchAll();

if (!$questions) {
    flash('error', 'Este quiz ainda não possui perguntas.');
    redirect('pages/modulos.php');
}

foreach ($questions as &$question) {
    $opStmt = $pdo->prepare(
        'SELECT id, texto FROM quiz_options WHERE question_id = :qid ORDER BY id ASC'
    );
    $opStmt->execute([':qid' => (int) $question['id']]);
    $question['options'] = $opStmt->fetchAll();
}
unset($question);

// ─── POST: submit answers ──────────────────────────────────────────────────────
if (is_post()) {
    require_csrf_token($_POST['csrf_token'] ?? null);

    $submitted = $_POST['answer'] ?? [];
    $acertos   = 0;
    $total     = count($questions);
    $respostas = [];

    foreach ($questions as $question) {
        $qid        = (int) $question['id'];
        $selectedId = isset($submitted[$qid]) ? (int) $submitted[$qid] : 0;
        $respostas[$qid] = $selectedId;

        if ($selectedId > 0) {
            $chkStmt = $pdo->prepare(
                'SELECT correta FROM quiz_options WHERE id = :id AND question_id = :qid LIMIT 1'
            );
            $chkStmt->execute([':id' => $selectedId, ':qid' => $qid]);
            $correta = (int) ($chkStmt->fetchColumn() ?: 0);
            if ($correta === 1) {
                $acertos++;
            }
        }
    }

    $nota = $total > 0 ? (int) round(($acertos / $total) * 100) : 0;

    $insStmt = $pdo->prepare(
        'INSERT INTO quiz_attempts (user_id, quiz_id, acertos, total, nota, respostas, feito_em)
         VALUES (:uid, :qid, :acertos, :total, :nota, :respostas, NOW())'
    );
    $insStmt->execute([
        ':uid'      => $userId,
        ':qid'      => $quizId,
        ':acertos'  => $acertos,
        ':total'    => $total,
        ':nota'     => $nota,
        ':respostas' => json_encode($respostas),
    ]);

    $newAttemptId = (int) $pdo->lastInsertId();
    redirect('pages/quiz.php?module_id=' . $moduleId . '&attempt_id=' . $newAttemptId);
}

// ─── Result view ───────────────────────────────────────────────────────────────
$attempt     = null;
$resultData  = [];

if ($attemptId > 0) {
    $attStmt = $pdo->prepare(
        'SELECT id, acertos, total, nota, respostas, feito_em
         FROM quiz_attempts
         WHERE id = :id AND user_id = :uid AND quiz_id = :qid
         LIMIT 1'
    );
    $attStmt->execute([':id' => $attemptId, ':uid' => $userId, ':qid' => $quizId]);
    $attempt = $attStmt->fetch() ?: null;

    if ($attempt) {
        $respostas = json_decode((string) ($attempt['respostas'] ?? '{}'), true) ?: [];

        foreach ($questions as $question) {
            $qid        = (int) $question['id'];
            $selectedId = (int) ($respostas[$qid] ?? 0);

            $allOptsStmt = $pdo->prepare(
                'SELECT id, texto, correta FROM quiz_options WHERE question_id = :qid ORDER BY id ASC'
            );
            $allOptsStmt->execute([':qid' => $qid]);
            $allOpts = $allOptsStmt->fetchAll();

            $correctId = 0;
            foreach ($allOpts as $opt) {
                if ((int) $opt['correta'] === 1) {
                    $correctId = (int) $opt['id'];
                }
            }

            $resultData[] = [
                'question'    => $question['pergunta'],
                'options'     => $allOpts,
                'selected_id' => $selectedId,
                'correct_id'  => $correctId,
                'is_correct'  => $selectedId === $correctId && $selectedId > 0,
            ];
        }
    }
}

$active_page = 'modulos';
$page_title  = 'Quiz — ' . $quiz['titulo'];
$totalQ      = count($questions);
require_once __DIR__ . '/../includes/header.php';
?>
<div class="app-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content-area quiz-fullpage">

        <?php if ($attempt): ?>
        <!-- ════════════════════════ RESULTADO ════════════════════════ -->
        <?php
            $nota      = (int) $attempt['nota'];
            $pass      = $nota >= 60;
            $emoji     = $pass ? '🎉' : '📚';
            $headline  = $pass ? 'Parabéns, você foi bem!' : 'Continue estudando!';
            $sub       = $pass
                ? 'Você demonstrou domínio do conteúdo deste módulo.'
                : 'Revise o material e tente novamente para melhorar sua nota.';
        ?>
        <div class="qr-page">
            <a class="qr-back" href="<?= e(url('pages/modulos.php')) ?>">
                &#8592; Voltar aos módulos
            </a>

            <div class="qr-hero">
                <div class="qr-emoji"><?= $emoji ?></div>
                <h1 class="qr-headline"><?= $headline ?></h1>
                <p class="qr-sub"><?= $sub ?></p>

                <div class="qr-ring <?= $pass ? 'qr-ring-pass' : 'qr-ring-fail' ?>">
                    <svg viewBox="0 0 120 120" class="qr-ring-svg">
                        <circle cx="60" cy="60" r="52" class="qr-ring-track"/>
                        <circle cx="60" cy="60" r="52" class="qr-ring-fill"
                            stroke-dasharray="<?= round(($nota / 100) * 326.7, 1) ?> 326.7"/>
                    </svg>
                    <div class="qr-ring-inner">
                        <span class="qr-ring-pct"><?= $nota ?>%</span>
                        <span class="qr-ring-lbl"><?= e((string) $attempt['acertos']) ?>/<?= e((string) $attempt['total']) ?></span>
                    </div>
                </div>

                <div class="qr-actions">
                    <a class="btn btn-primary btn-lg"
                       href="<?= e(url('pages/quiz.php?module_id=' . $moduleId)) ?>">
                        Refazer quiz
                    </a>
                    <a class="btn btn-ghost"
                       href="<?= e(url('pages/modulos.php')) ?>">
                        Módulos
                    </a>
                </div>
            </div>

            <!-- Revisão ─────────────────────────────────────────────── -->
            <div class="qr-review">
                <h2 class="qr-review-title">Revisão das respostas</h2>

                <?php foreach ($resultData as $i => $rd): ?>
                    <div class="qr-review-card <?= $rd['is_correct'] ? 'qrr-correct' : 'qrr-wrong' ?>">
                        <div class="qrr-badge">
                            <?= $rd['is_correct'] ? '✓' : '✗' ?>
                        </div>
                        <div class="qrr-body">
                            <p class="qrr-num">Questão <?= ($i + 1) ?></p>
                            <p class="qrr-text"><?= e($rd['question']) ?></p>
                            <ul class="qrr-options">
                                <?php foreach ($rd['options'] as $li => $opt): ?>
                                    <?php
                                    $oid    = (int) $opt['id'];
                                    $isSel  = $oid === $rd['selected_id'];
                                    $isCor  = $oid === $rd['correct_id'];
                                    $cls    = $isCor ? 'qrro-correct' : ($isSel ? 'qrro-wrong' : '');
                                    ?>
                                    <li class="qrro <?= $cls ?>">
                                        <span class="qrro-letter"><?= chr(65 + $li) ?></span>
                                        <span><?= e($opt['texto']) ?></span>
                                        <?php if ($isSel && $isCor): ?>
                                            <span class="qrro-tag">Sua resposta ✓</span>
                                        <?php elseif ($isSel): ?>
                                            <span class="qrro-tag qrro-tag-wrong">Sua resposta ✗</span>
                                        <?php elseif ($isCor): ?>
                                            <span class="qrro-tag">Correta</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- ════════════════════════ QUIZ FORM ════════════════════════ -->
        <div class="qz-wrapper" id="quizWrapper">

            <!-- Top bar -->
            <div class="qz-topbar">
                <a class="qz-back" href="<?= e(url('pages/modulos.php')) ?>">&#8592; Sair</a>
                <div class="qz-counter">
                    <span id="qzCurrent">1</span>
                    <span class="qz-counter-sep">/</span>
                    <span><?= $totalQ ?></span>
                </div>
                <div class="qz-progress-wrap">
                    <div class="qz-progress-bar" id="qzBar"
                         style="width: <?= round(1 / $totalQ * 100, 1) ?>%"></div>
                </div>
            </div>

            <form method="post" id="quizForm">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <!-- Questions -->
                <div class="qz-stage" id="qzStage">
                    <?php foreach ($questions as $i => $question): ?>
                        <div class="qz-question <?= $i === 0 ? 'qz-active' : '' ?>"
                             data-index="<?= $i ?>">

                            <div class="qz-question-header">
                                <span class="qz-q-tag"><?= $module['titulo'] ?></span>
                            </div>

                            <h2 class="qz-question-text"><?= e($question['pergunta']) ?></h2>

                            <div class="qz-options" role="radiogroup">
                                <?php foreach ($question['options'] as $li => $opt): ?>
                                    <label class="qz-option" tabindex="0">
                                        <input type="radio"
                                               name="answer[<?= (int) $question['id'] ?>]"
                                               value="<?= (int) $opt['id'] ?>">
                                        <span class="qz-opt-letter"><?= chr(65 + $li) ?></span>
                                        <span class="qz-opt-text"><?= e($opt['texto']) ?></span>
                                        <span class="qz-opt-check">&#10003;</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Navigation -->
                <div class="qz-nav">
                    <div class="qz-nav-dots" id="qzDots">
                        <?php for ($d = 0; $d < $totalQ; $d++): ?>
                            <span class="qz-dot <?= $d === 0 ? 'qz-dot-active' : '' ?>"
                                  data-dot="<?= $d ?>"></span>
                        <?php endfor; ?>
                    </div>

                    <button type="button" class="btn btn-primary btn-lg qz-btn-next"
                            id="qzNext" onclick="qzNext()">
                        Próxima questão <span class="qz-arrow">&#8594;</span>
                    </button>

                    <button type="submit" class="btn btn-primary btn-lg qz-btn-submit"
                            id="qzSubmit" style="display:none">
                        Ver resultado &#9654;
                    </button>
                </div>
            </form>
        </div>

        <script>
        (function () {
            const total    = <?= $totalQ ?>;
            let   current  = 0;

            const questions = document.querySelectorAll('.qz-question');
            const dots      = document.querySelectorAll('.qz-dot');
            const bar       = document.getElementById('qzBar');
            const counter   = document.getElementById('qzCurrent');
            const btnNext   = document.getElementById('qzNext');
            const btnSubmit = document.getElementById('qzSubmit');

            function answered(index) {
                const q   = questions[index];
                const inp = q.querySelectorAll('input[type="radio"]');
                return Array.from(inp).some(r => r.checked);
            }

            function goTo(index, direction) {
                const prev = questions[current];
                prev.classList.remove('qz-active');
                prev.classList.add(direction === 1 ? 'qz-exit-left' : 'qz-exit-right');
                setTimeout(() => prev.classList.remove('qz-exit-left', 'qz-exit-right'), 400);

                current = index;
                const next = questions[current];
                next.classList.add(direction === 1 ? 'qz-enter-right' : 'qz-enter-left');
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        next.classList.remove('qz-enter-right', 'qz-enter-left');
                        next.classList.add('qz-active');
                    });
                });

                counter.textContent = current + 1;
                bar.style.width = ((current + 1) / total * 100).toFixed(1) + '%';

                dots.forEach((d, i) => {
                    d.classList.toggle('qz-dot-active', i === current);
                    d.classList.toggle('qz-dot-done',   i < current);
                });

                btnNext.style.display   = current < total - 1 ? '' : 'none';
                btnSubmit.style.display = current === total - 1 ? '' : 'none';
            }

            window.qzNext = function () {
                if (!answered(current)) {
                    const q = questions[current];
                    q.classList.add('qz-shake');
                    setTimeout(() => q.classList.remove('qz-shake'), 500);
                    return;
                }
                if (current < total - 1) goTo(current + 1, 1);
            };

            // keyboard support
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && btnNext.style.display !== 'none') window.qzNext();
            });

            // clicking a label marks it visually
            document.querySelectorAll('.qz-option').forEach(function (label) {
                label.addEventListener('keydown', function (e) {
                    if (e.key === ' ' || e.key === 'Enter') {
                        e.preventDefault();
                        const inp = label.querySelector('input');
                        if (inp) { inp.checked = true; inp.dispatchEvent(new Event('change', {bubbles:true})); }
                    }
                });
            });

            // prevent submit without all answered
            document.getElementById('quizForm').addEventListener('submit', function (e) {
                for (let i = 0; i < total; i++) {
                    if (!answered(i)) {
                        e.preventDefault();
                        goTo(i, i > current ? 1 : -1);
                        return;
                    }
                }
            });
        })();
        </script>
        <?php endif; ?>
    </main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
