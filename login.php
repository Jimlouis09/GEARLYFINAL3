<?php

require 'config/database.php';
require 'includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'customer/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();

    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    $s = $conn->prepare('SELECT user_id,full_name,email,password,role FROM users WHERE email=?');
    $s->bind_param('s', $email);
    $s->execute();

    $u = $s->get_result()->fetch_assoc();

    if ($u && password_verify($pass, $u['password'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $u['user_id'];
        $_SESSION['full_name'] = $u['full_name'];
        $_SESSION['email'] = $u['email'];
        $_SESSION['role'] = $u['role'];

        header('Location: ' . ($u['role'] === 'admin' ? 'admin/dashboard.php' : 'customer/dashboard.php'));
        exit;

    } else {
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Login';

require 'includes/header.php';

?>

<div class="gearly-login-container">

    <!-- LEFT SIDE -->
    <div class="gearly-login-left">
</div>


    <!-- RIGHT SIDE -->
    <div class="gearly-login-right">

        <div class="gearly-form-container">

            <h2>Welcome Back!</h2>

            <p class="gearly-subtitle">
                Login to your account
            </p>

            <?php if ($error): ?>

                <div class="alert alert-danger">
                    <?= h($error) ?>
                </div>

            <?php endif; ?>

            <form method="post">

                <input type="hidden" name="csrf" value="<?= csrf() ?>">

                <!-- EMAIL -->
                <div class="gearly-input-group">
                    <span>✉</span>

                    <input
                        type="email"
                        name="email"
                        placeholder="Email Address"
                        required
                    >
                </div>

                <!-- PASSWORD -->
                <div class="gearly-input-group">
                    <span>🔒</span>

                    <input
                        type="password"
                        name="password"
                        placeholder="Password"
                        required
                    >
                </div>

                <!-- OPTIONS -->
                <div class="gearly-login-options">

                    <label>
                        <input type="checkbox" name="remember">
                        Remember me
                    </label>

                    <a href="forgot_password.php">
                        Forgot password?
                    </a>

                </div>

                <!-- LOGIN BUTTON -->
                <button type="submit" class="gearly-login-button">
                    Login
                </button>

            </form>

            <!-- REGISTER -->
            <p class="gearly-signup-text">
                Don't have an account?
                <a href="register.php">Sign Up</a>
            </p>

        </div>

    </div>

</div>

<?php require 'includes/footer.php'; ?>