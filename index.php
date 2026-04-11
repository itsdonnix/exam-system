<?php
// Add error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure no output before session starts
ob_start();

session_start();

// Debug: Check if session ID exists
// if (session_status() === PHP_SESSION_ACTIVE) {
//     error_log("Session active. ID: " . session_id());
// } else {
//     error_log("Session NOT active");
// }

require_once 'includes/auth.php';
require_once 'includes/csrf.php';
require_once 'php/db.php';

// Check if already logged in
if (isLoggedIn() && !isSessionExpired(3600)) {
    $redirect = match ($_SESSION['role']) {
        'siswa' => 'student/dashboard.php',
        'guru' => 'teacher/dashboard.html',
        'admin' => 'admin/dashboard.html',
        default => 'index.php'
    };
    header("Location: $redirect");
    exit;
}
// elseif (isSessionExpired(3600)) {
// clearSession();
// }

// Handle login form submission
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '', $_SESSION['csrf_token'])) {
        $error = 'Token keamanan tidak valid. Silakan refresh halaman.';
    } else {
        $role = $_POST['role'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;

        if (empty($role) || empty($username) || empty($password)) {
            $error = 'Semua field harus diisi.';
        } else {
            try {
                $db = getDB();

                // Rate limiting check
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
                $stmt->execute([$ip]);
                $attempts = $stmt->fetch()['cnt'];

                if ($attempts >= 5) {
                    $error = 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.';
                } else {
                    // Find user
                    $table = match ($role) {
                        'siswa' => 'students',
                        'guru' => 'teachers',
                        'admin' => 'admins',
                        default => null
                    };

                    if (!$table) {
                        $error = 'Role tidak valid';
                    } else {
                        if ($role === 'guru') {
                            $stmt = $db->prepare("SELECT * FROM teachers WHERE (nip = ? OR email = ?) AND is_active = 1 LIMIT 1");
                            $stmt->execute([$username, $username]);
                        } elseif ($role === 'siswa') {
                            $stmt = $db->prepare("SELECT * FROM students WHERE (username = ? OR nisn = ? OR email = ?) AND is_active = 1 LIMIT 1");
                            $stmt->execute([$username, $username, $username]);
                        } else {
                            $stmt = $db->prepare("SELECT * FROM admins WHERE (username = ? OR email = ?) AND is_active = 1 LIMIT 1");
                            $stmt->execute([$username, $username]);
                        }

                        $user = $stmt->fetch();

                        if (!$user || !password_verify($password, $user['password'])) {
                            // Log failed attempt
                            $db->prepare("INSERT INTO login_attempts (ip, username, created_at) VALUES (?, ?, NOW())")->execute([$ip, $username]);
                            $error = 'Username atau password salah';
                        } else {
                            // Check approval for teachers
                            if ($role === 'guru' && $user['approval_status'] !== 'approved') {
                                $error = 'Akun Anda belum disetujui admin. Harap tunggu verifikasi.';
                            } else {
                                // Clear login attempts
                                $db->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);

                                // Set session
                                setSession($user, $role);

                                // Handle Remember Me
                                if ($remember) {
                                    $token = bin2hex(random_bytes(32));
                                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

                                    $stmt = $db->prepare("INSERT INTO user_tokens (user_id, role, token, expires_at) VALUES (?, ?, ?, ?)");
                                    $stmt->execute([$user['id'], $role, $token, $expires]);

                                    setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
                                }

                                // Redirect based on role
                                $redirect = match ($role) {
                                    'siswa' => 'student/dashboard.php',
                                    'guru' => 'teacher/dashboard.html',
                                    'admin' => 'admin/dashboard.html',
                                };

                                $_SESSION['success'] = 'Login berhasil!';
                                header("Location: $redirect");
                                exit;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }
}

// Check for Remember Me cookie
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    try {
        $db = getDB();
        $token = $_COOKIE['remember_token'];
        $stmt = $db->prepare("SELECT * FROM user_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();

        if ($tokenData) {
            // Get user data
            $table = $tokenData['role'] === 'siswa' ? 'students' : ($tokenData['role'] === 'guru' ? 'teachers' : 'admins');
            $stmt = $db->prepare("SELECT * FROM $table WHERE id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$tokenData['user_id']]);
            $user = $stmt->fetch();

            if ($user) {
                setSession($user, $tokenData['role']);

                $redirect = match ($tokenData['role']) {
                    'siswa' => 'student/dashboard.php',
                    'guru' => 'teacher/dashboard.html',
                    'admin' => 'admin/dashboard.html',
                };
                header("Location: $redirect");
                exit;
            } else {
                // Invalid user, delete token
                $db->prepare("DELETE FROM user_tokens WHERE token = ?")->execute([$token]);
                setcookie('remember_token', '', time() - 3600, '/');
            }
        }
    } catch (Exception $e) {
        error_log("Remember Me error: " . $e->getMessage());
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ExamSafe - Sistem Ujian Online SMA</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a3c6e 0%, #2563eb 50%, #0ea5e9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            width: 100%;
            max-width: 460px;
            padding: 20px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo .logo-icon {
            width: 72px;
            height: 72px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin: 0 auto 14px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .login-logo h1 {
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
        }

        .login-logo h1 span {
            color: #fbbf24;
        }

        .login-logo p {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 36px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }

        .role-tabs {
            display: flex;
            background: #f1f5f9;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 28px;
        }

        .role-tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.2s;
            border: none;
            background: none;
            font-family: 'Poppins', sans-serif;
        }

        .role-tab.active {
            background: #fff;
            color: #1a3c6e;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .login-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a3c6e;
            margin-bottom: 6px;
        }

        .login-subtitle {
            font-size: 0.88rem;
            color: #64748b;
            margin-bottom: 24px;
        }

        .input-icon-wrap {
            position: relative;
        }

        .input-icon-wrap .icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
            color: #94a3b8;
        }

        .input-icon-wrap .form-control {
            padding-left: 42px;
        }

        .forgot-link {
            font-size: 0.85rem;
            color: #2563eb;
            float: right;
            margin-top: -12px;
            margin-bottom: 16px;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.88rem;
            color: #64748b;
        }

        .register-link a {
            color: #2563eb;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .divider-text {
            text-align: center;
            position: relative;
            margin: 20px 0;
            color: #94a3b8;
            font-size: 0.85rem;
        }

        .divider-text::before,
        .divider-text::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #e2e8f0;
        }

        .divider-text::before {
            left: 0;
        }

        .divider-text::after {
            right: 0;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #1e40af;
        }

        .info-box strong {
            display: block;
            margin-bottom: 4px;
        }

        .footer-text {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.82rem;
            margin-top: 20px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 16px 0;
            font-size: 0.88rem;
            color: #475569;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-logo">
            <div class="logo-icon">🎓</div>
            <h1>Exam<span>Safe</span></h1>
            <p>Sistem Ujian Online SMA — Aman & Terpercaya</p>
        </div>

        <div class="login-card">
            <?php if ($error): ?>
                <div class="alert alert-danger">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="role-tabs">
                <button type="button" class="role-tab active" data-role="siswa">👨‍🎓 Siswa</button>
                <button type="button" class="role-tab" data-role="guru">👨‍🏫 Guru</button>
                <button type="button" class="role-tab" data-role="admin">⚙️ Admin</button>
            </div>

            <form method="POST" action="" id="loginForm">
                <?php echo csrfField($csrf_token); ?>
                <input type="hidden" name="role" id="role" value="siswa">

                <!-- SISWA FIELDS -->
                <div id="fields-siswa" class="role-fields">
                    <div class="login-title">Login Siswa</div>
                    <div class="login-subtitle">Masukkan kredensial yang diberikan oleh sekolah</div>
                    <div class="form-group">
                        <label>NISN / Username</label>
                        <div class="input-icon-wrap">
                            <span class="icon">👤</span>
                            <input type="text" class="form-control" name="username" id="username-siswa" placeholder="Masukkan NISN" required autofocus>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-icon-wrap">
                            <span class="icon">🔒</span>
                            <input type="password" class="form-control" name="password" id="password-siswa" placeholder="Masukkan password" required>
                        </div>
                    </div>
                </div>

                <!-- GURU FIELDS -->
                <div id="fields-guru" class="role-fields" style="display:none;">
                    <div class="login-title">Login Guru</div>
                    <div class="login-subtitle">Masuk untuk mengelola ujian dan melihat hasil siswa</div>
                    <div class="form-group">
                        <label>NIP / Email .id.Belajar</label>
                        <div class="input-icon-wrap">
                            <span class="icon">📧</span>
                            <input type="text" class="form-control" name="username" id="username-guru" placeholder="Masukkan NIP atau Email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-icon-wrap">
                            <span class="icon">🔒</span>
                            <input type="password" class="form-control" name="password" id="password-guru" placeholder="Masukkan password">
                        </div>
                    </div>
                </div>

                <!-- ADMIN FIELDS -->
                <div id="fields-admin" class="role-fields" style="display:none;">
                    <div class="login-title">Login Administrator</div>
                    <div class="login-subtitle">Panel kontrol sistem ujian</div>
                    <div class="form-group">
                        <label>Username Admin</label>
                        <div class="input-icon-wrap">
                            <span class="icon">⚙️</span>
                            <input type="text" class="form-control" name="username" id="username-admin" placeholder="Username admin">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-icon-wrap">
                            <span class="icon">🔒</span>
                            <input type="password" class="form-control" name="password" id="password-admin" placeholder="Masukkan password">
                        </div>
                    </div>
                </div>

                <label class="checkbox-label">
                    <input type="checkbox" name="remember"> Ingat saya
                </label>

                <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">Masuk ke Dashboard</button>

                <div style="text-align:center; margin-top:20px; font-size:0.9rem; color:#64748b">
                    Belum punya akun? <a href="student/register.html" style="color:var(--primary); font-weight:600">Daftar Siswa</a>
                </div>

                <div class="info-box mt-3">
                    <strong>ℹ️ Demo Login:</strong><br>
                    <b>Siswa:</b> siswa001 / siswa123<br>
                    <b>Guru:</b> guru@sma.sch.id / guru123<br>
                    <b>Admin:</b> admin / admin123
                </div>
            </form>
        </div>

        <div class="footer-text">© 2024 ExamSafe — SMA Negeri 1 | Sistem Ujian Online Aman</div>
    </div>

    <script>
        // Role switching
        function updateFormFields() {
            const role = document.getElementById('role').value;

            // Hide all fieldsets and disable their inputs
            document.querySelectorAll('.role-fields').forEach(fields => {
                const inputs = fields.querySelectorAll('input, select, textarea');
                if (fields.id === `fields-${role}`) {
                    fields.style.display = 'block';
                    // Enable inputs in visible fieldset
                    inputs.forEach(input => input.disabled = false);
                } else {
                    fields.style.display = 'none';
                    // Disable inputs in hidden fieldsets so they don't get submitted
                    inputs.forEach(input => input.disabled = true);
                }
            });

            // Set focus to the username field of the active role
            const activeUsernameField = document.getElementById(`username-${role}`);
            if (activeUsernameField) {
                setTimeout(() => activeUsernameField.focus(), 50);
            }
        }

        document.querySelectorAll('.role-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const role = this.dataset.role;

                // Update active tab
                document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Update hidden role input
                document.getElementById('role').value = role;

                // Update form fields visibility and disable states
                updateFormFields();

                // Clear any previous error messages
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => alert.remove());
            });
        });

        // Initialize on page load - disable fields for non-active roles
        document.addEventListener('DOMContentLoaded', function() {
            updateFormFields();
        });
    </script>
</body>

</html>
