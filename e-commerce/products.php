<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/config/database.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';

// Get the search term from the search bar.
$search = trim($_GET['search'] ?? '');

// Retrieve products, filtering by name when a search is submitted.
if ($search !== '') {
    $stmt = $pdo->prepare(
        'SELECT id, name, description, price, image, stock
         FROM products
         WHERE name LIKE :search
         ORDER BY created_at DESC'
    );

    $stmt->execute([
        'search' => '%' . $search . '%'
    ]);
} else {
    $stmt = $pdo->query(
        'SELECT id, name, description, price, image, stock
         FROM products
         ORDER BY created_at DESC'
    );
}

$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Browse Products | Brand Name</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="styles.css">
</head>

<body class="products-page">


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


    <form action="products.php" method="GET" class="product-search">
        <input
            type="search"
            name="search"
            placeholder="Search for electronics and gadgets..."
            value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Search</button>
    </form>



    <main class="products-container">

        <?php if (count($products) > 0): ?>

            <section class="product-grid">

                <?php foreach ($products as $product): ?>

                    <article class="product-card">

                        <a
                            href="product-details.php?id=<?= (int) $product['id'] ?>"
                            class="product-card-link">

                            <?php if (!empty($product['image'])): ?>
                                <img
                                    class="product-image"
                                    src="<?= htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <div class="product-image-placeholder">
                                    No image available
                                </div>
                            <?php endif; ?>

                            <div class="product-information">
                                <h2>
                                    <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                </h2>

                                <p class="product-description">
                                    <?= htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8') ?>
                                </p>

                                <p class="product-price">
                                    ₱<?= number_format((float) $product['price'], 2) ?>
                                </p>

                                <?php if ((int) $product['stock'] > 0): ?>
                                    <p class="product-stock available">In stock</p>
                                <?php else: ?>
                                    <p class="product-stock unavailable">Out of stock</p>
                                <?php endif; ?>
                            </div>

                        </a>

                        <?php if ($isLoggedIn && ($_SESSION['role'] ?? '') === 'buyer' && (int) $product['stock'] > 0): ?>

                            <div class="product-actions">

                                <form action="cart.php" method="POST">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <input type="hidden" name="quantity" value="1">

                                    <button
                                        type="submit"
                                        name="add_to_cart"
                                        class="button product-button">
                                        Add to cart
                                    </button>
                                </form>

                                <form action="checkout.php" method="GET">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                </form>

                            </div>

                        <?php elseif (!$isLoggedIn): ?>

                            <a href="login.php" class="button product-button">
                                Log In to Shop
                            </a>

                        <?php else: ?>

                            <button type="button" class="button product-button" disabled>
                                Unavailable
                            </button>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </section>

        <?php else: ?>

            <div class="products-empty">

                <?php if ($search !== ''): ?>

                    <h2>No matching products found</h2>

                    <p>
                        No products matched
                        "<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>".
                        Try another search term.
                    </p>

                    <a href="products.php" class="button">View All Products</a>

                <?php else: ?>

                    <h2>No products yet</h2>
                    <p>Products will appear here once they have been added to the database.</p>

                <?php endif; ?>

            </div>

        <?php endif; ?>

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