<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';

require_once __DIR__ . '/config/database.php';

// Only logged-in buyers can check out.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? [];
$message = '';
$errors = [];

// Create a CSRF token to protect the checkout form.
if (empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));
}

// Handle order submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['checkout_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['checkout_token'], $token)) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';

    if ($fullName === '' || strlen($fullName) > 150) {
        $errors[] = 'Enter your full name (maximum 150 characters).';
    }

    if ($phone === '' || strlen($phone) > 30) {
        $errors[] = 'Enter a valid phone number (maximum 30 characters).';
    }

    if ($address === '') {
        $errors[] = 'Enter your delivery address.';
    }

    if (!in_array($paymentMethod, ['Cash on Delivery'], true)) {
        $errors[] = 'Select a valid payment method.';
    }

    if (empty($cart)) {
        $errors[] = 'Your cart is empty.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Lock product rows while checking stock and creating the order.
            $productIds = array_map('intval', array_keys($cart));
            $productIds = array_values(array_unique($productIds));

            if (empty($productIds) || in_array(0, $productIds, true)) {
                throw new RuntimeException('Your cart contains an invalid product.');
            }

            $placeholders = implode(',', array_fill(0, count($productIds), '?'));

            $stmt = $pdo->prepare(
                "SELECT id, name, price, stock
                 FROM products
                 WHERE id IN ($placeholders)
                 FOR UPDATE"
            );
            $stmt->execute($productIds);
            $databaseProducts = $stmt->fetchAll();

            $productsById = [];

            foreach ($databaseProducts as $product) {
                $productsById[(int) $product['id']] = $product;
            }

            $orderItems = [];
            $total = 0;

            foreach ($cart as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = filter_var($quantity, FILTER_VALIDATE_INT);

                if ($quantity === false || $quantity < 1) {
                    throw new RuntimeException('Your cart contains an invalid quantity.');
                }

                if (!isset($productsById[$productId])) {
                    throw new RuntimeException('A product in your cart no longer exists.');
                }

                $product = $productsById[$productId];

                if ($quantity > (int) $product['stock']) {
                    throw new RuntimeException(
                        'Not enough stock for ' . $product['name'] .
                            '. Available quantity: ' . $product['stock'] . '.'
                    );
                }

                $price = (float) $product['price'];
                $subtotal = round($price * $quantity, 2);
                $total = round($total + $subtotal, 2);

                $orderItems[] = [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal
                ];
            }

            // Save the main order.
            $stmt = $pdo->prepare(
                'INSERT INTO orders
                 (user_id, full_name, phone, delivery_address, payment_method, total_amount)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $userId,
                $fullName,
                $phone,
                $address,
                $paymentMethod,
                $total
            ]);

            $orderId = (int) $pdo->lastInsertId();

            // Save each item and deduct its stock.
            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items
                 (order_id, product_id, product_name, quantity, price, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stockStmt = $pdo->prepare(
                'UPDATE products
                 SET stock = stock - ?
                 WHERE id = ? AND stock >= ?'
            );

            foreach ($orderItems as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['price'],
                    $item['subtotal']
                ]);

                $stockStmt->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $item['quantity']
                ]);

                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Stock changed while your order was being processed. Please try again.'
                    );
                }
            }

            $pdo->commit();

            // Empty the cart only after the order is saved successfully.
            unset($_SESSION['cart']);
            unset($_SESSION['checkout_token']);

            header('Location: checkout.php?success=' . $orderId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof RuntimeException) {
                $errors[] = $e->getMessage();
            } else {
                error_log($e->getMessage());
                $errors[] = 'We could not place your order. Please try again.';
            }
        }
    }
}

// Show a confirmation only if this order belongs to the current buyer.
$successOrder = null;

if (isset($_GET['success'])) {
    $successId = filter_var($_GET['success'], FILTER_VALIDATE_INT);

    if ($successId !== false && $successId > 0) {
        $stmt = $pdo->prepare(
            'SELECT id, total_amount, status, created_at
             FROM orders
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$successId, $userId]);
        $successOrder = $stmt->fetch();
    }
}

// Retrieve fresh product information for the checkout page.
$checkoutItems = [];
$total = 0;

if (!$successOrder && !empty($cart)) {
    $productIds = array_map('intval', array_keys($cart));
    $productIds = array_values(array_unique($productIds));

    if (!empty($productIds) && !in_array(0, $productIds, true)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $stmt = $pdo->prepare(
            "SELECT id, name, price, image, stock
             FROM products
             WHERE id IN ($placeholders)"
        );
        $stmt->execute($productIds);
        $databaseProducts = $stmt->fetchAll();

        foreach ($databaseProducts as $product) {
            $id = (int) $product['id'];
            $quantity = filter_var($cart[$id] ?? null, FILTER_VALIDATE_INT);

            if ($quantity === false || $quantity < 1) {
                continue;
            }

            $product['quantity'] = $quantity;
            $product['subtotal'] = round((float) $product['price'] * $quantity, 2);

            $checkoutItems[] = $product;
            $total = round($total + $product['subtotal'], 2);
        }
    }
}

function checkoutEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Electronics Store</title>

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

    <main class="checkout-page">
        <div class="checkout-container">

            <?php if ($successOrder): ?>

                <section class="checkout-success">
                    <h1>Order Placed Successfully!</h1>
                    <p>Thank you for shopping with us.</p>

                    <div class="checkout-success-details">
                        <p>
                            <strong>Order Number:</strong>
                            #<?= (int) $successOrder['id'] ?>
                        </p>

                        <p>
                            <strong>Total:</strong>
                            ₱<?= number_format((float) $successOrder['total_amount'], 2) ?>
                        </p>

                        <p>
                            <strong>Payment:</strong>
                            Cash on Delivery
                        </p>

                        <p>
                            <strong>Status:</strong>
                            <?= checkoutEscape($successOrder['status']) ?>
                        </p>
                    </div>

                    <p class="checkout-note">
                        This is a school project demonstration. No actual payment has been collected.
                    </p>

                    <a href="products.php" class="checkout-primary-button">
                        Continue Shopping
                    </a>
                </section>

            <?php else: ?>

                <h1 class="checkout-title">Checkout</h1>
                <p class="checkout-subtitle">Enter your delivery information to place your order.</p>

                <?php if (!empty($errors)): ?>
                    <div class="checkout-errors">
                        <?php foreach ($errors as $error): ?>
                            <p><?= checkoutEscape($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($cart) || empty($checkoutItems)): ?>

                    <section class="checkout-empty">
                        <h2>Your cart is empty</h2>
                        <p>Add products to your cart before checking out.</p>
                        <a href="products.php" class="checkout-primary-button">
                            Browse Products
                        </a>
                    </section>

                <?php else: ?>

                    <div class="checkout-layout">

                        <section class="checkout-form-section">
                            <h2>Delivery Information</h2>

                            <form action="checkout.php" method="POST" class="checkout-form">
                                <input
                                    type="hidden"
                                    name="checkout_token"
                                    value="<?= checkoutEscape($_SESSION['checkout_token']) ?>">

                                <label for="full_name">Full Name</label>
                                <input
                                    type="text"
                                    id="full_name"
                                    name="full_name"
                                    maxlength="150"
                                    value="<?= checkoutEscape($_POST['full_name'] ?? $_SESSION['user_name'] ?? '') ?>"
                                    required>

                                <label for="phone">Phone Number</label>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    maxlength="30"
                                    value="<?= checkoutEscape($_POST['phone'] ?? '') ?>"
                                    required>

                                <label for="delivery_address">Delivery Address</label>
                                <textarea
                                    id="delivery_address"
                                    name="delivery_address"
                                    rows="4"
                                    required><?= checkoutEscape($_POST['delivery_address'] ?? '') ?></textarea>

                                <label for="payment_method">Payment Method</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="Cash on Delivery" selected>
                                        Cash on Delivery
                                    </option>
                                </select>

                                <button type="submit" class="checkout-primary-button">
                                    Place Order
                                </button>
                            </form>
                        </section>

                        <aside class="checkout-summary">
                            <h2>Order Summary</h2>

                            <?php foreach ($checkoutItems as $item): ?>
                                <div class="checkout-summary-item">
                                    <div>
                                        <strong><?= checkoutEscape($item['name']) ?></strong>
                                        <p>
                                            Quantity: <?= (int) $item['quantity'] ?>
                                        </p>
                                        <p>
                                            <?php if ((int) $item['stock'] < (int) $item['quantity']): ?>
                                                <span class="checkout-stock-warning">
                                                    Only <?= (int) $item['stock'] ?> left in stock
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <span>
                                        ₱<?= number_format($item['subtotal'], 2) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>

                            <div class="checkout-total">
                                <span>Total</span>
                                <strong>₱<?= number_format($total, 2) ?></strong>
                            </div>

                            <p class="checkout-note">
                                Your order total is recalculated using the current product prices.
                                Stock is checked again when you place your order.
                            </p>
                        </aside>

                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>
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