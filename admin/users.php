<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Sesi form tidak valid. Silakan coba lagi.');
        redirectTo('/admin/users.php');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'change_role') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? '';
        if ($targetId <= 0 || !in_array($newRole, ['student', 'admin'], true)) {
            setFlash('error', 'Data perubahan role tidak valid.');
            redirectTo('/admin/users.php');
        }

        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if (!$target) {
            setFlash('error', 'Pengguna tidak ditemukan.');
            redirectTo('/admin/users.php');
        }

        if ((int) $target['id'] === (int) $adminUser['id'] && $newRole !== 'admin') {
            setFlash('error', 'Kamu tidak dapat menurunkan role akunmu sendiri dari admin.');
            redirectTo('/admin/users.php');
        }

        if ($target['role'] === 'admin' && $newRole === 'student') {
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($adminCount <= 1) {
                setFlash('error', 'Minimal harus ada satu admin aktif.');
                redirectTo('/admin/users.php');
            }
        }

        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$newRole, $targetId]);
        setFlash('success', 'Role pengguna berhasil diperbarui.');
        redirectTo('/admin/users.php');
    }
}

$q = trim($_GET['q'] ?? '');
$role = in_array($_GET['role'] ?? '', ['student', 'admin'], true) ? $_GET['role'] : '';
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($role !== '') {
    $where[] = 'role = ?';
    $params[] = $role;
}
$sql = 'SELECT id, name, email, role, is_active, created_at FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

adminPageStart('Pengguna', 'users');
?>
<section class="panel admin-panel">
    <div class="panel__head">
        <div>
            <h2>Daftar Pengguna</h2>
            <p class="panel__sub"><?= count($users) ?> hasil ditampilkan.</p>
        </div>
    </div>

    <form method="GET" class="admin-filters">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama atau email...">
        <select name="role">
            <option value="">Semua Role</option>
            <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>

    <?php if (!$users): ?>
        <div class="empty-state"><span class="empty-state__icon">👥</span><p>Tidak ada pengguna yang cocok.</p></div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Bergabung</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?= e($u['name']) ?></strong></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="admin-badge <?= $u['role'] === 'admin' ? 'admin-badge--primary' : '' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
                        <td><span class="admin-badge <?= (int) $u['is_active'] === 1 ? 'admin-badge--success' : 'admin-badge--muted' ?>"><?= (int) $u['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <td><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                        <td>
                            <form method="POST" class="admin-inline-form">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="change_role">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <select name="role" aria-label="Ubah role <?= e($u['name']) ?>">
                                    <option value="student" <?= $u['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                                <button class="btn btn-secondary" type="submit">Simpan</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php adminPageEnd(); ?>
