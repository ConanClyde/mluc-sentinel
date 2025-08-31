<?php
require_once __DIR__ . '/../config/Session.php';
Session::start();

// Redirect if not logged in or not admin
if (!Session::get('user_id') || Session::get('user_type') !== 'admin') {
    header("Location: index.php");
    exit;
}

$page = $_GET['page'] ?? 'dashboard';
$pageFile = __DIR__ . "/pages/{$page}.php";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Mluc Sentinel</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
        }
        main {
            margin-left: 240px; /* sidebar width */
            padding: 1.5rem;
            flex-grow: 1;
            background: #f5f5f5;
            min-height: 100vh;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <?php include __DIR__ . "/../includes/sidebar.php"; ?>

    <!-- Main Content -->
    <main>
        <?php
        if (file_exists($pageFile)) {
            include $pageFile;
        } else {
            echo "<h2>404 - Page not found</h2>";
        }
        ?>
    </main>

</body>
</html>
