<?php
// login.php
require_once 'functions.php';
require_once 'db.php';

$error = '';

// Panggil session_start() di awal file
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- LOGIKA PHP UNTUK AJAX REQUEST (TIDAK BERUBAH) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $res = mysqli_query($mysqli, "SELECT * FROM users WHERE username='" . mysqli_real_escape_string($mysqli, $username) . "' LIMIT 1");

    if ($res && $user = mysqli_fetch_assoc($res)) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            echo json_encode(['success' => true, 'name' => htmlspecialchars($user['name'] ?? $user['username'])]);
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    } else {
        $error = 'Invalid credentials';
    }

    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}
// --- END LOGIKA PHP UNTUK AJAX REQUEST ---

// Jika user sudah login (ketika mengakses halaman tanpa POST), redirect
if (is_logged_in()) header('Location: index.php');


include 'header.php';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CV Afshin Raya Teknik</title>
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --gradient: linear-gradient(135deg, #4361ee 0%, #3a0ca3 50%, #7209b7 100%);
            --gradient-light: linear-gradient(135deg, #4cc9f0 0%, #4361ee 100%);
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 50px rgba(0, 0, 0, 0.15);
            --radius: 16px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: var(--gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.3;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 20s infinite linear;
        }

        .circle:nth-child(1) {
            width: 300px;
            height: 300px;
            top: -100px;
            left: -100px;
            animation-delay: 0s;
        }

        .circle:nth-child(2) {
            width: 200px;
            height: 200px;
            bottom: -50px;
            right: -50px;
            animation-delay: -5s;
        }

        .circle:nth-child(3) {
            width: 150px;
            height: 150px;
            top: 50%;
            right: 10%;
            animation-delay: -10s;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
            }

            100% {
                transform: translate(100px, 100px) rotate(360deg);
            }
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            transform: translateY(0);
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: var(--gradient);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }

        .logo-text h1 {
            color: var(--dark);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-text p {
            color: #6c757d;
            font-size: 14px;
            font-weight: 400;
        }

        .version-badge {
            display: inline-block;
            background: var(--gradient-light);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
            box-shadow: 0 4px 10px rgba(76, 201, 240, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: var(--dark);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-header p {
            color: #6c757d;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            color: var(--dark);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            padding-left: 5px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            color: #6c757d;
            z-index: 2;
            font-size: 18px;
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            color: var(--dark);
            background: white;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-control::placeholder {
            color: #adb5bd;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
            transition: var(--transition);
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            font-size: 14px;
        }

        .remember-me input {
            accent-color: var(--primary);
        }

        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
        }

        .forgot-password:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: var(--gradient);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(67, 97, 238, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .spinner-border {
            width: 20px;
            height: 20px;
            border-width: 2px;
        }

        .error-message {
            background: #fee;
            color: #dc3545;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border: 1px solid #fcc;
            display: none;
        }

        .error-message.show {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 14px;
        }

        .copyright {
            color: var(--dark);
            font-weight: 600;
        }

        /* Modal Styling */
        .modal-content {
            border-radius: var(--radius);
            border: none;
            overflow: hidden;
        }

        .modal-body {
            padding: 40px;
        }

        .modal-success {
            color: #28a745;
            font-size: 60px;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 25px;
            }

            .logo-text h1 {
                font-size: 20px;
            }

            .login-header h2 {
                font-size: 24px;
            }

            .form-control {
                padding: 12px 12px 12px 45px;
            }
        }

        /* Animation for form elements */
        .form-group {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        .form-group:nth-child(1) {
            animation-delay: 0.1s;
        }

        .form-group:nth-child(2) {
            animation-delay: 0.2s;
        }

        .remember-forgot {
            animation-delay: 0.3s;
        }

        .login-btn {
            animation-delay: 0.4s;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="background-animation">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="logo-text">
                    <h1>CV Afshin Raya Teknik</h1>
                    <p>Selamat Datang di Sistem Manajemen</p>
                    <span class="version-badge">2.1.0</span>
                </div>
            </div>

            <div class="login-header">
                <h2>Masuk ke Akun</h2>
                <p>Silakan login untuk memulai sesi Anda</p>
            </div>

            <div id="loginError" class="error-message"></div>

            <form id="loginForm" method="post">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            placeholder="Masukkan username Anda"
                            required
                            autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            placeholder="Masukkan password Anda"
                            required
                            autocomplete="current-password">
                        <button type="button" class="toggle-password" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Ingat saya</span>
                    </label>
                    <a href="#" class="forgot-password">Lupa password?</a>
                </div>

                <button id="loginButton" class="login-btn" type="submit">
                    <span id="buttonText">Masuk</span>
                    <span id="loadingSpinner" class="spinner-border" role="status" aria-hidden="true" style="display:none;"></span>
                </button>
            </form>

            <div class="login-footer">
                <p>&copy; <span class="copyright">2025 MRgoesray</span> | Afshin APP</p>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="welcomeModal" tabindex="-1" aria-labelledby="welcomeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-5">
                    <div class="modal-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="modal-title mb-3" id="welcomeModalLabel">Login Berhasil!</h3>
                    <p class="lead">Selamat datang, <strong id="userNameDisplay"></strong>.</p>
                    <p>Anda akan diarahkan ke Dashboard...</p>
                    <div class="mt-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('#togglePassword').click(function() {
                const passwordInput = $('#password');
                const icon = $(this).find('i');

                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Form submission with AJAX
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();

                let $form = $(this);
                let $button = $('#loginButton');
                let $spinner = $('#loadingSpinner');
                let $buttonText = $('#buttonText');
                let $errorDiv = $('#loginError');

                // Reset error
                $errorDiv.removeClass('show').empty();

                // Show loading state
                $button.attr('disabled', true);
                $buttonText.text('Memproses...');
                $spinner.show();

                // Get form data
                let formData = {
                    username: $('#username').val().trim(),
                    password: $('#password').val()
                };

                // Simple validation
                if (!formData.username || !formData.password) {
                    showError('Harap isi semua field');
                    return;
                }

                $.ajax({
                    type: 'POST',
                    url: 'login.php',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Show success modal
                            $('#userNameDisplay').text(response.name);
                            $('#welcomeModal').modal('show');

                            // Redirect after 2 seconds
                            setTimeout(function() {
                                window.location.href = 'index.php';
                            }, 2000);
                        } else {
                            showError(response.error || 'Login gagal. Periksa kembali username dan password.');
                        }
                    },
                    error: function(xhr, status, error) {
                        showError('Terjadi kesalahan pada server. Silakan coba lagi.');
                        console.error('AJAX Error:', error);
                    },
                    complete: function() {
                        // Reset button state
                        $button.attr('disabled', false);
                        $buttonText.text('Masuk');
                        $spinner.hide();
                    }
                });
            });

            // Enter key to submit form
            $('#username, #password').keypress(function(e) {
                if (e.which === 13) {
                    $('#loginForm').submit();
                    return false;
                }
            });

            // Auto focus username field
            $('#username').focus();

            function showError(message) {
                const $errorDiv = $('#loginError');
                $errorDiv.text(message).addClass('show');

                // Auto hide error after 5 seconds
                setTimeout(() => {
                    $errorDiv.removeClass('show');
                }, 5000);
            }
        });
    </script>
</body>

</html>