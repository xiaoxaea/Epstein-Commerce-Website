<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['role'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Fresh Tech Within Reach | Megatronix</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- CSS stylesheet -->
    <link rel="stylesheet" href="styles.css">
</head>

<body>

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

                <?php if ($isLoggedIn): ?>
                    <li class="profile-dropdown">
                        <a
                            class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                            <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
                        </a>

                        <ul class="profile-dropdown-menu">
                            <li>
                                <a href="profile.php">Profile</a>
                            </li>
                            <li>
                                <a href="cart.php">My Cart</a>
                            </li>
                            <li>
                                <a href="my-orders.php">My Orders</a>
                            </li>
                            <li>
                                <a href="logout.php">Log Out</a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>


    <main>
        <section class="hero-main">
            <?php if ($isLoggedIn): ?>
                <h1>Welcome, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>!</h1>

                <p class="hero-description">
                    Discover electronics and gadgets designed to keep you connected,
                    productive, and entertained.
                </p>

                <a href="products.php" class="button">Browse Items</a>

                <?php if ($userRole === 'buyer'): ?>
                    <p class="signup-text">
                        Ready to shop?
                        <a href="cart.php">View Your Cart</a>
                    </p>
                <?php endif; ?>

            <?php else: ?>
                <h1>Fresh Tech Within Reach</h1>

                <p class="hero-description">
                    Discover the latest electronics and gadgets
                    designed to keep you connected, productive,
                    and entertained—all in one place.
                </p>

                <a href="login.php" class="button">Log In</a>

                <p class="signup-text">
                    Don't have an account?
                    <a href="signup.php">Sign up here</a>
                </p>
            <?php endif; ?>
        </section>

        <!-- Featured Brands -->
        <section class="featured-section">
            <div class="featured-section-heading">
                <h2>Featured Brands</h2>
                <p>Explore trusted brands for your everyday tech needs.</p>
            </div>

            <div class="featured-brands">
                <a href="products.php" class="featured-brand-card">
                    <span>Logitech</span>
                </a>

                <a href="products.php" class="featured-brand-card">
                    <span>ASUS</span>
                </a>

                <a href="products.php" class="featured-brand-card">
                    <span>Razer</span>
                </a>

                <a href="products.php" class="featured-brand-card">
                    <span>Samsung</span>
                </a>

                <a href="products.php" class="featured-brand-card">
                    <span>Kingston</span>
                </a>
            </div>
        </section>

        <!-- Featured Items -->
        <section class="featured-section featured-items-section">
            <div class="featured-section-heading">
                <h2>Featured Items</h2>
                <p>Check out some of the electronics and gadgets in our store.</p>
            </div>

            <div class="featured-items">
                <?php
                require_once __DIR__ . '/config/database.php';

                $featuredStmt = $pdo->query(
                    'SELECT id, name, description, price, image, stock
             FROM products
             ORDER BY created_at DESC
             LIMIT 4'
                );

                $featuredProducts = $featuredStmt->fetchAll();
                ?>

                <?php if (!empty($featuredProducts)): ?>
                    <?php foreach ($featuredProducts as $product): ?>
                        <article class="featured-item-card">
                            <a
                                href="products.php"
                                class="featured-item-image"
                                aria-label="Browse products">
                                <?php if (!empty($product['image'])): ?>
                                    <img
                                        src="<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        loading="lazy">
                                <?php else: ?>
                                    <div class="featured-item-placeholder">
                                        No image available
                                    </div>
                                <?php endif; ?>
                            </a>

                            <div class="featured-item-details">
                                <h3>
                                    <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                </h3>

                                <p class="featured-item-description">
                                    <?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <p class="featured-item-price">
                                    ₱<?= number_format((float) $product['price'], 2) ?>
                                </p>

                                <a href="products.php" class="button">
                                    View Products
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="featured-items-empty">
                        No products are available yet. Check back soon!
                    </p>
                <?php endif; ?>
            </div>

            <div class="featured-items-footer">
                <a href="products.php" class="button-secondary">
                    Browse All Products
                </a>
            </div>
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