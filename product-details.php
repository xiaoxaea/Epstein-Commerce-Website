<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/config/database.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['role'] ?? '';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId || $productId < 1) {
    header('Location: products.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, name, description, price, image, stock
     FROM products
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

// Create a CSRF token for comment submissions.
if (empty($_SESSION['comment_csrf_token'])) {
    $_SESSION['comment_csrf_token'] = bin2hex(random_bytes(32));
}

$commentError = '';

// Process a new comment.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {

    if (!$isLoggedIn || $userRole !== 'buyer') {
        $commentError = 'Only logged-in buyers can post comments.';
    } elseif (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['comment_csrf_token'], $_POST['csrf_token'])
    ) {
        $commentError = 'Invalid request. Please refresh the page and try again.';
    } else {
        $comment = trim($_POST['comment'] ?? '');

        if ($comment === '') {
            $commentError = 'Please enter a comment.';
        } elseif (mb_strlen($comment, 'UTF-8') > 1000) {
            $commentError = 'Your comment cannot exceed 1000 characters.';
        } else {
            $insertComment = $pdo->prepare(
                'INSERT INTO product_comments (product_id, user_id, comment)
                 VALUES (:product_id, :user_id, :comment)'
            );

            $insertComment->execute([
                'product_id' => $productId,
                'user_id' => $_SESSION['user_id'],
                'comment' => $comment
            ]);

            // Prevent the same comment from being submitted again on refresh.
            unset($_SESSION['comment_csrf_token']);

            header('Location: product-details.php?id=' . $productId . '#product-comments');
            exit;
        }
    }
}

// Retrieve comments for this product.
$commentsStmt = $pdo->prepare(
    'SELECT product_comments.id,
            product_comments.comment,
            product_comments.created_at,
            users.name
     FROM product_comments
     INNER JOIN users ON product_comments.user_id = users.id
     WHERE product_comments.product_id = :product_id
     ORDER BY product_comments.created_at DESC'
);

$commentsStmt->execute(['product_id' => $productId]);
$comments = $commentsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
        | Megatronix
    </title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="styles.css">


</head>

<body class="product-details-page">

    <!-- Navbar -->
    <header>
        <nav>
            <h2><a href="index.php">Megatronix</a></h2>

            <ul>
                <li>
                    <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">home</span>
                        <span>Home</span>
                    </a>
                </li>

                <li>
                    <a href="about.php" class="<?= $currentPage === 'about.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">info</span>
                        <span>About</span>
                    </a>
                </li>

                <li>
                    <a href="contact.php" class="<?= $currentPage === 'contact.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">mail</span>
                        <span>Contact</span>
                    </a>
                </li>

                <li>
                    <a href="products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">
                        <span class="material-symbols-outlined">storefront</span>
                        <span>Shop</span>
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>
                <li class="profile-dropdown">
                    <a class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php">
                        <span class="material-symbols-outlined">person</span>
                        <span><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
                    </a>

                    <ul class="profile-dropdown-menu">
                        <li><a href="profile.php">Profile</a></li>
                        <li><a href="cart.php">My Cart</a></li>
                        <li><a href="my-orders.php">My Orders</a></li>
                        <li><a href="logout.php">Log Out</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Product Details -->
    <main class="product-details-container">

        <a href="products.php" class="product-back-link">
            &larr; Back to Products
        </a>

        <section class="product-details-card">

            <div class="product-details-image">
                <?php if (!empty($product['image'])): ?>
                <img src="<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                <div class="product-image-placeholder">
                    No image available
                </div>
                <?php endif; ?>
            </div>

            <div class="product-details-information">

                <h1>
                    <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                </h1>

                <p class="product-details-price">
                    ₱<?= number_format((float) $product['price'], 2) ?>
                </p>

                <p class="product-details-description">
                    <?= nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')) ?>
                </p>

                <?php if ((int) $product['stock'] > 0): ?>
                <p class="product-stock available">
                    In stock (<?= (int) $product['stock'] ?> available)
                </p>
                <?php else: ?>
                <p class="product-stock unavailable">
                    Out of stock
                </p>
                <?php endif; ?>

                <?php if ((int) $product['stock'] > 0): ?>

                <?php if (!$isLoggedIn): ?>

                <a href="login.php" class="button product-details-button">
                    Log In to Buy
                </a>

                <?php elseif ($userRole === 'buyer'): ?>

                <form action="cart.php" method="POST" class="product-details-form">

                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

                    <label for="quantity">Quantity</label>

                    <input type="number" id="quantity" name="quantity" min="1" max="<?= (int) $product['stock'] ?>"
                        value="1" required>

                    <button type="submit" name="add_to_cart" class="button product-details-button">
                        Add to Cart
                    </button>

                </form>

                <form action="checkout.php" method="GET">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

                    <button type="submit" class="button product-buy-button">
                        Buy Now
                    </button>
                </form>

                <?php else: ?>

                <p>Purchasing is available to buyer accounts only.</p>

                <?php endif; ?>

                <?php else: ?>

                <button type="button" class="button product-details-button" disabled>
                    Out of Stock
                </button>

                <?php endif; ?>

            </div>
        </section>

        <!-- Product Comments -->
        <section class="product-comments" id="product-comments">

            <h2>Customer Comments</h2>

            <p class="product-comments-count">
                <?= count($comments) ?>
                <?= count($comments) === 1 ? 'comment' : 'comments' ?>
            </p>

            <?php if ($commentError !== ''): ?>
            <p class="comment-error">
                <?= htmlspecialchars($commentError, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <?php endif; ?>

            <!-- Comment Form -->
            <?php if ($isLoggedIn && $userRole === 'buyer'): ?>

            <form method="POST" action="product-details.php?id=<?= (int) $productId ?>#product-comments"
                class="product-comment-form">

                <label for="comment">Write a comment</label>

                <textarea textarea style="resize: none;" id="comment" name="comment" rows="4" maxlength="1000"
                    placeholder="Share your thoughts about this product..." required></textarea>

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['comment_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <button type="submit" name="submit_comment" class="button">
                    Post Comment
                </button>

            </form>

            <?php elseif (!$isLoggedIn): ?>

            <p class="comment-login-message">
                <a href="login.php">Log in</a> as a buyer to leave a comment.
            </p>

            <?php else: ?>

            <p class="comment-login-message">
                Comments can only be posted by buyer accounts.
            </p>

            <?php endif; ?>

            <!-- Display Existing Comments -->
            <div class="product-comment-list">

                <?php if (count($comments) > 0): ?>

                <?php foreach ($comments as $comment): ?>

                <article class="product-comment">

                    <div class="product-comment-header">
                        <h3>
                            <?= htmlspecialchars($comment['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <span>
                            <?= date('M d, Y h:i A', strtotime($comment['created_at'])) ?>
                        </span>
                    </div>

                    <p>
                        <?= nl2br(htmlspecialchars($comment['comment'], ENT_QUOTES, 'UTF-8')) ?>
                    </p>

                </article>

                <?php endforeach; ?>

                <?php else: ?>

                <p class="no-product-comments">
                    No comments yet.
                </p>

                <?php endif; ?>

            </div>

        </section>

    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">

            <div class="footer-brand">
                <h2>Megatronix</h2>
                <p>Your destination for electronics and gadgets.</p>
            </div>

            <div class="footer-links">
                <h3>Quick Links</h3>
                <a href="index.php">Home</a>
                <a href="about.php">About Us</a>
                <a href="products.php">Browse Items</a>
                <a href="contact.php">Contact Us</a>
            </div>

            <div class="footer-contact">
                <h3>Customer Support</h3>
                <p>Have questions about our products?</p>
                <a href="contact.php">Get in touch with us</a>
            </div>

        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Megatronix. All rights reserved.</p>
        </div>
    </footer>

</body>

</html>