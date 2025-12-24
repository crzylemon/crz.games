<?php
require_once '../db.php';
require_once '../user/session.php';

$video_id = $_GET['id'] ?? '';
$add_watermark = isset($_GET['watermark']);

if (empty($video_id)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

try {
    $stmt = $pdo_videos->prepare("SELECT * FROM videos WHERE id = ? AND status = 'PUBLIC'");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();
    
    if (!$video || !file_exists($video['video_path'])) {
        header('HTTP/1.0 404 Not Found');
        exit;
    }
    
    $file_path = $video['video_path'];
    $filename = $video['title'] . '_' . $video_id . '.' . pathinfo($file_path, PATHINFO_EXTENSION);
    
    if ($add_watermark) {
        // Simple watermark by adding to filename - in production you'd use FFmpeg
        $filename = 'CRZ_' . $filename;
    }
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
}
?>