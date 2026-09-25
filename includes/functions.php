<?php
/**
 * Fungsi-fungsi bantuan yang dipakai di seluruh aplikasi.
 */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function redirectTo(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Sapaan otomatis berdasarkan jam saat ini.
 */
function greetingByTime(): string
{
    $hour = (int) date('G');
    if ($hour < 10) return 'Selamat pagi';
    if ($hour < 15) return 'Selamat siang';
    if ($hour < 18) return 'Selamat sore';
    return 'Selamat malam';
}

function formatMinutesToHours(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h === 0) {
        return $m . ' Menit';
    }
    return $h . ' Jam ' . $m . ' Menit';
}

function formatDeadline(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }

    $deadline = new DateTime($datetime);
    $today = new DateTime('today');
    $diffDays = (int) $today->diff($deadline)->format('%r%a');

    if ($deadline < $today) {
        return 'Terlewat (' . $deadline->format('d M') . ')';
    }
    if ($diffDays === 0) return 'Hari ini';
    if ($diffDays === 1) return 'Besok';

    return $deadline->format('d M Y');
}

function priorityLabel(string $priority): string
{
    return match ($priority) {
        'tinggi' => 'Tinggi',
        'sedang' => 'Sedang',
        'rendah' => 'Rendah',
        default  => ucfirst($priority),
    };
}

function statusLabel(string $status): string
{
    return match ($status) {
        'belum_selesai'     => 'Belum selesai',
        'sedang_dikerjakan' => 'Sedang dikerjakan',
        'selesai'           => 'Selesai',
        default             => $status,
    };
}

/**
 * Apakah sebuah task sudah lewat deadline?
 * Task tanpa deadline TIDAK PERNAH dianggap overdue.
 */
function isTaskOverdue(?string $deadline, string $status): bool
{
    if (!$deadline || $status === 'selesai') {
        return false;
    }
    return new DateTime($deadline) < new DateTime();
}

/**
 * Cari subject milik user (atau subject global) berdasarkan nama;
 * buat baru jika belum ada. Dipakai oleh form Tugas & Planner supaya
 * pengguna bisa mengetik nama mapel langsung tanpa halaman kelola mapel.
 */
function findOrCreateSubject(PDO $pdo, int $userId, ?string $name): ?int
{
    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM subjects WHERE (created_by = ? OR created_by IS NULL) AND LOWER(name) = LOWER(?) LIMIT 1'
    );
    $stmt->execute([$userId, $name]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $stmt = $pdo->prepare('INSERT INTO subjects (name, created_by) VALUES (?, ?)');
    $stmt->execute([$name, $userId]);
    return (int) $pdo->lastInsertId();
}

/**
 * Daftar mapel yang bisa dipilih user (miliknya sendiri + mapel global).
 */
function getUserSubjects(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, name FROM subjects WHERE created_by = ? OR created_by IS NULL ORDER BY name ASC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Level sederhana berdasarkan XP. Formula ringan, mudah diubah nanti.
 */
function levelFromXp(int $xp): int
{
    return intdiv($xp, 500) + 1;
}

/**
 * Perbarui streak harian user. Dipanggil setiap kali ada aktivitas belajar
 * yang "berarti" (sesi fokus selesai, tugas selesai, dst).
 * Tidak menambah streak dua kali di hari yang sama.
 */
function touchStreak(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('SELECT current_streak, longest_streak, last_activity_date FROM user_progress WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $today = new DateTime('today');
    $last = $row['last_activity_date'] ? new DateTime($row['last_activity_date']) : null;

    if ($last !== null && $last->format('Y-m-d') === $today->format('Y-m-d')) {
        return; // sudah tercatat hari ini
    }

    if ($last !== null && (int) $today->diff($last)->format('%a') === 1) {
        $newStreak = (int) $row['current_streak'] + 1;
    } else {
        $newStreak = 1; // hari pertama atau streak putus
    }

    $newLongest = max($newStreak, (int) $row['longest_streak']);

    $stmt = $pdo->prepare(
        'UPDATE user_progress SET current_streak = ?, longest_streak = ?, last_activity_date = CURDATE() WHERE user_id = ?'
    );
    $stmt->execute([$newStreak, $newLongest, $userId]);
}

/**
 * Tambah XP ke user, hitung ulang level, dan perbarui streak.
 * Ini adalah "hook" XP yang disiapkan di Fase 2 — pemakaian penuh
 * (achievement, level-up modal, dst) menyusul di Fase 4.
 */
function addXp(PDO $pdo, int $userId, int $amount): int
{
    ensureUserProgressRow($userId);
    touchStreak($pdo, $userId);

    $stmt = $pdo->prepare('SELECT xp, level FROM user_progress WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch() ?: ['xp' => 0, 'level' => 1];

    $newXp = max(0, (int) $row['xp'] + max(0, $amount));
    $oldLevel = (int) $row['level'];
    $newLevel = levelFromXp($newXp);

    $stmt = $pdo->prepare('UPDATE user_progress SET xp = ?, level = ? WHERE user_id = ?');
    $stmt->execute([$newXp, $newLevel, $userId]);

    if ($newLevel > $oldLevel) {
        $_SESSION['level_up'] = ['from' => $oldLevel, 'to' => $newLevel];
    }

    checkAndUnlockAchievements($pdo, $userId);
    return $newXp;
}

/**
 * Cek pencapaian user dan membuka achievement yang syaratnya sudah terpenuhi.
 * INSERT bersifat idempotent melalui UNIQUE(user_id, achievement_id).
 */
function checkAndUnlockAchievements(PDO $pdo, int $userId): array
{
    ensureUserProgressRow($userId);

    $stmt = $pdo->prepare('SELECT * FROM user_progress WHERE user_id = ?');
    $stmt->execute([$userId]);
    $progress = $stmt->fetch() ?: [];

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notes WHERE user_id = ?');
    $stmt->execute([$userId]);
    $notesCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ?');
    $stmt->execute([$userId]);
    $quizAttempts = (int) $stmt->fetchColumn();

    $conditions = [
        'first_focus' => (int) ($progress['focus_sessions_completed'] ?? 0) >= 1,
        'focus_60'    => (int) ($progress['total_study_minutes'] ?? 0) >= 60,
        'task_5'      => (int) ($progress['tasks_completed'] ?? 0) >= 5,
        'quiz_3'      => $quizAttempts >= 3,
        'material_3'  => (int) ($progress['materials_completed'] ?? 0) >= 3,
        'notes_5'     => $notesCount >= 5,
        'streak_7'    => (int) ($progress['longest_streak'] ?? 0) >= 7,
        'level_5'     => (int) ($progress['level'] ?? 1) >= 5,
    ];

    $stmt = $pdo->query('SELECT id, code, name FROM achievements');
    $achievements = $stmt->fetchAll();
    $newlyUnlocked = [];

    foreach ($achievements as $achievement) {
        if (empty($conditions[$achievement['code']])) {
            continue;
        }

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)'
        );
        $insert->execute([$userId, $achievement['id']]);
        if ($insert->rowCount() === 1) {
            $newlyUnlocked[] = $achievement;
        }
    }

    return $newlyUnlocked;
}

function getUserAchievements(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT a.*, ua.unlocked_at FROM achievements a
         LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ?
         ORDER BY ua.unlocked_at IS NULL ASC, ua.unlocked_at DESC, a.id ASC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}


/**
 * Catat penyelesaian sesi fokus: tambah menit belajar, hitung sesi,
 * berikan XP, perbarui streak. Dipanggil dari focus.php saat sesi selesai.
 */
function recordFocusSessionCompletion(PDO $pdo, int $userId, int $durationMinutes): int
{
    ensureUserProgressRow($userId);

    $stmt = $pdo->prepare(
        'UPDATE user_progress
         SET total_study_minutes = total_study_minutes + ?,
             focus_sessions_completed = focus_sessions_completed + 1
         WHERE user_id = ?'
    );
    $stmt->execute([$durationMinutes, $userId]);

    $xpEarned = max(5, $durationMinutes); // 1 XP per menit, minimal 5
    addXp($pdo, $userId, $xpEarned);

    return $xpEarned;
}

/**
 * Catat penyelesaian tugas: tambah counter & XP. Hanya dipanggil saat
 * status BERUBAH menjadi selesai (bukan setiap update biasa).
 */
function recordTaskCompletion(PDO $pdo, int $userId): int
{
    ensureUserProgressRow($userId);

    $stmt = $pdo->prepare(
        'UPDATE user_progress SET tasks_completed = tasks_completed + 1 WHERE user_id = ?'
    );
    $stmt->execute([$userId]);

    $xpEarned = 10;
    addXp($pdo, $userId, $xpEarned);

    return $xpEarned;
}

/**
 * Catat penyelesaian kuis (Fase 3): tambah counter & XP, perbarui streak.
 * Dipanggil SETIAP kali attempt baru berhasil disimpan — beda dari materi,
 * kuis boleh diulang dan tiap attempt yang selesai dihitung & diberi XP
 * (skor dipakai untuk menentukan besarnya XP, bukan untuk gate on/off).
 */
function recordQuizCompletion(PDO $pdo, int $userId, float $scorePercent): int
{
    ensureUserProgressRow($userId);

    $stmt = $pdo->prepare(
        'UPDATE user_progress SET quizzes_completed = quizzes_completed + 1 WHERE user_id = ?'
    );
    $stmt->execute([$userId]);

    // 5 XP dasar + hingga 20 XP tambahan sesuai skor (0-100%).
    $xpEarned = max(5, (int) round(5 + ($scorePercent / 100) * 20));
    addXp($pdo, $userId, $xpEarned);

    return $xpEarned;
}

/**
 * Catat penyelesaian materi (Fase 3): tambah counter & XP, perbarui streak.
 * PENTING: idempotent — hanya dipanggil oleh materials.php SETELAH baris
 * baru berhasil dimasukkan ke material_completions (INSERT yang gagal
 * karena sudah ada / duplicate tidak akan memanggil fungsi ini), sehingga
 * XP tidak mungkin dobel untuk materi yang sama.
 */
function recordMaterialCompletion(PDO $pdo, int $userId): int
{
    ensureUserProgressRow($userId);

    $stmt = $pdo->prepare(
        'UPDATE user_progress SET materials_completed = materials_completed + 1 WHERE user_id = ?'
    );
    $stmt->execute([$userId]);

    $xpEarned = 8;
    addXp($pdo, $userId, $xpEarned);

    return $xpEarned;
}

/**
 * Potong teks untuk pratinjau (misalnya isi catatan di daftar), tanpa
 * memotong di tengah kata jika memungkinkan.
 */
function truncateText(string $text, int $maxLength = 120): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }
    return mb_substr($text, 0, $maxLength) . '…';
}

/* =============================================================
   FASE 5 — Upload resource yang aman
============================================================= */

/**
 * Ekstensi yang diizinkan -> MIME type yang harus cocok. Dipakai untuk
 * validasi ganda: ekstensi file (dari nama asli) DAN MIME sungguhan
 * (dibaca dari isi file, bukan dipercaya dari header upload) harus
 * SAMA-SAMA ada dalam daftar ini dan konsisten satu sama lain.
 */
const RESOURCE_ALLOWED_TYPES = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'ppt'  => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'xls'  => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'txt'  => ['text/plain'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
];

const RESOURCE_MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

/**
 * Validasi file yang diupload secara berlapis: error upload, ukuran,
 * ekstensi nama asli, DAN mime type sungguhan (dibaca dari konten file
 * lewat finfo, bukan dari Content-Type yang dikirim browser -- yang
 * bisa dipalsukan). Tidak pernah mempercayai nama file asli untuk
 * penyimpanan. Mengembalikan ['ok' => bool, 'error' => string|null,
 * 'ext' => string|null, 'mime' => string|null].
 */
function validateUploadedResourceFile(array $file): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Tidak ada file yang dipilih.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas formulir.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian. Coba lagi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server tidak siap menerima upload. Coba lagi nanti.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menyimpan file di server.',
        ];
        return ['ok' => false, 'error' => $messages[$file['error']] ?? 'Upload gagal. Silakan coba lagi.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Upload tidak valid.'];
    }
    if ($file['size'] > RESOURCE_MAX_FILE_SIZE) {
        return ['ok' => false, 'error' => 'Ukuran file maksimal 10 MB.'];
    }

    $originalName = $file['name'] ?? '';
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '' || !isset(RESOURCE_ALLOWED_TYPES[$ext])) {
        return ['ok' => false, 'error' => 'Jenis file tidak didukung. Gunakan PDF, DOC(X), PPT(X), XLS(X), TXT, atau gambar (JPG/PNG/WEBP).'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actualMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!$actualMime || !in_array($actualMime, RESOURCE_ALLOWED_TYPES[$ext], true)) {
        return ['ok' => false, 'error' => 'Isi file tidak sesuai dengan jenis ekstensinya. File ditolak untuk keamanan.'];
    }

    return ['ok' => true, 'error' => null, 'ext' => $ext, 'mime' => $actualMime];
}

/**
 * Nama file acak yang aman untuk disimpan di disk. Nama asli dari
 * pengguna TIDAK PERNAH dipakai sebagai nama file fisik (hanya
 * disimpan sebagai teks/metadata di database untuk ditampilkan).
 */
function generateSafeStoredFilename(string $ext): string
{
    return bin2hex(random_bytes(16)) . '.' . $ext;
}

/**
 * Bersihkan nama file asli sebelum dipakai di header Content-Disposition
 * (mencegah header injection lewat CRLF/karakter kontrol) dan sebelum
 * ditampilkan di UI.
 */
function sanitizeDisplayFilename(string $name): string
{
    $name = preg_replace('/[\x00-\x1F\x7F"\r\n]/', '', $name);
    $name = trim($name);
    return $name !== '' ? $name : 'file';
}

function formatFileSizeKb(?int $sizeKb): string
{
    if (!$sizeKb) {
        return '-';
    }
    if ($sizeKb >= 1024) {
        return round($sizeKb / 1024, 1) . ' MB';
    }
    return $sizeKb . ' KB';
}

/**
 * Validasi sederhana untuk URL musik/resource eksternal: harus http/https
 * yang well-formed. Mengembalikan URL yang sudah divalidasi atau null.
 */
function validateExternalUrl(string $url): ?string
{
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }
    return $url;
}
