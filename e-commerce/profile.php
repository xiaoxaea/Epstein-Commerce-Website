<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/config/database.php';

// Require login.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$errors = [];

// Retrieve account information.
$stmt = $pdo->prepare(
    'SELECT name, email, role, profile_picture
     FROM users
     WHERE id = :id
     LIMIT 1'
);
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

// If the account no longer exists, end the session.
if (!$user) {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php');
    exit;
}

// Keep session information current.
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['role'] = $user['role'];

$userName = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
$userRole = htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8');

// Create a CSRF token.
if (empty($_SESSION['profile_token'])) {
    $_SESSION['profile_token'] = bin2hex(random_bytes(32));
}

// Handle username update.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_name'])
) {
    $token = $_POST['profile_token'] ?? '';

    if (
        !is_string($token) ||
        !isset($_SESSION['profile_token']) ||
        !hash_equals($_SESSION['profile_token'], $token)
    ) {
        $errors[] = 'Invalid form submission. Please refresh the page.';
    }

    $newName = trim($_POST['new_name'] ?? '');
    $nameLength = mb_strlen($newName, 'UTF-8');

    if ($nameLength < 3 || $nameLength > 20) {
        $errors[] = 'Your username must be between 3 and 20 characters.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE users SET name = ? WHERE id = ?'
            );
            $stmt->execute([$newName, $userId]);

            $_SESSION['user_name'] = $newName;

            header('Location: profile.php?name_updated=1');
            exit;
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $errors[] = 'Could not update your username. Please try again.';
        }
    }
}

// Handle profile-picture upload only when the picture form is submitted.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_FILES['profile_picture'])
) {
    $token = $_POST['profile_token'] ?? '';

    if (
        !is_string($token) ||
        !isset($_SESSION['profile_token']) ||
        !hash_equals($_SESSION['profile_token'], $token)
    ) {
        $errors[] = 'Invalid form submission. Please refresh the page.';
    }

    if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please choose an image to upload.';
    }

    if (empty($errors)) {
        $file = $_FILES['profile_picture'];

        // Maximum file size: 2 MB.
        $maxFileSize = 2 * 1024 * 1024;

        if ($file['size'] > $maxFileSize) {
            $errors[] = 'The image must not exceed 2 MB.';
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid uploaded file.';
        }

        // Verify actual image information.
        $imageInfo = false;
        $mimeType = '';

        if (empty($errors)) {
            $imageInfo = getimagesize($file['tmp_name']);

            if ($imageInfo !== false) {
                $mimeType = $imageInfo['mime'] ?? '';
            }
        }

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (
            $imageInfo === false ||
            !isset($allowedTypes[$mimeType])
        ) {
            $errors[] = 'Only valid JPEG, PNG, and WebP images are allowed.';
        }

        // Maximum dimensions: 1024 × 1024 pixels.
        if ($imageInfo !== false) {
            $width = (int) $imageInfo[0];
            $height = (int) $imageInfo[1];

            if (
                $width < 1 ||
                $height < 1 ||
                $width > 1024 ||
                $height > 1024
            ) {
                $errors[] = 'Image dimensions must not exceed 1024 × 1024 pixels.';
            }
        }

        if (empty($errors)) {
            $uploadDirectory = __DIR__ . '/uploads/profile-pictures/';

            if (
                !is_dir($uploadDirectory) ||
                !is_writable($uploadDirectory)
            ) {
                $errors[] = 'The profile-picture folder is not writable.';
            } else {
                // Generate a unique filename.
                $filename = bin2hex(random_bytes(16))
                    . '.' . $allowedTypes[$mimeType];

                $destination = $uploadDirectory . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'Could not save the uploaded image.';
                } else {
                    $relativePath = 'uploads/profile-pictures/' . $filename;

                    try {
                        // Update only the logged-in user's record.
                        $stmt = $pdo->prepare(
                            'UPDATE users
                             SET profile_picture = ?
                             WHERE id = ?'
                        );
                        $stmt->execute([$relativePath, $userId]);

                        // Delete the old picture after the database update.
                        $oldPicture = $user['profile_picture'] ?? null;

                        if (
                            $oldPicture &&
                            strpos($oldPicture, 'uploads/profile-pictures/') === 0
                        ) {
                            $oldFilename = basename($oldPicture);
                            $oldPath = $uploadDirectory . $oldFilename;

                            if (is_file($oldPath)) {
                                unlink($oldPath);
                            }
                        }

                        header('Location: profile.php?picture_updated=1');
                        exit;
                    } catch (Throwable $e) {
                        error_log($e->getMessage());

                        if (is_file($destination)) {
                            unlink($destination);
                        }

                        $errors[] = 'Could not update your profile picture.';
                    }
                }
            }
        }
    }
}

// Prepare the profile picture URL.
$profilePicture = $user['profile_picture'] ?? '';
$profilePictureUrl = '';

if (
    $profilePicture !== '' &&
    strpos($profilePicture, 'uploads/profile-pictures/') === 0 &&
    basename($profilePicture) === substr(
        $profilePicture,
        strlen('uploads/profile-pictures/')
    )
) {
    $picturePath = __DIR__ . '/' . $profilePicture;

    if (is_file($picturePath)) {
        $profilePictureUrl = $profilePicture;
    }
}

$pictureUpdated = isset($_GET['picture_updated'])
    && $_GET['picture_updated'] === '1';

$nameUpdated = isset($_GET['name_updated'])
    && $_GET['name_updated'] === '1';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | Megatronix</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="styles.css">
</head>

<body class="profile-page">

    <header>
        <nav>
            <h2><a href="index.php">Megatronix</a></h2>

            <ul>
                <li>
                    <a href="index.php"
                        class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        Home
                    </a>
                </li>
                <li>
                    <a href="about.php"
                        class="<?= $currentPage === 'about.php' ? 'active' : '' ?>">
                        About
                    </a>
                </li>
                <li>
                    <a href="contact.php"
                        class="<?= $currentPage === 'contact.php' ? 'active' : '' ?>">
                        Contact Us
                    </a>
                </li>
                <li>
                    <a href="products.php"
                        class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">
                        Browse Items
                    </a>
                </li>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="profile-dropdown">
                        <a href="profile.php"
                            class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                            <?= htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </a>

                        <!-- <ul class="profile-dropdown-menu">
                            <li><a href="cart.php">My Cart</a></li>
                            <li><a href="my-orders.php">My Orders</a></li>
                            <li><a href="logout.php">Log Out</a></li>
                        </ul> -->
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main class="account-layout">

        <!-- Account sidebar -->
        <aside class="account-sidebar">
            <div class="account-sidebar-header">
                <h2>My Account</h2>
            </div>

            <nav class="account-menu">
                <a href="cart.php">My Cart</a>
                <a href="my-orders.php">My Orders</a>
                <a href="logout.php">Log Out</a>
            </nav>
        </aside>

        <!-- Main account content -->
        <section class="account-content">

            <div class="account-heading">
                <h1>My Profile</h1>
                <p>Manage your account and view your account information.</p>
            </div>

            <!-- Combined profile settings -->
            <section class="account-card profile-picture-card">

                <div class="profile-picture-display">
                    <?php if ($profilePictureUrl !== ''): ?>
                        <img
                            class="profile-avatar"
                            src="<?= htmlspecialchars($profilePictureUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="Your profile picture">
                    <?php else: ?>
                        <div class="profile-avatar profile-avatar-placeholder">
                            <?= htmlspecialchars(
                                strtoupper(substr($user['name'], 0, 1)),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>
                    <?php endif; ?>

                    <div class="profile-picture-info">
                        <h2>Profile Settings</h2>
                        <p>Manage your profile picture and username.</p>
                        <p class="profile-picture-restrictions">
                            JPEG, PNG, or WebP · Max 1024 × 1024 pixels · 2 MB
                        </p>
                    </div>
                </div>

                <?php if ($pictureUpdated): ?>
                    <p class="profile-upload-success">
                        Profile picture updated successfully.
                    </p>
                <?php endif; ?>

                <?php if ($nameUpdated): ?>
                    <p class="profile-upload-success">
                        Your username has been updated successfully.
                    </p>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="profile-upload-errors">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Change profile picture -->
                <form
                    action="profile.php"
                    method="POST"
                    enctype="multipart/form-data"
                    class="profile-picture-form">

                    <input
                        type="hidden"
                        name="profile_token"
                        value="<?= htmlspecialchars($_SESSION['profile_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="profile_picture">Change Profile Picture</label>

                    <input
                        type="file"
                        id="profile_picture"
                        name="profile_picture"
                        accept="image/jpeg,image/png,image/webp"
                        required>

                    <button type="submit" class="button">
                        Upload Profile Picture
                    </button>
                </form>

                <div class="profile-settings-divider"></div>

                <!-- Change username -->
                <div class="profile-name-heading">
                    <h2>Change Your Username</h2>
                    <p>Update the username displayed on your account.</p>
                </div>

                <form
                    action="profile.php"
                    method="POST"
                    class="profile-name-form">

                    <input
                        type="hidden"
                        name="profile_token"
                        value="<?= htmlspecialchars($_SESSION['profile_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="new_name">Username</label>

                    <input
                        type="text"
                        id="new_name"
                        name="new_name"
                        minlength="3"
                        maxlength="20"
                        value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>"
                        required>

                    <button
                        type="submit"
                        name="update_name"
                        value="1"
                        class="button">
                        Save Username
                    </button>
                </form>

            </section>

            <!-- Account information -->
            <section class="account-card">
                <div class="account-card-heading">
                    <h2>Account Information</h2>
                    <p>Your registered account details.</p>
                </div>

                <div class="profile-details">
                    <div class="profile-detail-row">
                        <span class="profile-detail-label">Username</span>
                        <span class="profile-detail-value"><?= $userName ?></span>
                    </div>

                    <div class="profile-detail-row">
                        <span class="profile-detail-label">Email Address</span>
                        <span class="profile-detail-value"><?= $userEmail ?></span>
                    </div>

                    <div class="profile-detail-row">
                        <span class="profile-detail-label">Account Type</span>
                        <span class="profile-detail-value">
                            <span class="account-type"><?= $userRole ?></span>
                        </span>
                    </div>
                </div>
            </section>

        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-container">

            <!-- Brand Information -->
            <div class="footer-brand">
                <h2>Megatronix</h2>
                <p>Your destination for electronics and gadgets.</p>
            </div>

            <!-- Quick Links -->
            <div class="footer-links">
                <h3>Quick Links</h3>
                <a href="index.php">Home</a>
                <a href="about.php">About Us</a>
                <a href="products.php">Browse Items</a>
                <a href="contact.php">Contact Us</a>
            </div>

            <!-- Customer Support -->
            <div class="footer-contact">
                <h3>Customer Support</h3>
                <p>Have questions about our products?</p>
                <a href="contact.php">Get in touch with us</a>
            </div>

        </div>

        <!-- Copyright -->
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Megatronix. All rights reserved.</p>
        </div>
    </footer>

</body>

</html>