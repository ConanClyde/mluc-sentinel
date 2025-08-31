<?php
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../config/Session.php';

Session::start();
$database = new Database();
$db = $database->getConnection();

$message = "";




if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $middle_name = $_POST['middle_name'] ?: null;
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $school_id = trim($_POST['school_id']);
    $position = trim($_POST['position']);

    try {

        // Check for duplicate school_id
        $check = $db->prepare("SELECT COUNT(*) FROM student_officials WHERE school_id=:sid");
        $check->execute([':sid' => $school_id]);
        if ($check->fetchColumn() > 0) {
            $message = "⚠️ School ID already registered as official!";
        }
        // Insert into users
        $sql = "INSERT INTO users (user_type, username, email, password, first_name, middle_name, last_name) 
                VALUES ('student_official', :username, :email, :password, :first_name, :middle_name, :last_name)";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':username', $username);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password', $password);
        $stmt->bindValue(':first_name', $first_name);
        $stmt->bindValue(':middle_name', $middle_name);
        $stmt->bindValue(':last_name', $last_name);
        $stmt->execute();
        $user_id = $db->lastInsertId();

        // Insert into student_officials
        $sql2 = "INSERT INTO student_officials (user_id, position, school_id) 
                 VALUES (:user_id, :position, :school_id)";
        $stmt2 = $db->prepare($sql2);
        $stmt2->bindValue(':user_id', $user_id);
        $stmt2->bindValue(':position', $position);
        $stmt2->bindValue(':school_id', $school_id);
        $stmt2->execute();

        $message = "✅ Student Official registered successfully!";
    } catch (PDOException $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}
?>

<h1>Register Student Official</h1>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>

<form method="POST">
    <label>First Name:</label><br>
    <input type="text" name="first_name" required><br><br>

    <label>Middle Name (optional):</label><br>
    <input type="text" name="middle_name"><br><br>

    <label>Last Name:</label><br>
    <input type="text" name="last_name" required><br><br>

    <label>School ID:</label><br>
    <input type="text" name="school_id" required><br><br>

    <label>Position:</label><br>
    <input type="text" name="position" required><br><br>

    <label>Username:</label><br>
    <input type="text" name="username" required><br><br>

    <label>Email:</label><br>
    <input type="email" name="email" required><br><br>

    <label>Password:</label><br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Register Student Official</button>
</form>
