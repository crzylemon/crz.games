<?php
// Get banner settings from main database
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('banner_text', 'banner_type', 'banner_enabled')");
    $stmt->execute();
    $banner_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $banner_enabled = $banner_settings['banner_enabled'] ?? 0;
    $banner_text = $banner_settings['banner_text'] ?? '';
    $banner_type = $banner_settings['banner_type'] ?? 'info';
    
    if ($banner_enabled && !empty($banner_text)):
?>
<div class="site-banner banner-<?= htmlspecialchars($banner_type) ?>">
    <div class="banner-content">
        <?= htmlspecialchars($banner_text) ?>
    </div>
</div>
<?php
    endif;
} catch (Exception $e) {
    // Silently fail if banner can't be loaded
}
?>