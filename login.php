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
                    'role' => $user['role'],
                    'job_position' => $user['job_position'] ?? '',
                    'photo_path' => $user['photo_path'] ?? ''
                ];

                $_SESSION['LAST_ACTIVITY'] = time();
                $_SESSION['login_success'] = $user['username'];

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
    :root { --ink: #fff; --muted: #f0c9cd; --blue: #08a9e8; --panel: #fff; --card-red: #641820; }

    * { box-sizing: border-box; }

    body {
        min-height: 100vh;
        margin: 0;
        background: var(--panel);
        font-family: "Trebuchet MS", Arial, sans-serif;
        overflow-x: hidden;
    }

    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 8px;
        background: var(--blue);
        z-index: 5;
    }

    .login-page {
        min-height: 100vh;
        display: grid;
        grid-template-columns: minmax(0, 1.45fr) minmax(390px, .75fr);
        background: var(--panel);
    }

    .login-visual {
        position: relative;
        min-height: 100vh;
        display: flex;
        align-items: flex-end;
        padding: clamp(2rem, 6vw, 5.5rem);
        background-image: url("img/cover.jpg");
        background-size: 92% auto;
        background-repeat: no-repeat;
        background-position: center center;
        isolation: isolate;
    }

    .login-panel {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        background: #fff;
    }

    .login-panel::before {
        content: "";
        position: absolute;
        top: 8%;
        bottom: 8%;
        left: 0;
        width: 1px;
        background: rgba(255, 255, 255, .15);
    }

    .login-card {
        width: min(100%, 390px);
        padding: clamp(2rem, 4vw, 3.4rem);
        background: var(--card-red);
        border-radius: 8px;
        box-shadow: 0 18px 30px rgba(0, 0, 0, .28), 0 30px 75px rgba(0, 0, 0, .42);
        animation: cardIn .6s ease both;
    }

    .login-card::before {
        content: "";
        display: block;
        width: 100%;
        height: 3px;
        margin: -3.4rem 0 2.5rem;
        border-radius: 4px;
        background: var(--blue);
    }

    @keyframes cardIn { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }

    .login-brand { display: flex; align-items: center; gap: .75rem; margin-bottom: 2.2rem; }
    .login-brand img { width: 42px; height: 42px; object-fit: contain; }
    .login-brand strong { display: block; color: var(--ink); font-size: .88rem; letter-spacing: .03em; }
    .login-brand span { display: block; margin-top: .2rem; color: var(--muted); font-size: .66rem; }
    .login-heading { margin-bottom: 1.7rem; }
    .login-heading h2 { margin: 0 0 .5rem; color: var(--ink); font-size: 1.55rem; line-height: 1.2; font-weight: 800; }
    .login-heading p { margin: 0; color: var(--muted); font-size: .78rem; }
    .login-title, .login-subtitle { display: none; }
    .login-card label { color: var(--ink); font-size: .7rem; font-weight: 700; }
    .login-card .form-group { margin-bottom: 1.15rem; }
    .input-group-text { width: 44px; justify-content: center; color: var(--muted); background: #f8fafc; border: 1px solid #dfe3e8; border-right: 0; }
    .form-control { height: 44px; border: 1px solid #dfe3e8; border-radius: 0 4px 4px 0; box-shadow: none; font-size: .8rem; }
    .form-control:focus { border-color: var(--blue); box-shadow: 0 0 0 2px rgba(8, 169, 232, .12); }
    .btn-login { height: 44px; margin-top: .7rem; border: 0; border-radius: 4px; background: #111827; color: #fff; font-size: .78rem; font-weight: 700; letter-spacing: .02em; transition: background .2s, transform .2s; }
    .btn-login:hover { background: var(--blue); color: #fff; transform: translateY(-1px); }
    .login-card hr { margin: 1.8rem 0 1rem; border-color: rgba(255, 255, 255, .25); }

    .login-loading { position: fixed; inset: 0; z-index: 20; display: none; align-items: center; justify-content: center; background: rgba(75, 17, 24, .94); color: #fff; }
    .login-loading.is-visible { display: flex; }
    .loading-content { text-align: center; }
    .loading-spinner { width: 42px; height: 42px; margin: 0 auto 1rem; border: 3px solid rgba(255, 255, 255, .3); border-top-color: var(--blue); border-radius: 50%; animation: spin .8s linear infinite; }
    .loading-content strong { display: block; font-size: .95rem; }
    .loading-content span { display: block; margin-top: .35rem; color: #f0c9cd; font-size: .75rem; }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 767.98px) {
        .login-page { display: block; min-height: 100vh; background: var(--panel); }
        .login-visual { min-height: 35vh; padding: 1.5rem; background-size: 100% auto; background-position: center 38%; }
        .login-panel { min-height: 66vh; padding: 1.25rem; background: #fff; }
        .login-panel::before { display: none; }
        .login-card { padding: 1.8rem 1.35rem; }
        .login-card::before { margin-top: -1.8rem; margin-bottom: 1.8rem; }
        .login-brand { margin-bottom: 1.8rem; }
    }

    @media (max-width: 380px) {
        .login-visual { min-height: 30vh; background-position: center 32%; }
        .login-panel { min-height: 70vh; }
        .login-card { padding: 1.5rem 1.1rem; }
    }
    </style>
</head>

<body>
    <div class="login-loading" id="loginLoading" aria-live="polite" aria-busy="true">
        <div class="loading-content"><div class="loading-spinner"></div><strong>Memproses login...</strong><span>Menyiapkan dashboard Anda</span></div>
    </div>
    <main class="login-page">
        <section class="login-visual">
        </section>

        <section class="login-panel">
        <div class="login-card">
        <div class="login-brand">
            <img src="img/afshin2.png" alt="Afshin Raya Teknik">
            <div><strong>AFSHIN APP</strong><span>Business management portal</span></div>
        </div>
        <div class="login-heading">
            <h2>Hi there, great to see you</h2>
            <p>Masuk untuk mengelola aplikasi Afshin.</p>
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

            <button type="submit" class="btn btn-login btn-block mt-3" id="loginSubmit">
                Masuk
            </button>

        </form>

        <hr>

        <small class="text-muted">© <?= date('Y') ?> Afshin APP</small>

        </div>
        </section>
    </main>

    <script>
    document.querySelector('form').addEventListener('submit', function(event) {
        event.preventDefault();
        document.getElementById('loginLoading').classList.add('is-visible');
        document.getElementById('loginSubmit').disabled = true;
        window.setTimeout(function() {
            event.target.submit();
        }, 2000);
    });
    </script>

</body>

</html>