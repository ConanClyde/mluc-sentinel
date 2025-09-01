<?php
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../config/Session.php';
require_once __DIR__ . '/../../../config/ensureStickerCounters.php';
require_once __DIR__ . '/../../../config/StickerGenerator.php';

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
    $upd->execute([':counter'=>$counter, ':color'=>$color]);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log all POST data
    error_log("POST data: " . json_encode($_POST));
    
    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '') ?: null;
    $last_name    = trim($_POST['last_name'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $raw_password = $_POST['password'] ?? '';
    $personnel_id = trim($_POST['personnel_id'] ?? '');
    $phone        = trim($_POST['phone'] ?? '') ?: null;

    // Collect vehicles dynamically
    $vehicles = [];
    if (isset($_POST['vehicle_type']) && is_array($_POST['vehicle_type'])) {
        foreach ($_POST['vehicle_type'] as $idx => $vt) {
            $pl = trim($_POST['plate_number'][$idx] ?? '');
            
            // Skip empty vehicle types
            if (empty($vt)) {
                continue;
            }
            
            // For electric bikes, use a placeholder plate number
            if ($vt === 'electric_bike') {
                $pl = 'N/A';
            }
            
            // For motorcycles and cars, require plate number
            if (($vt === 'motorcycle' || $vt === 'car') && empty($pl)) {
                $message = "⚠ Plate number is required for {$vt}s.";
                break; // Stop processing and show error
            }
            
            $vehicles[] = ['type' => $vt, 'plate' => $pl];
        }
    }
    
    // Debug: Log vehicle data
    error_log("Vehicles collected: " . json_encode($vehicles));

    if (!$first_name || !$last_name || !$username || !$email || !$raw_password || !$personnel_id) {
        $message = "Missing required fields.";
    } elseif (empty($vehicles)) {
        $message = "⚠ At least one vehicle is required.";
    } else {
        try {
            $chk1 = $db->prepare("SELECT COUNT(*) FROM personnel WHERE personnel_id = :pid");
            $chk1->execute([':pid'=>$personnel_id]);
            if ($chk1->fetchColumn() > 0) {
                $message = "⚠️ Personnel ID already exists.";
            } else {
                $chk2 = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :u OR email = :e");
                $chk2->execute([':u'=>$username, ':e'=>$email]);
                if ($chk2->fetchColumn() > 0) {
                    $message = "⚠️ Username or Email already exists.";
                } else {
                    $db->beginTransaction();

                    $license_img = null;
                    if (!empty($_FILES['licensed_id_image']['name'])) {
                        $license_img = saveLicenseImage($_FILES['licensed_id_image']);
                    }
                    $licensed_id = trim($_POST['licensed_id'] ?? '') ?: null;
                    $expiration_date = date('Y-m-d', strtotime('+1 year'));

                    $passHash = password_hash($raw_password, PASSWORD_BCRYPT);
                    $u = $db->prepare("INSERT INTO users (user_type, username, email, password, first_name, middle_name, last_name) 
                        VALUES ('personnel', :u, :e, :p, :fn, :mn, :ln)");
                    $u->execute([
                        ':u'=>$username, ':e'=>$email, ':p'=>$passHash,
                        ':fn'=>$first_name, ':mn'=>$middle_name, ':ln'=>$last_name
                    ]);
                    $user_id = $db->lastInsertId();

                    $p = $db->prepare("INSERT INTO personnel (user_id, personnel_id, licensed_id, licensed_id_image, phone, expiration_date) 
                        VALUES (:uid, :pid, :lic, :licimg, :phone, :exp)");
                    $p->execute([
                        ':uid'=>$user_id, ':pid'=>$personnel_id,
                        ':lic'=>$licensed_id, ':licimg'=>$license_img,
                        ':phone'=>$phone, ':exp'=>$expiration_date
                    ]);

                    foreach ($vehicles as $v) {
                        $allowedTypes = ['motorcycle','car','electric_bike'];
                        if (!in_array($v['type'],$allowedTypes)) throw new Exception("Invalid vehicle type.");

                        $color = 'maroon'; // ✅ Always maroon for personnel
                        $sticker_no = generateStickerNumber($db, $color);

                        // Generate sticker image
                        try {
                            // Debug: Log the parameters being passed
                            error_log("Attempting to generate sticker: Number={$sticker_no}, Type={$v['type']}, Plate={$v['plate']}, Color={$color}");
                            
                            $sticker_image_path = StickerGenerator::generateVehicleSticker($sticker_no, $v['type'], $v['plate'], $color);
                            
                            // Debug: Log the returned path
                            error_log("Sticker generated successfully: {$sticker_image_path}");
                            
                            $insv = $db->prepare("INSERT INTO vehicles 
                                (user_id, vehicle_type, plate_number, sticker_color, sticker_number, sticker) 
                                VALUES (:uid, :vt, :plate, :color, :snum, :sticker_img)");
                            $insv->execute([
                                ':uid'=>$user_id,
                                ':vt'=>$v['type'],
                                ':plate'=>$v['plate'],
                                ':color'=>$color,
                                ':snum'=>$sticker_no,
                                ':sticker_img'=>$sticker_image_path
                            ]);
                            
                            // Debug: Log successful database insert
                            error_log("Vehicle inserted with sticker image: {$sticker_image_path}");
                            
                        } catch (Exception $stickerError) {
                            // Log sticker generation error but continue with registration
                            error_log("Sticker generation failed for vehicle {$v['type']} {$v['plate']}: " . $stickerError->getMessage());
                            error_log("Error details: " . $stickerError->getTraceAsString());
                            
                            // Insert vehicle without sticker image
                            $insv = $db->prepare("INSERT INTO vehicles 
                                (user_id, vehicle_type, plate_number, sticker_color, sticker_number, sticker) 
                                VALUES (:uid, :vt, :plate, :color, :snum, NULL)");
                            $insv->execute([
                                ':uid'=>$user_id,
                                ':vt'=>$v['type'],
                                ':plate'=>$v['plate'],
                                ':color'=>$color,
                                ':snum'=>$sticker_no
                            ]);
                            
                            // Debug: Log fallback insert
                            error_log("Vehicle inserted without sticker image due to error");
                        }
                    }

                    $db->commit();
                    $message = "✅ Personnel registered successfully!";
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message = "❌ Error: ".$e->getMessage();
        }
    }
}
?>



<h1>Register Personnel</h1>
<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="form-container">
    <label>First Name:</label>
    <input type="text" name="first_name" required>

    <label>Middle Name (optional):</label>
    <input type="text" name="middle_name">

    <label>Last Name:</label>
    <input type="text" name="last_name" required>

    <label>Personnel ID:</label>
    <input type="text" name="personnel_id" required>

    <label>Username:</label>
    <input type="text" name="username" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Password:</label>
    <input type="password" name="password" required>

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
            <input type="text" name="plate_number[]" required>

            <button type="button" class="remove-vehicle">❌ Remove</button>
        </div>
    </div>

    <button type="button" id="add-vehicle">+ Add Vehicle</button>

    <br><br>
    <button type="submit" onclick="enableAllFields()">Register Personnel</button>
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

// ✅ handle plate number visibility and requirements
function togglePlateInput(select) {
    const plateInput = select.closest(".vehicle-box").querySelector("input[name='plate_number[]']");
    const plateLabel = plateInput.previousElementSibling;
    
    if (select.value === "electric_bike") {
        plateInput.value = ""; // clear value
        plateInput.disabled = true;
        plateInput.required = false;
        plateInput.style.display = "none";
        plateLabel.style.display = "none"; // hide label
    } else if (select.value === "motorcycle") {
        plateInput.disabled = false;
        plateInput.required = true;
        plateInput.style.display = "block";
        plateLabel.style.display = "block"; // show label
        plateLabel.textContent = "Plate Number (required):";
    } else if (select.value === "car") {
        plateInput.disabled = false;
        plateInput.required = true;
        plateInput.style.display = "block";
        plateLabel.style.display = "block"; // show label
        plateLabel.textContent = "Plate Number (required):";
    } else {
        plateInput.disabled = false;
        plateInput.required = false;
        plateInput.style.display = "block";
        plateLabel.style.display = "block"; // show label
        plateLabel.textContent = "Plate Number:";
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
        <input type="text" name="plate_number[]" required>

        <button type="button" class="remove-vehicle">❌ Remove</button>
    `;
    container.appendChild(box);

    attachRemoveHandler(box.querySelector(".remove-vehicle"));
    attachTypeHandler(box.querySelector("select"));
});

// Function to enable all fields before form submission
function enableAllFields() {
    const form = document.querySelector('.form-container');
    const disabledInputs = form.querySelectorAll('input[disabled]');
    disabledInputs.forEach(input => {
        input.disabled = false;
    });
}
</script>


