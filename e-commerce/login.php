<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/config/database.php';

// Redirect users who are already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'seller') {
        header('Location: seller/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    if ($email === '' || $passwordInput === '') {
        $error = 'Please enter your email and password.';
    } else {
        $sql = 'SELECT id, name, email, password, role
                FROM users
                WHERE email = :email
                LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch();

        if (
            $user &&
            password_verify($passwordInput, $user['password'])
        ) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'seller') {
                header('Location: seller/index.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Log In | Megatronix</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <main>
        <section class="hero login-section">

            <h1>Welcome Back</h1>

            <p class="login-description">
                Log in to your account to continue.
            </p>

            <?php if ($error !== ''): ?>
                <p class="login-error">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="login.php" class="login-form">

                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    autocomplete="email"
                    required
                    value="<?= htmlspecialchars(
                                $_POST['email'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required>

                <button type="submit" class="button">
                    Log In
                </button>

            </form>

            <p class="signup-text">
                Don't have an account?
                <a href="signup.php">Sign up here</a>
            </p>

            <p class="back-home">
                <a href="index.php">Back to Home</a>
            </p>

        </section>
    </main>

    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> E-Commerce Project. All rights reserved.</p>
    </footer>
</body>

</html>