<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user = currentUser();
$pdo = getDbConnection();
$userId = (int) $user['id'];

/* -----------------------------------------------------------
   Mulai & simpan sesi fokus.
   Waktu mulai disimpan di server agar duration_minutes dari browser
   tidak bisa digunakan untuk memberikan durasi/XP palsu.
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        if ($action === 'start_session') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Sesi form tidak valid.']);
            exit;
        }
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/focus.php');
    }

    if ($action === 'start_session') {
        $studyMinutes = (int) ($_POST['study_minutes'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $taskId = (int) ($_POST['task_id'] ?? 0);

        if ($studyMinutes < 1 || $studyMinutes > 180) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Durasi belajar harus 1–180 menit.']);
            exit;
        }

        $validTaskId = null;
        if ($taskId > 0) {
            $check = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ? AND status != 'selesai'");
            $check->execute([$taskId, $userId]);
            if ($check->fetch()) {
                $validTaskId = $taskId;
            }
        }

        $_SESSION['fokusin_focus_session'] = [
            'token' => bin2hex(random_bytes(16)),
            'started_at' => time(),
            'study_minutes' => $studyMinutes,
            'label' => $label !== '' ? mb_substr($label, 0, 150) : 'Sesi Fokus',
            'task_id' => $validTaskId,
            'paused_at' => null,
            'total_paused_seconds' => 0,
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }

    /* Waktu yang dihabiskan dalam kondisi PAUSE tidak boleh terhitung
       sebagai waktu fokus aktif. Klien memberi tahu server kapan pause
       dan resume terjadi (bukan cuma UI lokal) supaya durasi akhir yang
       dihitung server benar-benar mencerminkan waktu aktif, bukan waktu
       sejak sesi dimulai. */
    if ($action === 'pause_session') {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['fokusin_focus_session'])) {
            http_response_code(404);
            echo json_encode(['success' => false]);
            exit;
        }
        // Idempotent: jika sudah dalam kondisi pause, permintaan pause lagi diabaikan.
        if (empty($_SESSION['fokusin_focus_session']['paused_at'])) {
            $_SESSION['fokusin_focus_session']['paused_at'] = time();
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'resume_session') {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['fokusin_focus_session'])) {
            http_response_code(404);
            echo json_encode(['success' => false]);
            exit;
        }
        // Idempotent: jika tidak sedang pause, permintaan resume diabaikan.
        if (!empty($_SESSION['fokusin_focus_session']['paused_at'])) {
            $pausedFor = max(0, time() - (int) $_SESSION['fokusin_focus_session']['paused_at']);
            $_SESSION['fokusin_focus_session']['total_paused_seconds'] =
                (int) ($_SESSION['fokusin_focus_session']['total_paused_seconds'] ?? 0) + $pausedFor;
            $_SESSION['fokusin_focus_session']['paused_at'] = null;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'save_session') {
        $serverSession = $_SESSION['fokusin_focus_session'] ?? null;
        unset($_SESSION['fokusin_focus_session']);

        if (!is_array($serverSession) || empty($serverSession['started_at'])) {
            setFlash('error', 'Sesi fokus tidak ditemukan atau sudah disimpan.');
            redirectTo('/focus.php');
        }

        $elapsedSeconds = max(0, time() - (int) $serverSession['started_at']);

        // Waktu pause tidak dihitung sebagai waktu fokus aktif. Jika sesi
        // masih dalam kondisi pause saat "Selesai" ditekan (tanpa resume
        // dulu), hitung juga sisa waktu pause yang sedang berjalan.
        $totalPausedSeconds = (int) ($serverSession['total_paused_seconds'] ?? 0);
        if (!empty($serverSession['paused_at'])) {
            $totalPausedSeconds += max(0, time() - (int) $serverSession['paused_at']);
        }
        $elapsedSeconds = max(0, $elapsedSeconds - $totalPausedSeconds);

        $plannedMinutes = max(1, min(180, (int) $serverSession['study_minutes']));
        $plannedSeconds = $plannedMinutes * 60;

        if ($elapsedSeconds < 30) {
            setFlash('error', 'Sesi terlalu singkat untuk disimpan.');
            redirectTo('/focus.php');
        }

        // Durasi yang dicatat adalah waktu server yang berlalu, dibatasi
        // maksimal durasi belajar yang dipilih saat sesi dimulai.
        $elapsedSeconds = min($elapsedSeconds, $plannedSeconds);
        $durationMinutes = max(1, min($plannedMinutes, (int) floor($elapsedSeconds / 60)));

        $validTaskId = !empty($serverSession['task_id']) ? (int) $serverSession['task_id'] : null;
        if ($validTaskId !== null) {
            $check = $pdo->prepare('SELECT id FROM tasks WHERE id = ? AND user_id = ?');
            $check->execute([$validTaskId, $userId]);
            if (!$check->fetch()) {
                $validTaskId = null;
            }
        }

        $label = (string) ($serverSession['label'] ?? 'Sesi Fokus');
        $startedAt = date('Y-m-d H:i:s', (int) $serverSession['started_at']);

        $stmt = $pdo->prepare(
            'INSERT INTO study_sessions (user_id, task_id, label, duration_minutes, started_at, ended_at, is_completed)
             VALUES (?, ?, ?, ?, ?, NOW(), 1)'
        );
        $stmt->execute([$userId, $validTaskId, $label, $durationMinutes, $startedAt]);

        $xpEarned = recordFocusSessionCompletion($pdo, $userId, $durationMinutes);

        redirectTo('/focus.php?done=1&minutes=' . $durationMinutes . '&xp=' . $xpEarned);
    }
}

/* -----------------------------------------------------------
   Data untuk panel setup: daftar tugas yang belum selesai +
   musik fokus yang tersedia.
----------------------------------------------------------- */
$stmt = $pdo->prepare(
    "SELECT id, title FROM tasks WHERE user_id = ? AND status != 'selesai' ORDER BY deadline IS NULL, deadline ASC LIMIT 30"
);
$stmt->execute([$userId]);
$openTasks = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM focus_music WHERE source_type = 'external_link' ORDER BY category, title");
$externalMusic = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM focus_music WHERE source_type = 'licensed_audio' ORDER BY category, title");
$licensedMusic = $stmt->fetchAll();

$csrfToken = generateCsrfToken();
$showCompleted = isset($_GET['done']);
$completedMinutes = (int) ($_GET['minutes'] ?? 0);
$completedXp = (int) ($_GET['xp'] ?? 0);

$pageTitle = 'Fokus';
$activeNav = 'fokus';
include __DIR__ . '/includes/header.php';
?>

<section class="focus-shell" id="focusShell" data-mode="<?= $showCompleted ? 'completed' : 'setup' ?>">

    <!-- ================= SETUP ================= -->
    <div class="focus-panel" id="panelSetup">
        <div class="panel">
            <div class="panel__head"><h2>Mulai Sesi Fokus</h2></div>

            <label for="setupLabel">Sedang belajar apa?</label>
            <input type="text" id="setupLabel" placeholder="Contoh: Belajar JavaScript" maxlength="150">

            <label for="setupTask">Kaitkan dengan tugas (opsional)</label>
            <select id="setupTask">
                <option value="0">Tidak ada</option>
                <?php foreach ($openTasks as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['title']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Durasi</label>
            <div class="focus-mode-chips">
                <button type="button" class="chip is-active" data-study="25" data-break="5">25 / 5</button>
                <button type="button" class="chip" data-study="50" data-break="10">50 / 10</button>
                <button type="button" class="chip" data-study="0" data-break="0" id="chipCustom">Custom</button>
            </div>

            <div class="focus-mode-custom" id="customDurationRow" style="display:none;">
                <div>
                    <label for="customStudy">Belajar (menit)</label>
                    <input type="number" id="customStudy" min="1" max="180" value="25">
                </div>
                <div>
                    <label for="customBreak">Istirahat (menit)</label>
                    <input type="number" id="customBreak" min="0" max="60" value="5">
                </div>
            </div>

            <button type="button" class="btn btn-primary btn-block" id="btnStartFocus" style="margin-top:16px;">Mulai Fokus</button>
        </div>

        <?php if (!empty($externalMusic) || !empty($licensedMusic)): ?>
        <div class="panel">
            <div class="panel__head">
                <h2>🎵 Musik Fokus</h2>
                <a href="<?= BASE_URL ?>/focus_music.php" class="panel__link">⚙️ Kelola Musik</a>
            </div>

            <?php if (!empty($licensedMusic)): ?>
                <p class="focus-music__hint">Audio bawaan (royalti bebas):</p>
                <div class="focus-music-list">
                    <?php foreach ($licensedMusic as $m): ?>
                        <button type="button" class="focus-music-item" data-src="<?= e($m['url_or_path']) ?>" data-title="<?= e($m['title']) ?>">
                            🎵 <?= e($m['title']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($externalMusic)): ?>
                <p class="focus-music__hint">Buka musik dari layanan eksternal (tidak diunduh, hanya tautan):</p>
                <div class="focus-music-list">
                    <?php foreach ($externalMusic as $m): ?>
                        <a href="<?= e($m['url_or_path']) ?>" target="_blank" rel="noopener" class="focus-music-item">
                            🎵 <?= e($m['title']) ?> ↗
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ================= RUNNING ================= -->
    <div class="focus-panel focus-running" id="panelRunning" style="display:none;">
        <a href="<?= BASE_URL ?>/dashboard.php" class="focus-running__exit" id="btnExitFocus">← Keluar dari Fokus</a>

        <div class="focus-running__brand">Fokusin</div>
        <div class="focus-running__timer" id="timerDisplay">25:00</div>
        <div class="focus-running__label" id="runningLabel">Belajar</div>
        <div class="focus-running__phase" id="runningPhase">Fokus</div>

        <audio id="ambientPlayer" loop></audio>
        <div class="focus-running__music" id="runningMusicNote" style="display:none;">
            🎵 <span id="runningMusicTitle"></span>
            <input type="range" id="volumeSlider" min="0" max="100" value="60">
        </div>

        <div class="focus-running__controls">
            <button type="button" class="btn btn-secondary" id="btnPauseResume">Pause</button>
            <button type="button" class="btn btn-primary" id="btnFinishNow">Selesai</button>
        </div>

        <p class="focus-running__session-info">Sesi: <span id="runningTotalMinutes">25</span> menit</p>
    </div>

    <!-- ================= COMPLETED ================= -->
    <div class="focus-panel focus-completed" id="panelCompleted" style="<?= $showCompleted ? '' : 'display:none;' ?>">
        <div class="panel focus-completed__card">
            <span class="focus-completed__emoji">🎉</span>
            <h2>Sesi fokus selesai!</h2>
            <p class="focus-completed__duration">Durasi: <strong><?= $completedMinutes ?> menit</strong></p>
            <p class="focus-completed__xp">+<?= $completedXp ?> XP</p>
            <div class="focus-completed__actions">
                <a href="<?= BASE_URL ?>/focus.php" class="btn btn-secondary">Sesi Lagi</a>
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary">Ke Dashboard</a>
            </div>
        </div>
    </div>
</section>

<!-- Form tersembunyi untuk menyimpan sesi ke server -->
<form method="POST" id="saveSessionForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="action" value="save_session">
    <input type="hidden" name="label" id="save_label">
    <input type="hidden" name="task_id" id="save_task_id">
    <input type="hidden" name="duration_minutes" id="save_duration_minutes">
    <input type="hidden" name="started_at" id="save_started_at">
</form>

<script>
(function () {
    var setupPanel = document.getElementById('panelSetup');
    var runningPanel = document.getElementById('panelRunning');
    var completedPanel = document.getElementById('panelCompleted');

    var chips = document.querySelectorAll('.chip');
    var customRow = document.getElementById('customDurationRow');
    var studyMinutes = 25, breakMinutes = 5;

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('is-active'); });
            chip.classList.add('is-active');

            if (chip.id === 'chipCustom') {
                customRow.style.display = 'grid';
                studyMinutes = parseInt(document.getElementById('customStudy').value, 10) || 25;
                breakMinutes = parseInt(document.getElementById('customBreak').value, 10) || 5;
            } else {
                customRow.style.display = 'none';
                studyMinutes = parseInt(chip.getAttribute('data-study'), 10);
                breakMinutes = parseInt(chip.getAttribute('data-break'), 10);
            }
        });
    });

    document.getElementById('customStudy').addEventListener('input', function () {
        studyMinutes = parseInt(this.value, 10) || 1;
    });
    document.getElementById('customBreak').addEventListener('input', function () {
        breakMinutes = parseInt(this.value, 10) || 0;
    });

    // ---- Music selection (local ambient, jika ada) ----
    var ambientPlayer = document.getElementById('ambientPlayer');
    var runningMusicNote = document.getElementById('runningMusicNote');
    var runningMusicTitle = document.getElementById('runningMusicTitle');
    var selectedMusicSrc = null, selectedMusicTitle = null;

    document.querySelectorAll('.focus-music-item[data-src]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectedMusicSrc = this.getAttribute('data-src');
            selectedMusicTitle = this.getAttribute('data-title');
            document.querySelectorAll('.focus-music-item[data-src]').forEach(function (b) { b.classList.remove('is-active'); });
            this.classList.add('is-active');
        });
    });

    document.getElementById('volumeSlider').addEventListener('input', function () {
        ambientPlayer.volume = this.value / 100;
    });

    // ---- Timer state ----
    var phase = 'focus'; // 'focus' | 'break'
    var totalSeconds = 0, remainingSeconds = 0, endTimestamp = null, intervalId = null, paused = false;
    var sessionStartedAt = null, sessionLabel = 'Sesi Fokus', sessionTaskId = 0, sessionStudyMinutes = 25;

    function formatTime(sec) {
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
    }

    function tick() {
        var now = Date.now();
        remainingSeconds = Math.max(0, Math.round((endTimestamp - now) / 1000));
        document.getElementById('timerDisplay').textContent = formatTime(remainingSeconds);

        if (remainingSeconds <= 0) {
            clearInterval(intervalId);
            if (phase === 'focus') {
                if (breakMinutes > 0) {
                    startBreakPhase();
                } else {
                    finishSession();
                }
            } else {
                // istirahat selesai -> sesi belajar dianggap tuntas
                finishSession();
            }
        }
    }

    function startBreakPhase() {
        phase = 'break';
        document.getElementById('runningPhase').textContent = 'Istirahat';
        totalSeconds = breakMinutes * 60;
        remainingSeconds = totalSeconds;
        endTimestamp = Date.now() + totalSeconds * 1000;
        intervalId = setInterval(tick, 500);
    }

    async function startTimer() {
        sessionLabel = document.getElementById('setupLabel').value.trim() || 'Sesi Fokus';
        sessionTaskId = parseInt(document.getElementById('setupTask').value, 10) || 0;
        sessionStudyMinutes = Math.max(1, Math.min(180, parseInt(studyMinutes, 10) || 25));

        var formData = new URLSearchParams();
        formData.append('csrf_token', <?= json_encode($csrfToken) ?>);
        formData.append('action', 'start_session');
        formData.append('label', sessionLabel);
        formData.append('task_id', sessionTaskId);
        formData.append('study_minutes', sessionStudyMinutes);

        var startButton = document.getElementById('btnStartFocus');
        startButton.disabled = true;

        try {
            var response = await fetch('<?= BASE_URL ?>/focus.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: formData.toString(),
                credentials: 'same-origin'
            });
            var result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Gagal memulai sesi.');
            }
        } catch (error) {
            startButton.disabled = false;
            alert(error.message || 'Gagal memulai sesi. Silakan coba lagi.');
            return;
        }

        sessionStartedAt = new Date();
        document.getElementById('runningLabel').textContent = sessionLabel;
        document.getElementById('runningTotalMinutes').textContent = sessionStudyMinutes;
        document.getElementById('runningPhase').textContent = 'Fokus';

        if (selectedMusicSrc) {
            ambientPlayer.src = selectedMusicSrc;
            ambientPlayer.volume = document.getElementById('volumeSlider').value / 100;
            ambientPlayer.play().catch(function () {});
            runningMusicNote.style.display = 'flex';
            runningMusicTitle.textContent = selectedMusicTitle;
        }

        phase = 'focus';
        totalSeconds = sessionStudyMinutes * 60;
        remainingSeconds = totalSeconds;
        endTimestamp = Date.now() + totalSeconds * 1000;
        paused = false;
        document.getElementById('btnPauseResume').textContent = 'Pause';

        setupPanel.style.display = 'none';
        runningPanel.style.display = 'flex';
        intervalId = setInterval(tick, 500);
    }

    function finishSession(manual) {
        clearInterval(intervalId);
        ambientPlayer.pause();

        // Hanya simpan durasi fase belajar (bukan istirahat) yang benar-benar terjadi.
        var elapsedStudySeconds;
        if (phase === 'focus') {
            elapsedStudySeconds = totalSeconds - remainingSeconds;
        } else {
            elapsedStudySeconds = sessionStudyMinutes * 60; // fase belajar sudah tuntas sebelum masuk istirahat
        }
        var minutes = Math.max(1, Math.round(elapsedStudySeconds / 60));

        document.getElementById('save_label').value = sessionLabel;
        document.getElementById('save_task_id').value = sessionTaskId;
        document.getElementById('save_duration_minutes').value = minutes;
        document.getElementById('save_started_at').value = sessionStartedAt ? sessionStartedAt.toISOString() : '';
        document.getElementById('saveSessionForm').submit();
    }

    document.getElementById('btnStartFocus').addEventListener('click', startTimer);

    /* Beri tahu server kapan pause/resume terjadi supaya waktu pause bisa
       dikeluarkan dari perhitungan durasi aktif saat sesi disimpan. Dikirim
       di background (tidak menghambat UI) -- jika request ini gagal karena
       jaringan, kasus terburuknya waktu pause tetap terhitung seperti
       sebelumnya (tidak lebih buruk dari sebelum perbaikan ini). */
    function postFocusAction(action) {
        var formData = new URLSearchParams();
        formData.append('csrf_token', <?= json_encode($csrfToken) ?>);
        formData.append('action', action);
        fetch('<?= BASE_URL ?>/focus.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: formData.toString(),
            credentials: 'same-origin'
        }).catch(function () {});
    }

    document.getElementById('btnPauseResume').addEventListener('click', function () {
        if (!paused) {
            clearInterval(intervalId);
            paused = true;
            this.textContent = 'Resume';
            postFocusAction('pause_session');
        } else {
            endTimestamp = Date.now() + remainingSeconds * 1000;
            intervalId = setInterval(tick, 500);
            paused = false;
            this.textContent = 'Pause';
            postFocusAction('resume_session');
        }
    });

    document.getElementById('btnFinishNow').addEventListener('click', function () {
        // Minta konfirmasi hanya jika waktu belajar yang berjalan masih sangat singkat.
        var elapsed = totalSeconds - remainingSeconds;
        if (phase === 'focus' && elapsed < 30) {
            if (!confirm('Sesi baru berjalan sebentar. Selesaikan sekarang?')) return;
        }
        finishSession(true);
    });

    document.getElementById('btnExitFocus').addEventListener('click', function (e) {
        if (runningPanel.style.display !== 'none') {
            if (!confirm('Keluar tanpa menyimpan sesi ini?')) {
                e.preventDefault();
            } else {
                clearInterval(intervalId);
                ambientPlayer.pause();
            }
        }
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
