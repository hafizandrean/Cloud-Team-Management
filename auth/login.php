<?php
/**
 * Cloud Team Management - Login Page
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/activity_helper.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header("Location: ../dashboard/index.php");
    exit;
}

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errorMsg = 'Username dan password wajib diisi.';
    } else {
        try {
            $db = Database::getConnection();
            
            // Query user and join with anggota to get name and photo if exists
            $stmt = $db->prepare("
                SELECT u.*, a.nama, a.foto, a.email AS anggota_email 
                FROM users u
                LEFT JOIN anggota a ON u.id = a.id_user
                WHERE u.username = :username
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login successful, set session variables
                startSession();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['nama'] = $user['nama'] ?? $user['username'];
                $_SESSION['foto'] = $user['foto'];
                $_SESSION['email'] = $user['anggota_email'] ?? $user['email'];

                // Write activity log
                writeLog($db, $user['id'], 'LOGIN', 'User berhasil login');

                header("Location: ../dashboard/index.php");
                exit;
            } else {
                $errorMsg = 'Username atau password salah.';
            }
        } catch (PDOException $e) {
            $errorMsg = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Cloud Team Management</title>
    <meta name="description" content="Halaman masuk aplikasi Cloud Team Management. Kelola tim Anda secara efisien.">
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --primary-color: #6366f1;
            --primary-hover: #4f46e5;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-body: #334155;
            --border-color: #e2e8f0;
            --error-bg: #fef2f2;
            --error-text: #ef4444;
            --error-border: #fca5a5;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.05) 0%, transparent 40%),
                        radial-gradient(circle at 100% 100%, rgba(14, 165, 233, 0.05) 0%, transparent 40%),
                        #f8fafc;
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.5s ease-out;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 16px;
            padding: 40px 32px;
            box-shadow: 0 8px 32px 0 rgba(99, 102, 241, 0.04);
            transition: all 0.3s ease;
        }

        .login-card:hover {
            box-shadow: 0 12px 40px 0 rgba(99, 102, 241, 0.08);
            border-color: rgba(99, 102, 241, 0.2);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
            border-radius: 12px;
            font-weight: 700;
            font-size: 24px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
        }

        .logo-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .logo-subtitle {
            font-size: 14px;
            color: var(--text-muted);
        }

        .error-alert {
            background-color: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            font-family: inherit;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-main);
            background-color: rgba(255, 255, 255, 0.5);
            transition: all 0.2s ease;
        }

        .form-input::placeholder {
            color: #a1a1aa;
        }

        .form-input:focus {
            outline: none;
            background-color: rgba(255, 255, 255, 0.9);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .btn-submit {
            width: 100%;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            color: #ffffff;
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99, 102, 241, 0.25);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .footer-text a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="logo-section">
            <div class="logo-icon">C</div>
            <h1 class="logo-title" id="login-title">Cloud Team Management</h1>
            <p class="logo-subtitle">Silakan masuk ke akun Anda</p>
        </div>

        <?php if (!empty($errorMsg)): ?>
            <div class="error-alert" role="alert" id="error-alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="login-form">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="Masukkan username Anda" required autocomplete="username" value="<?php echo htmlspecialchars($username ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Masukkan password Anda" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-submit" id="btn-login">Masuk</button>
        </form>
    </div>
    <p class="footer-text" style="margin-top: 16px;">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
    <p class="footer-text">&copy; <?php echo date('Y'); ?> Cloud Team Management. All rights reserved.</p>
</div>

</body>
</html>
