<?php
ob_start();
// This ensures the cookie works across all subdirectories
session_set_cookie_params([
    'lifetime' => 0,      // Session cookie (expires when browser closes)
    'path' => '/',        // Make cookie available across entire site
    'domain' => '',       // Current domain only
    'secure' => false,    // Set to true if using HTTPS
    'httponly' => true,   // Prevent JavaScript access
    'samesite' => 'Lax'   // CSRF protection
]);
session_start();

require_once 'includes/auth.php';
require_once 'includes/csrf.php';
require_once 'php/db.php';

// Function to send JSON response (for AJAX requests)
function sendJsonResponse($success, $code, $message, $data = [])
{
    $response = array_merge([
        'success' => $success,
        'code' => $code,
        'message' => $message
    ], $data);

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Check if already logged in
if (isLoggedIn() && !isSessionExpired(3600)) {
    $redirect = match ($_SESSION['role']) {
        'siswa' => 'student/dashboard.php',
        'guru' => 'teacher/dashboard.html',
        'admin' => 'admin/dashboard.html',
        default => 'index.php'
    };

    // For AJAX requests, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        sendJsonResponse(true, 200, 'Already logged in', ['redirect' => $redirect]);
    }

    header("Location: $redirect");
    exit;
}

// Detect AJAX request
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Handle login logic (both AJAX and traditional POST)
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get CSRF token from POST or JSON body
    $csrf_token = null;
    $role = null;
    $username = null;
    $password = null;
    $remember = false;

    if ($isAjax) {
        // Handle JSON input for AJAX
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $csrf_token = $input['csrf_token'] ?? '';
            $role = $input['role'] ?? '';
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            $remember = $input['remember'] ?? false;
        }
    } else {
        // Handle traditional form POST
        $csrf_token = $_POST['csrf_token'] ?? '';
        $role = $_POST['role'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;
    }

    // Verify CSRF token
    if (!verifyCSRFToken($csrf_token, $_SESSION['csrf_token'])) {
        $error = 'Token keamanan tidak valid. Silakan refresh halaman.';
        if ($isAjax) {
            sendJsonResponse(false, 400, $error);
        }
    } elseif (empty($role) || empty($username) || empty($password)) {
        $error = 'Semua field harus diisi.';
        if ($isAjax) {
            sendJsonResponse(false, 400, $error);
        }
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
                if ($isAjax) {
                    sendJsonResponse(false, 429, $error);
                }
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
                    if ($isAjax) {
                        sendJsonResponse(false, 400, $error);
                    }
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
                        if ($isAjax) {
                            sendJsonResponse(false, 401, $error);
                        }
                    } else {
                        // Check approval for teachers
                        if ($role === 'guru' && $user['approval_status'] !== 'approved') {
                            $error = 'Akun Anda belum disetujui admin. Harap tunggu verifikasi.';
                            if ($isAjax) {
                                sendJsonResponse(false, 403, $error);
                            }
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

                            // Determine redirect based on role
                            $redirect = match ($role) {
                                'siswa' => 'student/dashboard.php',
                                'guru' => 'teacher/dashboard.html',
                                'admin' => 'admin/dashboard.html',
                            };

                            // Get user name for response
                            $userName = match ($role) {
                                'siswa' => $user['full_name'] ?? $user['username'],
                                'guru' => $user['full_name'] ?? $user['nip'],
                                'admin' => $user['username'],
                            };

                            if ($isAjax) {
                                // Return JSON success response for AJAX
                                sendJsonResponse(true, 200, 'Login berhasil', [
                                    'redirect' => $redirect,
                                    'role' => $role,
                                    'user' => [
                                        'id' => $user['id'],
                                        'name' => $userName
                                    ]
                                ]);
                            } else {
                                // Traditional redirect for non-AJAX
                                $_SESSION['success'] = 'Login berhasil!';
                                header("Location: $redirect");
                                exit;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            if ($isAjax) {
                sendJsonResponse(false, 500, $error);
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

        .register-link {
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

        .footer-text {
            text-align: center;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.82rem;
            margin-top: 20px;
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

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
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

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .role-alert {
            margin-bottom: 20px;
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
            <!-- Traditional POST error/success display (non-JS fallback) -->
            <?php if ($error && !$isAjax): ?>
                <div class="alert alert-danger">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success && !$isAjax): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Role-specific alert containers for AJAX - hidden by default, shown only when content exists -->
            <div id="alert-siswa" class="role-alert" style="display:none;"></div>
            <div id="alert-guru" class="role-alert" style="display:none;"></div>
            <div id="alert-admin" class="role-alert" style="display:none;"></div>

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
                    <div class="register-link">
                        Belum punya akun? <a href="student/register.html">Daftar Siswa</a>
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
                    <div class="register-link">
                        Belum punya akun? <a href="teacher/register.html">Daftar Guru</a>
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
                    <!-- No registration link for admin -->
                </div>

                <label class="checkbox-label">
                    <input type="checkbox" name="remember"> Ingat saya
                </label>

                <button type="submit" class="btn btn-primary btn-block btn-lg mt-2" id="submitBtn">Masuk ke Dashboard</button>
            </form>
        </div>

        <div class="footer-text">© 2024 ExamSafe — SMA Negeri 1 | Sistem Ujian Online Aman</div>
    </div>

    <script>
        // CSRF token from hidden field
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;

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

            // Clear all role alerts (hides them if they were visible)
            document.querySelectorAll('.role-alert').forEach(alert => {
                alert.innerHTML = '';
                alert.style.display = 'none';
            });

            // Set focus to the username field of the active role
            const activeUsernameField = document.getElementById(`username-${role}`);
            if (activeUsernameField) {
                setTimeout(() => activeUsernameField.focus(), 50);
            }
        }

        // Display alert message for current role - only shows when message has content
        function showAlert(role, message, type) {
            const alertDiv = document.getElementById(`alert-${role}`);
            if (alertDiv) {
                if (message && message.trim() !== '') {
                    alertDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
                    alertDiv.style.display = 'block';
                } else {
                    // Clear and hide if message is empty
                    alertDiv.innerHTML = '';
                    alertDiv.style.display = 'none';
                }
            }
        }

        // AJAX login function - sends request to current page (index.php)
        async function doLoginAJAX(role, username, password, remember) {
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⌛ Memproses...';

            // Clear previous alerts (hides the alert container)
            showAlert(role, '', '');

            // Show loading message (this will make the alert container visible)
            showAlert(role, '⌛ Sedang memverifikasi...', 'info');

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        role: role,
                        username: username,
                        password: password,
                        remember: remember,
                        csrf_token: csrfToken
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(role, `✅ ${data.message}! Mengalihkan...`, 'success');

                    // Store data in sessionStorage (Option A)
                    sessionStorage.setItem('role', data.role);
                    sessionStorage.setItem('user', data.user.name);
                    sessionStorage.setItem('user_id', data.user.id);

                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    // Handle different error codes
                    let errorMessage = `❌ ${data.message}`;
                    if (data.code === 403) {
                        errorMessage = `❌ ${data.message} Hubungi admin untuk verifikasi.`;
                    }
                    showAlert(role, errorMessage, 'danger');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Login error:', error);
                showAlert(role, '❌ Terjadi kesalahan koneksi ke server.', 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }

        // Handle form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const role = document.getElementById('role').value;
            const usernameField = document.getElementById(`username-${role}`);
            const passwordField = document.getElementById(`password-${role}`);
            const remember = document.querySelector('input[name="remember"]').checked;

            const username = usernameField ? usernameField.value : '';
            const password = passwordField ? passwordField.value : '';

            if (!username || !password) {
                showAlert(role, '❌ Semua field harus diisi.', 'danger');
                e.preventDefault();
                return false;
            }

            // Use AJAX if JavaScript is enabled
            e.preventDefault();
            doLoginAJAX(role, username, password, remember);
            return false;
        });

        // Role tab switching
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

                // Clear any existing alerts specifically for the newly active role
                showAlert(role, '', '');
            });
        });

        // Initialize on page load - disable fields for non-active roles
        document.addEventListener('DOMContentLoaded', function() {
            updateFormFields();
        });
    </script>
</body>

</html>
