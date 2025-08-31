<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Session.php';

Session::start();

$database = new Database();
$db = $database->getConnection();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = isset($_POST['username']) ? trim($_POST['username']) : null;
    $email       = isset($_POST['email']) ? trim($_POST['email']) : null;
    $passwordRaw = $_POST['password'] ?? '';
    $first_name  = trim($_POST['first_name'] ?? '');
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : null; // optional
    $last_name   = trim($_POST['last_name'] ?? '');
    $user_type   = "admin"; // force admin

    // Server-side validation (admins must have username, email, password)
    if (!$username || !$email || !$passwordRaw) {
        $message = "⚠️ Username, Email, and Password are required for admin accounts.";
    } else {
        $password = password_hash($passwordRaw, PASSWORD_BCRYPT);

        try {
            // Insert into users (middle_name optional)
            $sql = "INSERT INTO users 
                        (user_type, username, email, password, first_name, middle_name, last_name) 
                    VALUES 
                        (:user_type, :username, :email, :password, :first_name, :middle_name, :last_name)";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_type',   $user_type);
            $stmt->bindValue(':username',    $username);
            $stmt->bindValue(':email',       $email);
            $stmt->bindValue(':password',    $password);
            $stmt->bindValue(':first_name',  $first_name);
            // bind null cleanly if middle_name is empty
            $stmt->bindValue(':middle_name', $middle_name !== '' ? $middle_name : null, $middle_name !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':last_name',   $last_name);

            if ($stmt->execute()) {
                $user_id = $db->lastInsertId();

                // Also insert into admins table
                $adminSql = "INSERT INTO admins (user_id) VALUES (:user_id)";
                $adminStmt = $db->prepare($adminSql);
                $adminStmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
                $adminStmt->execute();

                $message = "✅ Admin account created successfully! You can now login.";
            } else {
                $message = "❌ Failed to create account.";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // duplicate username/email
                $message = "⚠️ Username or Email already exists.";
            } else {
                $message = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Admin - Mluc Sentinel</title>
    
</head>
<body>
    <div class="signup-box">
        <h2>Create Admin Account</h2>

        <?php if (!empty($message)): ?>
            <p class="<?= strpos($message, '✅') !== false ? 'message' : 'error' ?>">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="first_name" placeholder="First Name" required>
            <input type="text" name="middle_name" placeholder="Middle Name (optional)">
            <input type="text" name="last_name" placeholder="Last Name" required>
            <input type="text" name="username" placeholder="Username (required)" required>
            <input type="email" name="email" placeholder="Email (required)" required>
            <input type="password" name="password" placeholder="Password (required)" required>
            <button type="submit">Create Admin</button>
        </form>

        <p class="muted">Already have an account? <a href="index.php">Login</a></p>
    </div>
</body>
</html>
