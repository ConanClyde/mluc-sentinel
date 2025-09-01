<?php
require_once __DIR__ . '/../../config/StickerGenerator.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Sticker Generator Debug</h1>";

// Test 1: Check if GD extension is available
echo "<h2>1. GD Extension Check</h2>";
if (extension_loaded('gd')) {
    echo "✅ GD extension is loaded<br>";
    echo "GD Version: " . gd_info()['GD Version'] . "<br>";
} else {
    echo "❌ GD extension is not loaded<br>";
}

// Test 2: Check if Endroid QR Code library is available
echo "<h2>2. Endroid QR Code Library Check</h2>";
try {
    $qrCode = new Endroid\QrCode\QrCode('test');
    echo "✅ Endroid QR Code library is working<br>";
} catch (Exception $e) {
    echo "❌ Endroid QR Code library error: " . $e->getMessage() . "<br>";
}

// Test 3: Check uploads directory
echo "<h2>3. Uploads Directory Check</h2>";
$uploadDir = __DIR__ . '/../../uploads/stickers/';
echo "Upload directory: " . $uploadDir . "<br>";
if (is_dir($uploadDir)) {
    echo "✅ Upload directory exists<br>";
} else {
    echo "❌ Upload directory does not exist<br>";
    echo "Attempting to create directory...<br>";
    if (mkdir($uploadDir, 0755, true)) {
        echo "✅ Upload directory created successfully<br>";
    } else {
        echo "❌ Failed to create upload directory<br>";
    }
}

if (is_writable($uploadDir)) {
    echo "✅ Upload directory is writable<br>";
} else {
    echo "❌ Upload directory is not writable<br>";
}

// Test 4: Test sticker generation
echo "<h2>4. Sticker Generation Test</h2>";
try {
    $stickerPath = StickerGenerator::generateVehicleSticker('0001', 'car', 'ABC123', 'blue');
    echo "✅ Sticker generated successfully<br>";
    echo "Sticker path: " . $stickerPath . "<br>";
    
    $fullPath = __DIR__ . '/../../' . $stickerPath;
    if (file_exists($fullPath)) {
        echo "✅ Sticker file exists at: " . $fullPath . "<br>";
        echo "File size: " . filesize($fullPath) . " bytes<br>";
        echo "<img src='" . $stickerPath . "' alt='Generated Sticker' style='border: 1px solid #ccc; max-width: 300px;'><br>";
    } else {
        echo "❌ Sticker file does not exist at: " . $fullPath . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Sticker generation failed: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 5: Test different vehicle types
echo "<h2>5. Different Vehicle Types Test</h2>";
$testCases = [
    ['type' => 'car', 'plate' => 'ABC123', 'color' => 'blue'],
    ['type' => 'motorcycle', 'plate' => 'XYZ789', 'color' => 'pink'],
    ['type' => 'electric_bike', 'plate' => 'N/A', 'color' => 'white']
];

foreach ($testCases as $test) {
    try {
        $stickerPath = StickerGenerator::generateVehicleSticker('0002', $test['type'], $test['plate'], $test['color']);
        echo "✅ Generated sticker for {$test['type']} ({$test['color']})<br>";
    } catch (Exception $e) {
        echo "❌ Failed to generate sticker for {$test['type']}: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>6. Database Schema Check</h2>";
echo "Make sure your vehicles table has the sticker_image column:<br>";
echo "<code>ALTER TABLE vehicles ADD COLUMN sticker_image VARCHAR(255) AFTER sticker_number;</code><br>";

echo "<h2>7. Test Form</h2>";
?>
<form method="POST" action="">
    <label>Sticker Number: <input type="text" name="sticker_number" value="0003"></label><br>
    <label>Vehicle Type: 
        <select name="vehicle_type">
            <option value="car">Car</option>
            <option value="motorcycle">Motorcycle</option>
            <option value="electric_bike">Electric Bike</option>
        </select>
    </label><br>
    <label>Plate Number: <input type="text" name="plate_number" value="TEST123"></label><br>
    <label>Color: 
        <select name="color">
            <option value="blue">Blue</option>
            <option value="red">Red</option>
            <option value="green">Green</option>
            <option value="yellow">Yellow</option>
            <option value="orange">Orange</option>
            <option value="pink">Pink</option>
            <option value="white">White</option>
            <option value="black">Black</option>
        </select>
    </label><br>
    <button type="submit">Generate Test Sticker</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>8. Form Test Result</h2>";
    try {
        $stickerPath = StickerGenerator::generateVehicleSticker(
            $_POST['sticker_number'],
            $_POST['vehicle_type'],
            $_POST['plate_number'],
            $_POST['color']
        );
        echo "✅ Form test successful!<br>";
        echo "Generated sticker: <img src='" . $stickerPath . "' alt='Test Sticker' style='border: 1px solid #ccc; max-width: 300px;'><br>";
    } catch (Exception $e) {
        echo "❌ Form test failed: " . $e->getMessage() . "<br>";
    }
}
?>
