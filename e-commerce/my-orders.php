<?php
session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF']);

require_once __DIR__ . '/config/database.php';

// Only logged-in buyers can view their orders.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Generate a CSRF token to protect order cancellation.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process order cancellation.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $orderId = filter_input(INPUT_POST, 'cancel_order_id', FILTER_VALIDATE_INT);
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(403);
        exit('Invalid request. Please return to My Orders and try again.');
    }

    if ($orderId && $orderId > 0) {
        // Buyers can cancel only their own pending orders.
        $cancelStmt = $pdo->prepare(
            "UPDATE orders
             SET status = 'Cancelled'
             WHERE id = ?
               AND user_id = ?
               AND LOWER(status) = 'pending'"
        );

        $cancelStmt->execute([$orderId, $userId]);
    }

    // Prevent the form from being submitted again on refresh.
    header('Location: my-orders.php');
    exit;
}

// Retrieve orders belonging to this buyer only.
$stmt = $pdo->prepare(
    'SELECT id, total_amount, status, payment_method, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC'
);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// Retrieve the items for each order.
$itemStmt = $pdo->prepare(
    'SELECT product_name, quantity, price, subtotal
     FROM order_items
     WHERE order_id = ?
     ORDER BY id ASC'
);

function ordersEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Megatronix</title>

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


    <main class="orders-page">
        <div class="orders-container">

            <h1>My Orders</h1>
            <p class="orders-subtitle">
                View your purchases and track their current status.
            </p>

            <?php if (empty($orders)): ?>

                <section class="orders-empty">
                    <h2>No orders yet</h2>
                    <p>Your completed checkout orders will appear here.</p>
                    <a href="products.php" class="orders-button">
                        Browse Products
                    </a>
                </section>

            <?php else: ?>

                <?php foreach ($orders as $order): ?>

                    <section class="order-card">

                        <div class="order-card-header">
                            <div>
                                <h2>Order #<?= (int) $order['id'] ?></h2>
                                <p>
                                    Placed on
                                    <?= ordersEscape(date(
                                        'M d, Y \a\t h:i A',
                                        strtotime($order['created_at'])
                                    )) ?>
                                </p>
                            </div>

                            <span class="order-status order-status-<?= strtolower(
                                                                        ordersEscape($order['status'])
                                                                    ) ?>">
                                <?= ordersEscape($order['status']) ?>
                            </span>
                        </div>

                        <div class="order-items">
                            <?php
                            $itemStmt->execute([(int) $order['id']]);
                            $items = $itemStmt->fetchAll();
                            ?>

                            <?php foreach ($items as $item): ?>
                                <div class="order-item">
                                    <div>
                                        <strong>
                                            <?= ordersEscape($item['product_name']) ?>
                                        </strong>
                                        <p>
                                            Quantity: <?= (int) $item['quantity'] ?>
                                            × ₱<?= number_format((float) $item['price'], 2) ?>
                                        </p>
                                    </div>

                                    <span>
                                        ₱<?= number_format((float) $item['subtotal'], 2) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="order-card-footer">
                            <span>
                                <?= ordersEscape($order['payment_method']) ?>
                            </span>

                            <div class="order-total">
                                Total:
                                <strong>
                                    ₱<?= number_format((float) $order['total_amount'], 2) ?>
                                </strong>
                            </div>
                        </div>

                        <?php if (strtolower($order['status']) === 'pending'): ?>
                            <div class="order-cancel-section">
                                <form
                                    action="my-orders.php"
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to cancel Order #<?= (int) $order['id'] ?>? This action cannot be undone.');">

                                    <input
                                        type="hidden"
                                        name="cancel_order_id"
                                        value="<?= (int) $order['id'] ?>">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= ordersEscape($_SESSION['csrf_token']) ?>">

                                    <button type="submit" class="cancel-order-button">
                                        Cancel Order
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    </section>

                <?php endforeach; ?>

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