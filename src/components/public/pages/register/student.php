<?php
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../config/Session.php';
require_once __DIR__ . '/../../../config/ensureStickerCounters.php';

Session::start();
$db = (new Database())->getConnection();
$message = "";

// ✅ Ensure counters exist (prevents missing color errors)
ensureStickerCounters($db);

function generateStickerNumber(PDO $db, string $color) {
    $stmt = $db->prepare("SELECT counter FROM sticker_counters WHERE color = :color FOR UPDATE");
    $stmt->execute([':color' => $color]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception("Sticker color not configured: {$color}");
    $counter = intval($row['counter']) + 1;
    $upd = $db->prepare("UPDATE sticker_counters SET counter = :counter WHERE color = :color");
    $upd->execute([':counter' => $counter, ':color' => $color]);
    return str_pad($counter, 4, '0', STR_PAD_LEFT);
}

function saveLicenseImage($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['jpg','jpeg','png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) throw new Exception("License image must be JPG/PNG.");
    if ($file['size'] > 5 * 1024 * 1024) throw new Exception("License image too large (>5MB).");

    $uploadDir = __DIR__ . '/uploads/licenses/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $fileName = uniqid('lic_') . '.' . $ext;
    $dest = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception("Failed to save license image.");
    return 'uploads/licenses/' . $fileName;
}

// ✅ Fetch colleges from DB for dropdown
$colleges = [];
try {
    $stmt = $db->query("SELECT name FROM colleges ORDER BY name ASC");
    $colleges = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $colleges = []; // fallback if colleges table missing
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '') ?: null;
    $last_name   = trim($_POST['last_name'] ?? '');
    $school_id   = trim($_POST['school_id'] ?? '');
    $college     = trim($_POST['college'] ?? '');
    $phone       = trim($_POST['phone'] ?? '') ?: null;

    // Collect vehicles dynamically
    $vehicles = [];
    if (isset($_POST['vehicle_type']) && is_array($_POST['vehicle_type'])) {
        foreach ($_POST['vehicle_type'] as $idx => $vt) {
            $pl = trim($_POST['plate_number'][$idx] ?? '');
            if ($vt && $pl) $vehicles[] = ['type' => $vt, 'plate' => $pl];
        }
    }

    if (!$first_name || !$last_name || !$school_id) {
        $message = "⚠ First name, last name, and school ID are required.";
    } elseif (count($vehicles) > 3) {
        $message = "⚠ Maximum 3 vehicles allowed.";
    } elseif ($college && !in_array($college, $colleges)) {
        $message = "⚠ Invalid college selected.";
    } else {
        try {
            // Check duplicate school ID
            $chk = $db->prepare("SELECT COUNT(*) FROM students WHERE school_id = :sid");
            $chk->execute([':sid' => $school_id]);
            if ($chk->fetchColumn() > 0) {
                $message = "⚠ School ID already registered.";
            } else {
                $db->beginTransaction();

                $license_img_path = null;
                if (!empty($_FILES['licensed_id_image']['name'])) {
                    $license_img_path = saveLicenseImage($_FILES['licensed_id_image']);
                }
                $licensed_id = trim($_POST['licensed_id'] ?? '') ?: null;
                $expiration_date = date('Y-m-d', strtotime('+1 year'));

                // Insert into users
                $uq = $db->prepare("INSERT INTO users (user_type, first_name, middle_name, last_name) 
                                    VALUES ('student', :fn, :mn, :ln)");
                $uq->execute([':fn'=>$first_name, ':mn'=>$middle_name, ':ln'=>$last_name]);
                $user_id = $db->lastInsertId();

                // Insert into students
                $s = $db->prepare("INSERT INTO students 
                    (user_id, school_id, licensed_id, licensed_id_image, college, phone, expiration_date) 
                    VALUES (:uid, :sid, :lic, :lic_img, :col, :phone, :exp)");
                $s->execute([
                    ':uid'=>$user_id, ':sid'=>$school_id,
                    ':lic'=>$licensed_id, ':lic_img'=>$license_img_path,
                    ':col'=>$college, ':phone'=>$phone, ':exp'=>$expiration_date
                ]);

                // Insert vehicles
                foreach ($vehicles as $v) {
                    $allowedTypes = ['motorcycle','car','electric_bike'];
                    if (!in_array($v['type'], $allowedTypes)) throw new Exception("Invalid vehicle type.");

                    // Sticker color logic
                    if ($v['type'] === 'car') {
                        $color = 'blue';
                    } elseif ($v['type'] === 'electric_bike') {
                        $color = 'white';
                    } elseif ($v['type'] === 'motorcycle') {
                        $lastDigit = substr(preg_replace('/\D/', '', $v['plate']), -1);
                        switch ($lastDigit) {
                            case '1': case '2': $color = 'green'; break;
                            case '3': case '4': $color = 'yellow'; break;
                            case '5': case '6': $color = 'red'; break;
                            case '7': case '8': $color = 'orange'; break;
                            case '9': case '0': $color = 'pink'; break;
                            default: $color = 'white';
                        }
                    } else {
                        $color = 'white';
                    }

                    $sticker_no = generateStickerNumber($db, $color);

                    $insV = $db->prepare("INSERT INTO vehicles 
                        (user_id, vehicle_type, plate_number, sticker_color, sticker_number) 
                        VALUES (:uid, :type, :plate, :color, :snum)");
                    $insV->execute([
                        ':uid'=>$user_id,
                        ':type'=>$v['type'],
                        ':plate'=>$v['plate'],
                        ':color'=>$color,
                        ':snum'=>$sticker_no
                    ]);
                }

                $db->commit();
                $message = "✅ Student registered successfully!";
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message = "❌ Error: ".$e->getMessage();
        }
    }
}
?>

<h1>Register Student</h1>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="form-container">
    <label>First Name:</label>
    <input type="text" name="first_name" required>

    <label>Middle Name (optional):</label>
    <input type="text" name="middle_name">

    <label>Last Name:</label>
    <input type="text" name="last_name" required>

    <label>School ID:</label>
    <input type="text" name="school_id" required>

    <label>College:</label>
    <select name="college" required>
        <option value="">-- Select College --</option>
        <?php foreach ($colleges as $col): ?>
            <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Phone:</label>
    <input type="text" name="phone">

    <label>License Number (optional):</label>
    <input type="text" name="licensed_id">

    <label>License Image (JPG/PNG, max 5MB):</label>
    <input type="file" name="licensed_id_image" accept=".jpg,.jpeg,.png">

    <h3>Vehicles</h3>
    <div id="vehicles-container">
        <div class="vehicle-box">
            <label>Type:</label>
            <select name="vehicle_type[]">
                <option value="">-- none --</option>
                <option value="motorcycle">Motorcycle</option>
                <option value="car">Car</option>
                <option value="electric_bike">Electric Bike</option>
            </select>

            <label>Plate Number:</label>
            <input type="text" name="plate_number[]">

            <button type="button" class="remove-vehicle">❌ Remove</button>
        </div>
    </div>

    <button type="button" id="add-vehicle">+ Add Vehicle</button>
    <br><br>
    <button type="submit">Register Student</button>
</form>

<style>
.form-container {
    max-width: 500px;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.form-container label { font-weight: bold; margin-top: 8px; }
.form-container input, 
.form-container select {
    padding: 8px;
    width: 100%;
    border: 1px solid #ccc;
    border-radius: 4px;
}
.vehicle-box {
    border: 1px solid #ddd;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 12px;
    background: #f9f9f9;
}
.vehicle-box .remove-vehicle {
    background: #dc3545;
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 8px;
}
.vehicle-box .remove-vehicle:hover { background: #b02a37; }
#add-vehicle {
    background: #28a745;
    color: #fff;
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
#add-vehicle:hover { background: #218838; }
button[type=submit] {
    background: #162051;
    color: #fff;
    padding: 10px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
button[type=submit]:hover { background: #0f1530; }
</style>

<script>
const container = document.getElementById("vehicles-container");
const addBtn = document.getElementById("add-vehicle");

function attachRemoveHandler(btn) {
    btn.addEventListener("click", function() {
        btn.parentElement.remove();
    });
}

// ✅ handle plate number visibility
function togglePlateInput(select) {
    const plateInput = select.closest(".vehicle-box").querySelector("input[name='plate_number[]']");
    if (select.value === "electric_bike") {
        plateInput.value = ""; // clear value
        plateInput.disabled = true;
        plateInput.style.display = "none";
        plateInput.previousElementSibling.style.display = "none"; // hide label
    } else {
        plateInput.disabled = false;
        plateInput.style.display = "block";
        plateInput.previousElementSibling.style.display = "block"; // show label
    }
}

function attachTypeHandler(select) {
    select.addEventListener("change", function() {
        togglePlateInput(select);
    });
    // run once on load
    togglePlateInput(select);
}

// Attach to default vehicle box
attachRemoveHandler(document.querySelector(".remove-vehicle"));
attachTypeHandler(document.querySelector(".vehicle-box select"));

addBtn.addEventListener("click", function() {
    const count = container.querySelectorAll(".vehicle-box").length;
    if (count >= 3) {
        alert("Maximum 3 vehicles allowed.");
        return;
    }

    const box = document.createElement("div");
    box.className = "vehicle-box";
    box.innerHTML = `
        <label>Type:</label>
        <select name="vehicle_type[]">
            <option value="">-- none --</option>
            <option value="motorcycle">Motorcycle</option>
            <option value="car">Car</option>
            <option value="electric_bike">Electric Bike</option>
        </select>

        <label>Plate Number:</label>
        <input type="text" name="plate_number[]">

        <button type="button" class="remove-vehicle">❌ Remove</button>
    `;
    container.appendChild(box);

    attachRemoveHandler(box.querySelector(".remove-vehicle"));
    attachTypeHandler(box.querySelector("select"));
});
</script>