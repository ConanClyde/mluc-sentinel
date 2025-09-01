# MLUC Sentinel Sticker Generator

This system automatically generates professional sticker images for vehicles with QR codes, sticker numbers, and the title "MLUC SENTINEL".

## Features

- **Automatic Sticker Generation**: Creates stickers automatically when vehicles are registered
- **QR Code Integration**: Each sticker contains a scannable QR code with vehicle information
- **Color Coding**: Different colors for different vehicle types
- **Professional Design**: Clean, modern appearance with rounded corners
- **High-Quality Output**: PNG format with customizable dimensions

## How It Works

### 1. Automatic Generation
When a student registers a vehicle, the system automatically:
- Generates a unique sticker number
- Determines the appropriate color based on vehicle type
- Creates a sticker image with QR code
- Saves the image to the uploads/stickers/ directory
- Stores the image path in the database

### 2. Color Logic
- **Cars**: Blue stickers
- **Electric Bikes**: White stickers  
- **Motorcycles**: Color based on last digit of plate number:
  - 1,2 → Green
  - 3,4 → Yellow
  - 5,6 → Red
  - 7,8 → Orange
  - 9,0 → Pink

### 3. Sticker Components
Each sticker contains:
- **Background**: Color-coded based on vehicle type
- **QR Code Area**: White rounded rectangle containing scannable QR code
- **Title**: "MLUC SENTINEL" prominently displayed
- **Sticker Number**: Unique identifier (e.g., "0001", "0002")

## File Structure

```
src/
├── components/
│   ├── config/
│   │   └── StickerGenerator.php          # Main sticker generation class
│   └── public/
│       ├── pages/
│       │   ├── register/
│       │   │   └── student.php           # Student registration with sticker generation
│       │   └── sticker-test.php          # Test page for sticker generation
│       └── uploads/
│           └── stickers/                 # Generated sticker images
```

## Usage

### Automatic Generation (Recommended)
Stickers are automatically generated when registering vehicles through the student registration form. No manual intervention required.

### Manual Generation
You can also generate stickers manually using the `StickerGenerator` class:

```php
require_once 'src/components/config/StickerGenerator.php';

// Generate a basic sticker
$stickerPath = StickerGenerator::generateSticker('0001', 'blue');

// Generate a vehicle-specific sticker with QR data
$stickerPath = StickerGenerator::generateVehicleSticker(
    '0001',           // Sticker number
    'car',            // Vehicle type
    'ABC123',         // Plate number
    'blue'            // Color
);
```

### Test Page
Visit `/src/components/public/pages/sticker-test.php` to test the sticker generation functionality with a web interface.

## Technical Details

### Dependencies
- **PHP GD Extension**: For image manipulation
- **Endroid QR Code Library**: For high-quality QR code generation
- **Composer**: For dependency management

### Image Specifications
- **Default Size**: 400x300 pixels
- **Format**: PNG with transparency support
- **QR Code**: High error correction level for reliable scanning
- **Colors**: 8 predefined colors with automatic text contrast

### QR Code Data
The QR code contains JSON data with:
```json
{
    "type": "MLUC-SENTINEL",
    "sticker": "0001",
    "vehicle": "car",
    "plate": "ABC123",
    "color": "blue",
    "timestamp": "2024-01-15 10:30:00"
}
```

## Database Integration

The system automatically adds a `sticker_image` column to the vehicles table to store the path to generated sticker images.

### SQL Schema Update
```sql
ALTER TABLE vehicles ADD COLUMN sticker_image VARCHAR(255) AFTER sticker_number;
```

## Customization

### Changing Sticker Dimensions
Modify the `generateSticker()` method parameters:
```php
$stickerPath = StickerGenerator::generateSticker('0001', 'blue', 600, 450);
```

### Adding New Colors
Update the `getColorPalette()` method in `StickerGenerator.php`:
```php
'purple' => imagecolorallocate($image, 128, 0, 128),
```

### Modifying QR Code Content
Edit the `generateVehicleSticker()` method to include additional data in the QR code.

## Troubleshooting

### Common Issues

1. **GD Extension Missing**
   - Error: "Call to undefined function imagecreatetruecolor()"
   - Solution: Install PHP GD extension

2. **Permission Errors**
   - Error: "Failed to create directory" or "Permission denied"
   - Solution: Ensure write permissions on uploads/stickers/ directory

3. **QR Code Not Generating**
   - Check that Endroid QR Code library is properly installed via Composer
   - Verify autoload.php path is correct

### Performance Notes
- Sticker generation adds minimal overhead to vehicle registration
- Images are cached in the uploads/stickers/ directory
- Consider implementing cleanup for old sticker images if storage becomes an issue

## Security Considerations

- Sticker images are stored in a public uploads directory
- QR codes contain vehicle information - ensure this aligns with privacy policies
- Consider implementing access controls if sticker images contain sensitive data

## Future Enhancements

- **Batch Generation**: Generate multiple stickers at once
- **Template System**: Customizable sticker designs
- **Watermarking**: Add security features to prevent counterfeiting
- **Export Options**: PDF generation for printing
- **API Endpoint**: RESTful API for sticker generation

## Support

For issues or questions regarding the sticker generator:
1. Check the troubleshooting section above
2. Verify all dependencies are properly installed
3. Check PHP error logs for detailed error messages
4. Ensure the uploads/stickers/ directory exists and is writable
