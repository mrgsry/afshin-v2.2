<?php
require_once 'functions.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if(empty($username) || empty($password)){
        $error = "Username dan Password wajib diisi!";
    } else {

        $stmt = $mysqli->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows === 1){

            $user = $result->fetch_assoc();

            // VERIFIKASI PASSWORD
            if(password_verify($password, $user['password'])){

                // Simpan session
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ];

                $_SESSION['LAST_ACTIVITY'] = time();

                header("Location: index.php");
                exit;

            } else {
                $error = "Password salah!";
            }

        } else {
            $error = "Username tidak ditemukan!";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Login - Afshin APP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
    body {
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        background: linear-gradient(135deg, #3a1c71, #6a11cb, #2575fc);
        background-size: 400% 400%;
        animation: gradientMove 10s ease infinite;
        overflow: hidden;
    }

    @keyframes gradientMove {
        0% {
            background-position: 0% 50%
        }

        50% {
            background-position: 100% 50%
        }

        100% {
            background-position: 0% 50%
        }
    }

    .login-card {
        width: 400px;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        animation: fadeIn .6s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .login-title {
        font-weight: 700;
        color: #3a1c71;
    }

    .btn-login {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none;
        color: #fff;
        font-weight: 600;
        border-radius: 10px;
        transition: .3s;
    }

    .btn-login:hover {
        transform: scale(1.05);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .input-group-text {
        background: #f0f0f0;
        border: none;
    }

    .form-control {
        border-radius: 10px;
    }
    </style>
</head>

<body>

    <div class="login-card text-center">

        <div class="mb-4">
            <i class="fas fa-cogs fa-3x text-primary mb-3"></i>
            <h4 class="login-title">CV Afshin Raya Teknik</h4>
            <small class="login-title">CRM Apps by Hnet Solution</small>
            <small class="text-muted">Sistem Manajemen v2.2</small>
        </div>

        <?php if($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group text-left">
                <label>Username</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                    </div>
                    <input type="text" name="username" class="form-control" required>
                </div>
            </div>

            <div class="form-group text-left">
                <label>Password</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>

            <button type="submit" class="btn btn-login btn-block mt-3">
                Masuk
            </button>

        </form>

        <hr>

        <small class="text-muted">© <?= date('Y') ?> Afshin APP</small>

    </div>

</body>

</html>