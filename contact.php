<?php
session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | E-Commerce</title>

    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="styles.css">


</head>

<body>

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


    <main class="contact-page">
        <section class="hero">
            <h1>Contact Us</h1>
            <p class="hero-description">
                Have a question or suggestion about our website?
                We would be glad to hear from you.
            </p>
        </section>

        <section class="contact-layout">
            <div class="contact-information">
                <h2>Let's Talk</h2>
                <p>
                    Use the form to write your message to our project team.
                    You can share questions, feedback, or suggestions about
                    the website.
                </p>

                <div class="contact-info-card">
                    <h3>General Inquiries</h3>
                    <p>
                        For questions about the website, products, or shopping
                        features, describe your concern in the form.
                    </p>
                </div>

                <div class="contact-info-card">
                    <h3>Website Information</h3>
                    <p>
                        Megatronix is a website which aims to provide a platform where users can buy quality products
                        from trusted sellers.
                    </p>
                </div>
            </div>

            <div class="contact-form-card">
                <h2>Send Us a Message</h2>

                <?php if ($submitted): ?>
                <div class="contact-notice">
                    This is a demonstration form. Your message has not
                    been sent or saved. A message-submission feature
                    can be connected later.
                </div>
                <?php endif; ?>

                <form method="POST" action="contact.php">
                    <div class="contact-form-group">
                        <label for="contact-name">Your Name</label>
                        <input type="text" id="contact-name" name="name" maxlength="100" required
                            value="<?= $isLoggedIn ? htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') : '' ?>"
                            placeholder="Enter your name">
                    </div>

                    <div class="contact-form-group">
                        <label for="contact-email">Email Address</label>
                        <input type="email" id="contact-email" name="email" maxlength="150" required
                            placeholder="Enter your email address">
                    </div>

                    <div class="contact-form-group">
                        <label for="contact-subject">Subject</label>
                        <input type="text" id="contact-subject" name="subject" maxlength="150" required
                            placeholder="What is your message about?">
                    </div>

                    <div class="contact-form-group">
                        <label for="contact-message">Message</label>
                        <textarea style="resize: none;" id="contact-message" name="message" rows="6" maxlength="3000"
                            required placeholder="Write your message here..."></textarea>
                    </div>

                    <button type="submit" class="contact-submit-button">
                        Submit Message
                    </button>
                </form>
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