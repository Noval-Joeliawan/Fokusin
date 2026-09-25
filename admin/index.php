<?php
require_once __DIR__ . '/_bootstrap.php';

$stats = [];
$queries = [
    'users_total' => 'SELECT COUNT(*) FROM users',
    'students' => "SELECT COUNT(*) FROM users WHERE role = 'student'",
    'admins' => "SELECT COUNT(*) FROM users WHERE role = 'admin'",
    'resources' => 'SELECT COUNT(*) FROM resources',
    'music' => 'SELECT COUNT(*) FROM focus_music',
    'tasks_completed' => "SELECT COUNT(*) FROM tasks WHERE status = 'selesai'",
    'study_minutes' => 'SELECT COALESCE(SUM(total_study_minutes), 0) FROM user_progress',
];
foreach ($queries as $key => $sql) {
    $stats[$key] = (int) $pdo->query($sql)->fetchColumn();
}

adminPageStart('Dashboard Admin', 'dashboard');
?>
<div class="admin-grid admin-grid--stats">
    <div class="admin-stat"><span>👥</span><strong><?= $stats['users_total'] ?></strong><small>Total Pengguna</small></div>
    <div class="admin-stat"><span>🎓</span><strong><?= $stats['students'] ?></strong><small>Student</small></div>
    <div class="admin-stat"><span>🛠️</span><strong><?= $stats['admins'] ?></strong><small>Admin</small></div>
    <div class="admin-stat"><span>📂</span><strong><?= $stats['resources'] ?></strong><small>Resource</small></div>
    <div class="admin-stat"><span>🎧</span><strong><?= $stats['music'] ?></strong><small>Focus Music</small></div>
    <div class="admin-stat"><span>✅</span><strong><?= $stats['tasks_completed'] ?></strong><small>Tugas Selesai</small></div>
    <div class="admin-stat"><span>⏱️</span><strong><?= e(formatMinutesToHours($stats['study_minutes'])) ?></strong><small>Total Waktu Fokus</small></div>
</div>

<section class="panel admin-panel">
    <div class="panel__head">
        <div>
            <h2>Kelola Fokusin</h2>
            <p class="panel__sub">Phase 6 MVP menyediakan pengelolaan pengguna, resource, dan focus music.</p>
        </div>
    </div>
    <div class="admin-quick-grid">
        <a class="admin-quick" href="<?= e(adminUrl('users.php')) ?>"><span>👥</span><strong>Pengguna</strong><small>Atur role dan cari akun.</small></a>
        <a class="admin-quick" href="<?= e(adminUrl('resources.php')) ?>"><span>📂</span><strong>Resource</strong><small>Kelola tautan dan file belajar.</small></a>
        <a class="admin-quick" href="<?= e(adminUrl('music.php')) ?>"><span>🎧</span><strong>Focus Music</strong><small>Kelola musik yang tersedia di Fokus Mode.</small></a>
    </div>
</section>
<?php adminPageEnd(); ?>
