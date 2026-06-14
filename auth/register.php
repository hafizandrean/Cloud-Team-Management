<?php
/**
 * Cloud Team Management - Registration Page
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header("Location: ../dashboard/index.php");
    exit;
}

$errorMsg = '';
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'member';

    // Restrict role to allowed database roles ('admin', 'member')
    if ($role !== 'admin' && $role !== 'member') {
        $role = 'member';
    }

    if (empty($username) || empty($email) || empty($password)) {
        $errorMsg = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Format email tidak valid.';
    } else {
        try {
            $db = Database::getConnection();

            // Check if username already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
            $stmt->execute(['username' => $username]);
            if ($stmt->fetch()) {
                $errorMsg = 'Username sudah terdaftar.';
            } else {
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                if ($stmt->fetch()) {
                    $errorMsg = 'Email sudah terdaftar.';
                } else {
                    // Hash password and insert
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("
                        INSERT INTO users (username, email, password, role) 
                        VALUES (:username, :email, :password, :role)
                    ");
                    $stmt->execute([
                        'username' => $username,
                        'email' => $email,
                        'password' => $hashedPassword,
                        'role' => $role
                    ]);

                    $successMsg = 'Akun berhasil dibuat! Silakan masuk.';
                }
            }
        } catch (PDOException $e) {
            $errorMsg = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Cloud Team Management</title>
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
            --success-bg: #f0fdf4;
            --success-text: #15803d;
            --success-border: #bbf7d0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
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

        .register-container {
            width: 100%;
            max-width: 440px;
            animation: fadeIn 0.5s ease-out;
        }

        .register-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 8px 32px 0 rgba(99, 102, 241, 0.04);
            transition: all 0.3s ease;
        }

        .register-card:hover {
            box-shadow: 0 12px 40px 0 rgba(99, 102, 241, 0.08);
            border-color: rgba(99, 102, 241, 0.2);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 28px;
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

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error {
            background-color: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        .alert-success {
            background-color: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 11px 14px;
            font-size: 14px;
            font-family: inherit;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-main);
            background-color: rgba(255, 255, 255, 0.5);
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-select:focus {
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

<div class="register-container">
    <div class="register-card">
        <div class="logo-section">
            <div class="logo-icon">C</div>
            <h1 class="logo-title">Daftar Akun Baru</h1>
            <p class="logo-subtitle">Buat akun untuk masuk ke dashboard CTM</p>
        </div>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-error" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="Masukkan username" required autocomplete="username" value="<?php echo htmlspecialchars($username ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="Masukkan alamat email" required autocomplete="email" value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Buat password minimalis" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="role" class="form-label">Peran (Role)</label>
                <select id="role" name="role" class="form-select">
                    <option value="member">Member</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">Daftar</button>
        </form>
    </div>
    <p class="footer-text">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
</div>

</body>
</html>
