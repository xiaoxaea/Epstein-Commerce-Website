
<?php
session_start();

$currentPage = basename($_SERVER['PHP_SELF']);

// Remove all session variables.
$_SESSION = [];

// Destroy the current session.
session_destroy();

// Return to the homepage.
header('Location: index.php');
exit;
