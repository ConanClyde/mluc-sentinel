<?php
/**
 * Sticker Generator for MLUC Sentinel
 * Creates sticker images with QR codes, sticker numbers, and title
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;

class StickerGenerator {
    
    /**
     * Generate a sticker image with QR code
     * 
     * @param string $stickerNumber The sticker number
     * @param string $color The sticker color
     * @param int $width Image width (default: 400)
     * @param int $height Image height (default: 300)
     * @return string Path to the generated sticker image
     */
    public static function generateSticker($stickerNumber, $color = 'blue', $width = 400, $height = 300, $qrData = null) {
        // Debug: Log the start of sticker generation
        error_log("StickerGenerator: Starting sticker generation for number {$stickerNumber}, color {$color}");
        
        // Create the main image
        $image = imagecreatetruecolor($width, $height);
        
        // Define colors
        $colors = self::getColorPalette($image);
        $bgColor = $colors[$color] ?? $colors['black'];
        
        // Fill background
        imagefill($image, 0, 0, $bgColor);
        
        // Create white rounded rectangle for QR code area
        $qrAreaX = 50;
        $qrAreaY = 30;
        $qrAreaWidth = 300;
        $qrAreaHeight = 200;
        
        // Draw white rounded rectangle
        self::drawRoundedRectangle($image, $qrAreaX, $qrAreaY, $qrAreaWidth, $qrAreaHeight, 15, $colors['white']);
        
        // Generate QR code
        $qrDataToUse = $qrData ?: "MLUC-SENTINEL-" . $stickerNumber;
        $qrCodePath = self::generateQRCode($qrDataToUse, $qrAreaWidth - 20, $qrAreaHeight - 20);
        
        // Load and resize QR code image
        $qrImage = imagecreatefrompng($qrCodePath);
        $qrImageResized = imagescale($qrImage, $qrAreaWidth - 20, $qrAreaHeight - 20);
        
        // Calculate position to center QR code
        $qrX = $qrAreaX + 10;
        $qrY = $qrAreaY + 10;
        
        // Copy QR code onto main image
        imagecopy($image, $qrImageResized, $qrX, $qrY, 0, 0, $qrAreaWidth - 20, $qrAreaHeight - 20);
        
        // Clean up QR code images
        imagedestroy($qrImage);
        imagedestroy($qrImageResized);
        unlink($qrCodePath); // Remove temporary QR code file
        
        // Add title "MLUC SENTINEL"
        $title = "MLUC SENTINEL";
        $titleFontSize = 5;
        $titleX = $width / 2 - (strlen($title) * imagefontwidth($titleFontSize)) / 2;
        $titleY = $qrAreaY + $qrAreaHeight + 20;
        
        // Set text color based on background
        $textColor = ($bgColor == $colors['white']) ? $colors['black'] : $colors['white'];
        imagestring($image, $titleFontSize, $titleX, $titleY, $title, $textColor);
        
        // Add sticker number
        $stickerText = $stickerNumber;
        $stickerFontSize = 4;
        $stickerX = $width / 2 - (strlen($stickerText) * imagefontwidth($stickerFontSize)) / 2;
        $stickerY = $titleY + 25;
        imagestring($image, $stickerFontSize, $stickerX, $stickerY, $stickerText, $textColor);
        
        // Create uploads directory if it doesn't exist
        $uploadDir = __DIR__ . '/../public/pages/register/uploads/stickers/';
        error_log("StickerGenerator: Upload directory path: {$uploadDir}");
        
        if (!is_dir($uploadDir)) {
            error_log("StickerGenerator: Creating upload directory");
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("StickerGenerator: Failed to create upload directory");
                throw new Exception("Failed to create upload directory: {$uploadDir}");
            }
        }
        
        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            error_log("StickerGenerator: Upload directory is not writable: {$uploadDir}");
            throw new Exception("Upload directory is not writable: {$uploadDir}");
        }
        
        // Generate filename
        $filename = 'sticker_' . $stickerNumber . '_' . $color . '.png';
        $filepath = $uploadDir . $filename;
        error_log("StickerGenerator: Full file path: {$filepath}");
        
        // Save image
        if (!imagepng($image, $filepath)) {
            error_log("StickerGenerator: Failed to save image to {$filepath}");
            throw new Exception("Failed to save image to {$filepath}");
        }
        
        imagedestroy($image);
        
        // Verify file was created
        if (!file_exists($filepath)) {
            error_log("StickerGenerator: File was not created at {$filepath}");
            throw new Exception("File was not created at {$filepath}");
        }
        
        $returnPath = 'uploads/stickers/' . $filename;
        error_log("StickerGenerator: Successfully created sticker at {$returnPath}");
        
        return $returnPath;
    }
    
    /**
     * Generate QR code using Endroid QR Code library
     */
    private static function generateQRCode($data, $size = 200) {
        try {
            error_log("StickerGenerator: Generating QR code for data: {$data}");
            
            // Create QR code
            $qrCode = new QrCode(
                data: $data,
                size: $size,
                margin: 10,
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255)
            );
            
            // Create writer
            $writer = new PngWriter();
            
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
            $tempFile .= '.png';
            error_log("StickerGenerator: Temporary QR file: {$tempFile}");
            
            // Write QR code to temporary file
            $result = $writer->write($qrCode);
            if (!file_put_contents($tempFile, $result->getString())) {
                throw new Exception("Failed to write QR code to temporary file");
            }
            
            error_log("StickerGenerator: QR code generated successfully");
            return $tempFile;
            
        } catch (Exception $e) {
            error_log("StickerGenerator: QR code generation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get color palette for the image
     */
    private static function getColorPalette($image) {
        return [
            'black' => imagecolorallocate($image, 0, 0, 0),
            'white' => imagecolorallocate($image, 255, 255, 255),
            'red' => imagecolorallocate($image, 255, 0, 0),
            'green' => imagecolorallocate($image, 0, 255, 0),
            'blue' => imagecolorallocate($image, 0, 0, 255),
            'yellow' => imagecolorallocate($image, 255, 255, 0),
            'orange' => imagecolorallocate($image, 255, 165, 0),
            'pink' => imagecolorallocate($image, 255, 192, 203),
            'maroon' => imagecolorallocate($image, 128, 0, 0)
        ];
    }
    
    /**
     * Draw a rounded rectangle
     */
    private static function drawRoundedRectangle($image, $x, $y, $width, $height, $radius, $color) {
        // Draw main rectangle
        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $color);
        imagefilledrectangle($image, $x, $y + $radius, $x + $width, $y + $height - $radius, $color);
        
        // Draw corner circles
        imagefilledellipse($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $width - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $radius, $y + $height - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x + $width - $radius, $y + $height - $radius, $radius * 2, $radius * 2, $color);
    }
    
    /**
     * Generate sticker for a specific vehicle
     */
    public static function generateVehicleSticker($stickerNumber, $vehicleType, $plateNumber, $color) {
        $qrData = json_encode([
            'type' => 'MLUC-SENTINEL',
            'sticker' => $stickerNumber,
            'vehicle' => $vehicleType,
            'plate' => $plateNumber,
            'color' => $color,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        return self::generateSticker($stickerNumber, $color, 400, 300, $qrData);
    }
}
