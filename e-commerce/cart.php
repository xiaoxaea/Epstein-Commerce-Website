<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';

require_once __DIR__ . '/config/database.php';

// Only logged-in buyers can use the shopping cart.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: index.php');
    exit;
}

// Store cart items as product IDs and quantities.
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$messageType = '';

// Add a product to the cart.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    $quantity = ($quantity !== false && $quantity !== null && $quantity > 0)
        ? $quantity
        : 1;

    if (!$productId || $productId < 1) {
        $message = 'Invalid product.';
        $messageType = 'error';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, stock FROM products WHERE id = ?'
        );
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            $message = 'Product not found.';
            $messageType = 'error';
        } elseif ((int) $product['stock'] < 1) {
            $message = 'This product is out of stock.';
            $messageType = 'error';
        } else {
            $currentQuantity = (int) ($_SESSION['cart'][$productId] ?? 0);
            $newQuantity = $currentQuantity + $quantity;

            if ($newQuantity > (int) $product['stock']) {
                $message = 'The requested quantity exceeds available stock.';
                $messageType = 'error';
            } else {
                $_SESSION['cart'][$productId] = $newQuantity;
                $message = 'Product added to your cart.';
                $messageType = 'success';
            }
        }
    }
}

// Update a cart item's quantity.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $quantities = $_POST['quantities'] ?? [];

    if (is_array($quantities)) {
        foreach ($quantities as $productId => $quantityValue) {
            $productId = filter_var($productId, FILTER_VALIDATE_INT);
            $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);

            if (!$productId || !isset($_SESSION['cart'][$productId])) {
                continue;
            }

            if ($quantity === 0) {
                unset($_SESSION['cart'][$productId]);
                continue;
            }

            if ($quantity === false || $quantity === null || $quantity < 1) {
                $message = 'Quantities must be at least 1, or remove the item.';
                $messageType = 'error';
                continue;
            }

            $stmt = $pdo->prepare(
                'SELECT stock FROM products WHERE id = ?'
            );
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                unset($_SESSION['cart'][$productId]);
                continue;
            }

            if ($quantity > (int) $product['stock']) {
                $message = 'A requested quantity exceeds available stock.';
                $messageType = 'error';
                continue;
            }

            $_SESSION['cart'][$productId] = $quantity;
        }

        if ($message === '') {
            $message = 'Cart updated.';
            $messageType = 'success';
        }
    }
}

// Remove one product from the cart.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $productId = filter_input(INPUT_POST, 'remove_item', FILTER_VALIDATE_INT);

    if ($productId && isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
        $message = 'Item removed from your cart.';
        $messageType = 'success';
    }
}

// Retrieve current product details from the database.
$cartItems = [];
$cartTotal = 0;

if (!empty($_SESSION['cart'])) {
    $productIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $stmt = $pdo->prepare(
        "SELECT id, name, description, price, image, stock
         FROM products
         WHERE id IN ($placeholders)"
    );
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();

    // Remove products that no longer exist.
    $foundIds = [];

    foreach ($products as $product) {
        $productId = (int) $product['id'];
        $foundIds[] = $productId;

        $quantity = (int) $_SESSION['cart'][$productId];
        $price = (float) $product['price'];
        $subtotal = $price * $quantity;

        $product['quantity'] = $quantity;
        $product['subtotal'] = $subtotal;

        $cartItems[] = $product;
        $cartTotal += $subtotal;
    }

    foreach ($productIds as $productId) {
        if (!in_array((int) $productId, $foundIds, true)) {
            unset($_SESSION['cart'][$productId]);
        }
    }
}

$itemCount = array_sum($_SESSION['cart']);
$userName = $_SESSION['user_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Cart | Brand Name</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="styles.css">
</head>

<body class="cart-page">

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
                        <a href="profile.php"
                            class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                            <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
                        </a>

                        <ul class="profile-dropdown-menu">
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

    <main class="cart-container">

        <section class="cart-heading">
            <h1>My Shopping Cart</h1>
            <p>Review your items before proceeding to checkout.</p>
        </section>

        <?php if ($message !== ''): ?>
            <div class="cart-message <?= $messageType === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>

            <section class="cart-empty">
                <h2>Your cart is empty</h2>
                <p>You haven't added any products to your cart yet.</p>
                <a href="products.php" class="button">Browse Products</a>
            </section>

        <?php else: ?>

            <div class="cart-layout">

                <section class="cart-items-section">
                    <form action="cart.php" method="POST">
                        <div class="cart-items">

                            <?php foreach ($cartItems as $item): ?>
                                <article class="cart-item">

                                    <?php if (!empty($item['image'])): ?>
                                        <img
                                            class="cart-item-image"
                                            src="<?= htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8') ?>"
                                            alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?php else: ?>
                                        <div class="cart-item-placeholder">
                                            No image
                                        </div>
                                    <?php endif; ?>

                                    <div class="cart-item-details">
                                        <h2>
                                            <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </h2>

                                        <p class="cart-item-price">
                                            ₱<?= number_format((float) $item['price'], 2) ?> each
                                        </p>

                                        <p class="cart-item-stock">
                                            <?= (int) $item['stock'] ?> available
                                        </p>

                                        <label
                                            class="cart-quantity-label"
                                            for="quantity-<?= (int) $item['id'] ?>">
                                            Quantity
                                        </label>

                                        <input
                                            class="cart-quantity-input"
                                            type="number"
                                            id="quantity-<?= (int) $item['id'] ?>"
                                            name="quantities[<?= (int) $item['id'] ?>]"
                                            min="0"
                                            max="<?= (int) $item['stock'] ?>"
                                            value="<?= (int) $item['quantity'] ?>"
                                            required>

                                        <button
                                            class="cart-remove-button"
                                            type="submit"
                                            name="remove_item"
                                            value="<?= (int) $item['id'] ?>"
                                            formnovalidate>
                                            Remove
                                        </button>
                                    </div>

                                    <div class="cart-item-subtotal">
                                        <span>Subtotal</span>
                                        <strong>
                                            ₱<?= number_format((float) $item['subtotal'], 2) ?>
                                        </strong>
                                    </div>

                                </article>
                            <?php endforeach; ?>

                        </div>

                        <div class="cart-update-row">
                            <button
                                type="submit"
                                name="update_cart"
                                value="1"
                                class="button-secondary">
                                Update Cart
                            </button>

                            <a href="products.php" class="cart-continue-link">
                                Continue Shopping
                            </a>
                        </div>
                    </form>
                </section>

                <aside class="cart-summary">
                    <h2>Order Summary</h2>

                    <div class="cart-summary-row">
                        <span>Total Items</span>
                        <span><?= (int) $itemCount ?></span>
                    </div>

                    <div class="cart-summary-row cart-total-row">
                        <span>Total</span>
                        <strong>₱<?= number_format($cartTotal, 2) ?></strong>
                    </div>

                    <p class="cart-summary-note">
                        Shipping fees and payment details are not included.
                    </p>

                    <a href="checkout.php" class="button cart-checkout-button">
                        Proceed to Checkout
                    </a>
                </aside>

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