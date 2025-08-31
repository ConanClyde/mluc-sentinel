<?php
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../config/Session.php';

Session::start();
$db = (new Database())->getConnection();
$message = "";

// --- helpers ---
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

function generateStickerNumber(PDO $db, string $color) {
    $stmt = $db->prepare("SELECT counter FROM sticker_counters WHERE color = :color FOR UPDATE");
    $stmt->execute([':color' => $color]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception("Sticker color not configured: {$color}");
    $counter = intval($row['counter']) + 1;

    // update the counter in DB
    $upd = $db->prepare("UPDATE sticker_counters SET counter = :counter WHERE color = :color");
    $upd->execute([':counter' => $counter, ':color' => $color]);

    return str_pad($counter, 4, '0', STR_PAD_LEFT); // e.g. 0001, 0002...
}

// --- handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '') ?: null;
    $last_name   = trim($_POST['last_name'] ?? '');
    $licensed_id = trim($_POST['licensed_id'] ?? '') ?: null;
    $phone       = trim($_POST['phone'] ?? '') ?: null;

    // collect vehicles dynamically
    $vehicles = [];
    if (isset($_POST['vehicle_type']) && is_array($_POST['vehicle_type'])) {
        foreach ($_POST['vehicle_type'] as $idx => $vt) {
            $pl = trim($_POST['plate_number'][$idx] ?? '');
            if ($vt && $pl) $vehicles[] = ['type'=>$vt, 'plate'=>$pl];
        }
    }

    if (!$first_name || !$last_name) {
        $message = "⚠ First and last name required.";
    } elseif (count($vehicles) > 3) {
        $message = "⚠ Maximum 3 vehicles allowed.";
    } else {
        try {
            $db->beginTransaction();

            $license_img_path = null;
            if (!empty($_FILES['licensed_id_image']['name'])) {
                $license_img_path = saveLicenseImage($_FILES['licensed_id_image']);
            }
            $expiration_date = date('Y-m-d', strtotime('+1 year'));

            // users
            $uq = $db->prepare("INSERT INTO users (user_type, first_name, middle_name, last_name) 
                                VALUES ('parent', :fn, :mn, :ln)");
            $uq->execute([':fn'=>$first_name, ':mn'=>$middle_name, ':ln'=>$last_name]);
            $user_id = $db->lastInsertId();

            // parents
            $p = $db->prepare("INSERT INTO parents (user_id, expiration_date, licensed_id, licensed_id_image) 
                               VALUES (:uid, :exp, :lic, :licimg)");
            $p->execute([
                ':uid'=>$user_id,
                ':exp'=>$expiration_date,
                ':lic'=>$licensed_id,
                ':licimg'=>$license_img_path
            ]);

            // vehicles — always white stickers
            foreach ($vehicles as $v) {
                $allowedTypes = ['motorcycle','car','electric_bike'];
                if (!in_array($v['type'],$allowedTypes)) throw new Exception("Invalid vehicle type.");
                
                $color = 'white'; // ✅ force white for all parent vehicles
                $snum  = generateStickerNumber($db, $color);

                $insv = $db->prepare("INSERT INTO vehicles 
                    (user_id, vehicle_type, plate_number, sticker_color, sticker_number) 
                    VALUES (:uid, :vt, :plate, :color, :snum)");
                $insv->execute([
                    ':uid'=>$user_id,
                    ':vt'=>$v['type'],
                    ':plate'=>$v['plate'],
                    ':color'=>$color,
                    ':snum'=>$snum
                ]);
            }

            $db->commit();
            $message = "✅ Parent registered successfully!";
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message = "❌ Error: " . $e->getMessage();
        }
    }
}
?>


<h1>Register Parent / Guardian</h1>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="form-container">
    <label>First Name:</label>
    <input type="text" name="first_name" required>

    <label>Middle Name (optional):</label>
    <input type="text" name="middle_name">

    <label>Last Name:</label>
    <input type="text" name="last_name" required>

    <label>Phone (optional):</label>
    <input type="text" name="phone">

    <label>License Number (optional):</label>
    <input type="text" name="licensed_id">

    <label>License Image (JPG/PNG, max 5MB):</label>
    <input type="file" name="licensed_id_image" accept=".jpg,.jpeg,.png">

    <h3>Vehicles</h3>
    <div id="vehicles-container">
        <!-- one vehicle block by default -->
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
    <button type="submit">Register Parent</button>
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
.form-container label {
    font-weight: bold;
    margin-top: 8px;
}
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
    position: relative;
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
.vehicle-box .remove-vehicle:hover {
    background: #b02a37;
}
#add-vehicle {
    background: #28a745;
    color: #fff;
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
#add-vehicle:hover {
    background: #218838;
}
button[type=submit] {
    background: #162051;
    color: #fff;
    padding: 10px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
button[type=submit]:hover {
    background: #0f1530;
}
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

