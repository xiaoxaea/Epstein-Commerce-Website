<?php
session_start();


$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | E-Commerce</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
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



    <main class="about-page">
        <section class="hero">
            <h1>About us</h1>
            <p class="hero-description">
                We aim to make discovering electronics and gadgets simple,
                convenient, and accessible.
            </p>
            <a href="products.php" class="about-button">Explore Products</a>
        </section>

        <section class="about-content">
            <div class="about-text">
                <h2>Who We Are</h2>
                <p>
                    We are an electronics and gadgets e-commerce website
                    developed as a school project. Our goal is to demonstrate
                    how an online shopping platform can help customers discover
                    products in one convenient place.
                </p>
                <p>
                    From everyday accessories to useful electronic devices,
                    our website is designed to make browsing products simple
                    and straightforward.
                </p>
            </div>

            <div class="about-highlight">
                <h2>Our Purpose</h2>
                <p>
                    To provide a simple online shopping experience where
                    customers can browse products, manage their cart, and
                    review their orders.
                </p>
            </div>
        </section>

        <section class="about-features">
            <h2>What You Can Do</h2>

            <div class="about-feature-grid">
                <article class="about-feature-card">
                    <h3>Browse Products</h3>
                    <p>Explore the electronics and gadgets listed in our catalog.</p>
                </article>

                <article class="about-feature-card">
                    <h3>Manage Your Cart</h3>
                    <p>Add products to your cart and review your selections.</p>
                </article>

                <article class="about-feature-card">
                    <h3>Track Your Orders</h3>
                    <p>Review your order information through your account.</p>
                </article>
            </div>
        </section>

        <section class="about-cta">
            <h2>Have Questions?</h2>
            <p>Visit our Contact Us page to learn how to reach our project team.</p>
            <a href="contact.php" class="about-button">Contact Us</a>
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