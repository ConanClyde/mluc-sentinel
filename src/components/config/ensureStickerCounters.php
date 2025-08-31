<?php
/**
 * Ensures that all required sticker colors exist in the sticker_counters table.
 * Run this at app startup (include in registration scripts).
 */

function ensureStickerCounters(PDO $db) {
    // Define all valid colors used in logic
    $requiredColors = ['green', 'yellow', 'red', 'orange', 'pink', 'blue', 'white', 'maroon'];

    foreach ($requiredColors as $color) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM sticker_counters WHERE color = :color");
        $stmt->execute([':color' => $color]);
        $exists = $stmt->fetchColumn();

        if (!$exists) {
            $ins = $db->prepare("INSERT INTO sticker_counters (color, counter) VALUES (:color, 0)");
            $ins->execute([':color' => $color]);
        }
    }
}
