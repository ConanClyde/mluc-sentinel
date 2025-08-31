<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';

Session::start();

$database = new Database();
$db = $database->getConnection();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = $_POST['login'];
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE (username = :login OR email = :login) AND is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':login', $login);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Save session
        Session::set('user_id', $user['id']);
        Session::set('user_type', $user['user_type']);
        Session::set('username', $user['username']);

        // Redirect by role
        switch ($user['user_type']) {
            case 'admin':
                header("Location: admin.php");
                break;
            case 'student':
                header("Location: client/student_dashboard.php");
                break;
            case 'personnel':
                header("Location: client/personnel_dashboard.php");
                break;
            case 'student_official':
                header("Location: client/official_dashboard.php");
                break;
            case 'parent':
                header("Location: client/parent_dashboard.php");
                break;
            default:
                header("Location: index.php");
        }
        exit;
    } else {
        $error = "Invalid login credentials.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Mluc Sentinel</title>
    
</head>
<body>
    <div class="login-box">
        <h2>Mluc Sentinel Login</h2>
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="text" name="login" placeholder="Username or Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p style="text-align:center; margin-top:10px;">
            Don't have an account? <a href="signup.php">Login</a>
        </p>
    </div>
</body>
</html>
