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

    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (
        !$name ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        !$phone ||
        !$address ||
        strlen($pass) < 8
    ) {
        $error = 'Complete all fields with a valid email and password of at least 8 characters.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        try {
            $s = $conn->prepare("INSERT INTO users(full_name,email,phone,address,password,role) VALUES(?,?,?,?,?,'customer')");
            $s->bind_param('sssss', $name, $email, $phone, $address, $hash);
            $s->execute();

            flash('Registration successful. You can now log in.');

            header('Location: login.php');
            exit;

        } catch (mysqli_sql_exception $e) {
            $error = $e->getCode() === 1062
                ? 'That email is already registered.'
                : 'Registration failed. Please try again.';
        }
    }
}

$pageTitle = 'Register';

require 'includes/header.php';

?>

<div class="gearly-login-container">

    <!-- LEFT SIDE: GEARLY BACKGROUND -->

    <div class="gearly-login-left">
    </div>


    <!-- RIGHT SIDE: REGISTER FORM -->

    <div class="gearly-login-right">

        <div class="gearly-form-container">

            <h2>Create Account</h2>

            <p class="gearly-subtitle">
                Register to start renting equipment
            </p>

            <?php if ($error): ?>

                <div class="alert alert-danger">
                    <?= h($error) ?>
                </div>

            <?php endif; ?>


            <form method="post">

                <input type="hidden" name="csrf" value="<?= csrf() ?>">

                <!-- FULL NAME -->

                <div class="gearly-input-group">
                    <span>👤</span>

                    <input
                        type="text"
                        name="full_name"
                        placeholder="Full Name"
                        maxlength="150"
                        required
                    >
                </div>


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


                <!-- PHONE -->

                <div class="gearly-input-group">
                    <span>📞</span>

                    <input
                        type="tel"
                        name="phone"
                        placeholder="Phone Number"
                        required
                    >
                </div>


                <!-- ADDRESS -->

                <div class="gearly-input-group gearly-address-group">
                    <span>📍</span>

                    <textarea
                        name="address"
                        placeholder="Complete Address"
                        required
                    ></textarea>
                </div>


                <!-- PASSWORD -->

                <div class="gearly-input-group">
                    <span>🔒</span>

                    <input
                        type="password"
                        name="password"
                        placeholder="Password (At least 8 characters)"
                        minlength="8"
                        required
                    >
                </div>


                <!-- REGISTER BUTTON -->

                <button type="submit" class="gearly-login-button">
                    Create Account
                </button>

            </form>


            <!-- LOGIN LINK -->

            <p class="gearly-signup-text">
                Already have an account?
                <a href="login.php">Login</a>
            </p>

        </div>

    </div>

</div>

<?php require 'includes/footer.php'; ?>