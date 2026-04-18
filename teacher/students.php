<?php
require_once 'includes/init.php';
$activePage = 'students';

// Fetch students from database (server-side)
$students = [];
$error = null;

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.full_name, s.nisn, s.class, s.is_active 
        FROM students s
        JOIN exams e ON e.class = s.class
        WHERE e.teacher_id = ?
        ORDER BY s.class ASC, s.full_name ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $students = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("[students.php] Database error: " . $e->getMessage());
    $error = "Gagal memuat data siswa. Silakan coba lagi.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Data Siswa — ExamSafe</title>
    <link rel="stylesheet" href="../css/style.css" />
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <div>
                <div class="page-title">👥 Data Siswa</div>
                <div class="page-subtitle">
                    Daftar siswa di kelas yang Anda ampu
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Lengkap</th>
                            <th>NISN</th>
                            <th>Kelas</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($error): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;color:var(--danger);padding:20px">
                                    <?php echo htmlspecialchars($error); ?>
                                 </td>
                            </tr>
                        <?php elseif (empty($students)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:20px;color:#64748b">
                                    Belum ada data siswa di kelas Anda.
                                 </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td style="vertical-align:middle"><?php echo $index + 1; ?></td>
                                    <td style="vertical-align:middle"><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                    <td style="vertical-align:middle"><?php echo htmlspecialchars($student['nisn']); ?></td>
                                    <td style="vertical-align:middle"><?php echo htmlspecialchars($student['class']); ?></td>
                                    <td style="vertical-align:middle">
                                        <span class="badge badge-success">
                                            <?php echo $student['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    </div>

    <script src="../js/teacher-layout.js"></script>
</body>
</html>