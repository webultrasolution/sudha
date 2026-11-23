<?php
/**
 * Safe file name for logo / signature / letterhead images saved into assets/images/.
 * Accepts only real images (extension whitelist + getimagesize; HEIC/HEIF by extension and size since GD can't read them).
 * Returns a generated name like "entity_1795461234_a1b2c3d4.png", or null when the upload is missing or not an image.
 */
function safeImageUploadName(array $file, $prefix) {
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return null;
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > 10 * 1024 * 1024) {
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $raster = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $raster, true)) {
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return null;
        }
    } elseif (!in_array($ext, ['heic', 'heif'], true)) {
        return null;
    }
    $prefix = preg_replace('/[^a-z0-9_]/i', '', (string)$prefix);
    return $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
}
