<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];

$action = $_GET['action'] ?? 'list';
$pageTitle = 'Kuis';
$activeNav = 'kuis';

/* =============================================================
   SUBMIT — SERVER menghitung skor. Klien tidak pernah dipercaya.
============================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/quiz.php');
    }

    $quizId = (int) ($_POST['quiz_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, title FROM quizzes WHERE id = ?');
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        setFlash('error', 'Kuis tidak ditemukan.');
        redirectTo('/quiz.php');
    }

    // Ambil semua soal kuis ini + opsi yang benar (server-side, tidak dari browser).
    $stmt = $pdo->prepare('SELECT id FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC');
    $stmt->execute([$quizId]);
    $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($questionIds)) {
        setFlash('error', 'Kuis ini belum memiliki soal.');
        redirectTo('/quiz.php');
    }

    // Peta option_id -> [question_id, is_correct] untuk SELURUH kuis ini,
    // supaya jawaban yang disubmit divalidasi benar-benar milik soal yang dijawab
    // (bukan option_id acak dari soal/kuis lain).
    $stmt = $pdo->prepare(
        'SELECT qo.id, qo.question_id, qo.is_correct FROM quiz_options qo
         JOIN quiz_questions qq ON qq.id = qo.question_id
         WHERE qq.quiz_id = ?'
    );
    $stmt->execute([$quizId]);
    $optionsById = [];
    foreach ($stmt->fetchAll() as $row) {
        $optionsById[(int) $row['id']] = ['question_id' => (int) $row['question_id'], 'is_correct' => (int) $row['is_correct']];
    }

    $submittedAnswers = $_POST['answer'] ?? [];
    $correctCount = 0;
    $answersToSave = []; // [question_id, selected_option_id|null, is_correct]

    foreach ($questionIds as $qid) {
        $qid = (int) $qid;
        $selected = isset($submittedAnswers[$qid]) ? (int) $submittedAnswers[$qid] : null;
        $isValidSelection = $selected !== null && isset($optionsById[$selected]) && $optionsById[$selected]['question_id'] === $qid;

        $isCorrect = $isValidSelection && $optionsById[$selected]['is_correct'] === 1;
        if ($isCorrect) {
            $correctCount++;
        }

        $answersToSave[] = [
            'question_id' => $qid,
            'selected_option_id' => $isValidSelection ? $selected : null,
            'is_correct' => $isCorrect ? 1 : 0,
        ];
    }

    $total = count($questionIds);
    $wrongCount = $total - $correctCount;
    $score = round(($correctCount / $total) * 100, 2);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO quiz_attempts (quiz_id, user_id, score, correct_count, wrong_count, started_at, finished_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$quizId, $userId, $score, $correctCount, $wrongCount]);
        $attemptId = (int) $pdo->lastInsertId();

        $insertAnswer = $pdo->prepare(
            'INSERT INTO quiz_answers (attempt_id, question_id, selected_option_id, is_correct) VALUES (?, ?, ?, ?)'
        );
        foreach ($answersToSave as $a) {
            $insertAnswer->execute([$attemptId, $a['question_id'], $a['selected_option_id'], $a['is_correct']]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Quiz submit failed: ' . $e->getMessage());
        setFlash('error', 'Terjadi kesalahan. Silakan coba lagi.');
        redirectTo('/quiz.php');
    }

    recordQuizCompletion($pdo, $userId, $score);
    setFlash('success', 'Jawaban kuis berhasil dikirim.');
    redirectTo('/quiz.php?action=result&attempt_id=' . $attemptId);
}

/* =============================================================
   RESULT
============================================================= */
if ($action === 'result') {
    $attemptId = (int) ($_GET['attempt_id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT qa.*, q.title AS quiz_title, q.id AS quiz_id FROM quiz_attempts qa
         JOIN quizzes q ON q.id = qa.quiz_id
         WHERE qa.id = ? AND qa.user_id = ?'
    );
    $stmt->execute([$attemptId, $userId]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        setFlash('error', 'Kamu tidak memiliki akses ke data ini.');
        redirectTo('/quiz.php');
    }

    include __DIR__ . '/includes/header.php';
    ?>
    <section class="panel quiz-result">
        <span class="quiz-result__emoji">🎯</span>
        <h1>Hasil Kuis</h1>
        <p class="quiz-result__title"><?= e($attempt['quiz_title']) ?></p>

        <div class="quiz-result__score">
            <span class="quiz-result__score-label">Skor Kamu</span>
            <span class="quiz-result__score-value"><?= e(rtrim(rtrim((string) $attempt['score'], '0'), '.')) ?: '0' ?> / 100</span>
        </div>

        <div class="quiz-result__breakdown">
            <span class="quiz-result__correct">Benar: <?= (int) $attempt['correct_count'] ?></span>
            <span class="quiz-result__wrong">Salah: <?= (int) $attempt['wrong_count'] ?></span>
        </div>

        <div class="quiz-result__actions">
            <a href="<?= BASE_URL ?>/quiz.php?action=review&attempt_id=<?= $attempt['id'] ?>" class="btn btn-secondary">Lihat Pembahasan</a>
            <a href="<?= BASE_URL ?>/quiz.php?action=start&id=<?= $attempt['quiz_id'] ?>" class="btn btn-secondary">Coba Lagi</a>
            <a href="<?= BASE_URL ?>/quiz.php" class="btn btn-primary">Kembali ke Kuis</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* =============================================================
   REVIEW / PEMBAHASAN
============================================================= */
if ($action === 'review') {
    $attemptId = (int) ($_GET['attempt_id'] ?? 0);
    $stmt = $pdo->prepare(
        'SELECT qa.*, q.title AS quiz_title FROM quiz_attempts qa
         JOIN quizzes q ON q.id = qa.quiz_id
         WHERE qa.id = ? AND qa.user_id = ?'
    );
    $stmt->execute([$attemptId, $userId]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        setFlash('error', 'Kamu tidak memiliki akses ke data ini.');
        redirectTo('/quiz.php');
    }

    $stmt = $pdo->prepare(
        'SELECT qq.id AS question_id, qq.question_text, qq.order_index,
                ans.is_correct, ans.selected_option_id,
                selected.option_text AS selected_text,
                correct.option_text AS correct_text
         FROM quiz_answers ans
         JOIN quiz_questions qq ON qq.id = ans.question_id
         LEFT JOIN quiz_options selected ON selected.id = ans.selected_option_id
         LEFT JOIN quiz_options correct ON correct.question_id = qq.id AND correct.is_correct = 1
         WHERE ans.attempt_id = ?
         ORDER BY qq.order_index ASC'
    );
    $stmt->execute([$attemptId]);
    $reviewRows = $stmt->fetchAll();

    include __DIR__ . '/includes/header.php';
    ?>
    <section class="panel">
        <a href="<?= BASE_URL ?>/quiz.php?action=result&attempt_id=<?= $attemptId ?>" class="panel__back">← Kembali ke Hasil</a>
        <div class="panel__head"><h2>Pembahasan — <?= e($attempt['quiz_title']) ?></h2></div>

        <?php foreach ($reviewRows as $i => $row): ?>
            <div class="review-item <?= $row['is_correct'] ? 'review-item--correct' : 'review-item--wrong' ?>">
                <p class="review-item__number">Soal <?= $i + 1 ?></p>
                <p class="review-item__question"><?= e($row['question_text']) ?></p>

                <p class="review-item__label">Jawaban kamu:</p>
                <p class="review-item__answer"><?= e($row['selected_text'] ?? 'Tidak dijawab') ?></p>

                <?php if (!$row['is_correct']): ?>
                    <p class="review-item__label">Jawaban benar:</p>
                    <p class="review-item__answer review-item__answer--correct"><?= e($row['correct_text'] ?? '-') ?></p>
                <?php endif; ?>

                <span class="review-item__verdict">
                    <?= $row['is_correct'] ? '✓ Benar' : '✗ Salah' ?>
                </span>
            </div>
        <?php endforeach; ?>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* =============================================================
   HISTORY / RIWAYAT
============================================================= */
if ($action === 'history') {
    $stmt = $pdo->prepare(
        'SELECT qa.id, qa.score, qa.correct_count, qa.wrong_count, qa.finished_at, q.title AS quiz_title
         FROM quiz_attempts qa
         JOIN quizzes q ON q.id = qa.quiz_id
         WHERE qa.user_id = ?
         ORDER BY qa.finished_at DESC
         LIMIT 50'
    );
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll();

    include __DIR__ . '/includes/header.php';
    ?>
    <section class="panel">
        <a href="<?= BASE_URL ?>/quiz.php" class="panel__back">← Kembali ke Kuis</a>
        <div class="panel__head"><h2>Riwayat Kuis</h2></div>

        <?php if (empty($history)): ?>
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true">🧠</span>
                <p>Belum ada riwayat kuis.</p>
            </div>
        <?php else: ?>
            <ul class="history-list">
                <?php foreach ($history as $h): ?>
                    <li class="history-row">
                        <div class="history-row__main">
                            <span class="history-row__title"><?= e($h['quiz_title']) ?></span>
                            <span class="history-row__date"><?= e((new DateTime($h['finished_at']))->format('d M Y')) ?></span>
                        </div>
                        <a href="<?= BASE_URL ?>/quiz.php?action=review&attempt_id=<?= $h['id'] ?>" class="history-row__score">
                            Skor: <?= e(rtrim(rtrim((string) $h['score'], '0'), '.')) ?: '0' ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* =============================================================
   START (halaman info sebelum mulai)
============================================================= */
if ($action === 'start') {
    $quizId = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare(
        "SELECT q.*, s.name AS subject_name, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) AS question_count
         FROM quizzes q LEFT JOIN subjects s ON s.id = q.subject_id WHERE q.id = ?"
    );
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        setFlash('error', 'Kuis tidak ditemukan.');
        redirectTo('/quiz.php');
    }
    if ((int) $quiz['question_count'] === 0) {
        setFlash('error', 'Kuis ini belum memiliki soal.');
        redirectTo('/quiz.php');
    }

    include __DIR__ . '/includes/header.php';
    ?>
    <section class="panel quiz-start">
        <a href="<?= BASE_URL ?>/quiz.php" class="panel__back">← Kembali ke Kuis</a>

        <h1><?= e($quiz['title']) ?></h1>
        <p class="quiz-start__desc"><?= e($quiz['description'] ?? '') ?></p>

        <div class="quiz-start__meta">
            <span>Jumlah soal: <strong><?= (int) $quiz['question_count'] ?></strong></span>
            <span>Waktu: <strong>Tidak dibatasi</strong></span>
        </div>

        <a href="<?= BASE_URL ?>/quiz.php?action=take&id=<?= $quiz['id'] ?>" class="btn btn-primary btn-block">Mulai Kuis</a>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* =============================================================
   TAKE (mengerjakan soal) — jawaban benar TIDAK dikirim ke klien.
============================================================= */
if ($action === 'take') {
    $quizId = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, title FROM quizzes WHERE id = ?');
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        setFlash('error', 'Kuis tidak ditemukan.');
        redirectTo('/quiz.php');
    }

    $stmt = $pdo->prepare('SELECT id, question_text FROM quiz_questions WHERE quiz_id = ? ORDER BY order_index ASC');
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll();

    if (empty($questions)) {
        setFlash('error', 'Kuis ini belum memiliki soal.');
        redirectTo('/quiz.php');
    }

    $questionIds = array_column($questions, 'id');
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, question_id, option_text FROM quiz_options WHERE question_id IN ($placeholders) ORDER BY id ASC"
    );
    $stmt->execute($questionIds);
    $optionsByQuestion = [];
    foreach ($stmt->fetchAll() as $opt) {
        $optionsByQuestion[$opt['question_id']][] = $opt;
    }

    $csrfToken = generateCsrfToken();
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="panel quiz-take" id="quizTake" data-total="<?= count($questions) ?>">
        <form method="POST" id="quizForm">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?>">

            <p class="quiz-take__counter">Soal <span id="quizCurrentNum">1</span> dari <?= count($questions) ?></p>

            <?php foreach ($questions as $i => $q): ?>
                <div class="quiz-question" data-index="<?= $i ?>" <?= $i > 0 ? 'style="display:none;"' : '' ?>>
                    <p class="quiz-question__text"><?= e($q['question_text']) ?></p>

                    <div class="quiz-options" role="radiogroup" aria-label="Pilihan jawaban soal <?= $i + 1 ?>">
                        <?php foreach ($optionsByQuestion[$q['id']] ?? [] as $opt): ?>
                            <label class="quiz-option">
                                <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= $opt['id'] ?>">
                                <span><?= e($opt['option_text']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="quiz-take__nav">
                <button type="button" class="btn btn-secondary" id="btnPrevQuestion" disabled>Soal Sebelumnya</button>
                <button type="button" class="btn btn-primary" id="btnNextQuestion">Soal Berikutnya</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitQuiz" style="display:none;">Kirim Jawaban</button>
            </div>
        </form>
    </section>

    <script>
    (function () {
        var container = document.getElementById('quizTake');
        var total = parseInt(container.getAttribute('data-total'), 10);
        var current = 0;
        var questions = document.querySelectorAll('.quiz-question');
        var btnPrev = document.getElementById('btnPrevQuestion');
        var btnNext = document.getElementById('btnNextQuestion');
        var btnSubmit = document.getElementById('btnSubmitQuiz');
        var counterEl = document.getElementById('quizCurrentNum');

        function render() {
            questions.forEach(function (q, idx) {
                q.style.display = idx === current ? '' : 'none';
            });
            counterEl.textContent = current + 1;
            btnPrev.disabled = current === 0;
            var isLast = current === total - 1;
            btnNext.style.display = isLast ? 'none' : '';
            btnSubmit.style.display = isLast ? '' : 'none';
        }

        btnPrev.addEventListener('click', function () {
            if (current > 0) { current--; render(); }
        });
        btnNext.addEventListener('click', function () {
            if (current < total - 1) { current++; render(); }
        });

        document.getElementById('quizForm').addEventListener('submit', function (e) {
            if (!confirm('Kirim jawaban sekarang? Kamu tidak bisa mengubahnya setelah dikirim.')) {
                e.preventDefault();
            }
        });

        render();
    })();
    </script>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

/* =============================================================
   LIST (default)
============================================================= */
$q = trim($_GET['q'] ?? '');
$filterSubject = (int) ($_GET['subject_id'] ?? 0);

$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = 'qz.title LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($filterSubject > 0) {
    $where[] = 'qz.subject_id = ?';
    $params[] = $filterSubject;
}

$sql = "SELECT qz.*, s.name AS subject_name,
               (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = qz.id) AS question_count,
               (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = qz.id AND user_id = ?) AS best_score,
               (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = qz.id AND user_id = ?) AS attempt_count
        FROM quizzes qz
        LEFT JOIN subjects s ON s.id = qz.subject_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY qz.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$userId, $userId], $params));
$quizzes = $stmt->fetchAll();

$stmt = $pdo->query("SELECT DISTINCT s.id, s.name FROM subjects s JOIN quizzes qz ON qz.subject_id = s.id ORDER BY s.name");
$subjectOptions = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel__head">
        <h2>Kuis</h2>
        <a href="<?= BASE_URL ?>/quiz.php?action=history" class="panel__link">Lihat Riwayat</a>
    </div>

    <form method="GET" class="task-toolbar">
        <input type="search" name="q" placeholder="Cari kuis..." value="<?= e($q) ?>" class="task-toolbar__search">
        <select name="subject_id" onchange="this.form.submit()">
            <option value="0">Semua Mapel</option>
            <?php foreach ($subjectOptions as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $filterSubject === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Cari</button>
    </form>

    <?php if (empty($quizzes)): ?>
        <div class="empty-state">
            <span class="empty-state__icon" aria-hidden="true">🧠</span>
            <p>Belum ada kuis yang tersedia.</p>
        </div>
    <?php else: ?>
        <div class="quiz-list">
            <?php foreach ($quizzes as $qz): ?>
                <div class="quiz-card">
                    <div class="quiz-card__main">
                        <h3 class="quiz-card__title"><?= e($qz['title']) ?></h3>
                        <p class="quiz-card__meta"><?= (int) $qz['question_count'] ?> Soal · <?= e($qz['subject_name'] ?? 'Umum') ?></p>
                        <?php if ($qz['description']): ?>
                            <p class="quiz-card__desc"><?= e($qz['description']) ?></p>
                        <?php endif; ?>
                        <?php if ((int) $qz['attempt_count'] > 0): ?>
                            <span class="badge badge-completed">✓ Skor terbaik: <?= e(rtrim(rtrim((string) $qz['best_score'], '0'), '.')) ?: '0' ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= BASE_URL ?>/quiz.php?action=start&id=<?= $qz['id'] ?>" class="btn btn-primary">Mulai Kuis</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
