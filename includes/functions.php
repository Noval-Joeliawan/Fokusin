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

    $stmt = $pdo->prepare('SELECT xp FROM user_progress WHERE user_id = ?');
    $stmt->execute([$userId]);
    $newXp = (int) $stmt->fetchColumn() + $amount;
    $newLevel = levelFromXp($newXp);

    $stmt = $pdo->prepare('UPDATE user_progress SET xp = ?, level = ? WHERE user_id = ?');
    $stmt->execute([$newXp, $newLevel, $userId]);

    return $newXp;
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
