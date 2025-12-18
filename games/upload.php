<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../user/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $title));
    $description = $_POST['description'] ?? '';
    $genre = $_POST['genre'] ?? '';
    $hosting_type = $_POST['hosting_type'] ?? 'ZIP';
    $game_url = $_POST['game_url'] ?? '';
    $uses_crengine = isset($_POST['uses_crengine']) ? 1 : 0;
    $is_crengine_mod = isset($_POST['is_crengine_mod']) ? 1 : 0;
    $status = $_POST['status'] ?? 'DRAFT';
    
    // If not admin and trying to set to PLAYABLE, set to PENDING_APPROVAL instead
    if ($user['id'] != 1 && $status === 'PLAYABLE') {
        $status = 'PENDING_APPROVAL';
    }
    $whitelist_visibility = isset($_POST['whitelist_visibility']) ? 1 : 0;
    $whitelist_play = isset($_POST['whitelist_play']) ? 1 : 0;
    $current_version = $_POST['current_version'] ?? '0.1.0';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO games (slug, title, description, genre, owner_user_id, hosting_type, game_url, uses_crengine, is_crengine_mod, status, whitelist_visibility, whitelist_play, current_version) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$slug, $title, $description, $genre, $user['id'], $hosting_type, $game_url, $uses_crengine, $is_crengine_mod, $status, $whitelist_visibility, $whitelist_play, $current_version]);
        
        $game_id = $pdo->lastInsertId();
        
        // Handle file uploads
        if ($hosting_type === 'ZIP' && isset($_FILES['game_file'])) {
            $upload_dir = "uploads/games/$game_id/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            move_uploaded_file($_FILES['game_file']['tmp_name'], $upload_dir . 'game.zip');
        }
        
        // Handle thumbnails
        if (isset($_FILES['thumbnail_small'])) {
            $thumb_dir = "uploads/thumbnails/";
            if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);
            $small_path = $thumb_dir . $game_id . '_small.jpg';
            move_uploaded_file($_FILES['thumbnail_small']['tmp_name'], $small_path);
            $pdo->prepare("UPDATE games SET thumbnail_small = ? WHERE id = ?")->execute([$small_path, $game_id]);
        }
        
        if (isset($_FILES['thumbnail_big'])) {
            $thumb_dir = "uploads/thumbnails/";
            if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);
            $big_path = $thumb_dir . $game_id . '_big.jpg';
            move_uploaded_file($_FILES['thumbnail_big']['tmp_name'], $big_path);
            $pdo->prepare("UPDATE games SET thumbnail_big = ? WHERE id = ?")->execute([$big_path, $game_id]);
        }
        
        header('Location: game.php?slug=' . urlencode($slug));
        exit;
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Game - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .upload-form {
            background: #1e2329;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            color: #66c0f4;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px;
            background: #16202d;
            border: 1px solid #3c4043;
            border-radius: 4px;
            color: #c7d5e0;
            font-size: 1rem;
        }
        .form-textarea {
            height: 100px;
            resize: vertical;
        }
        .form-checkbox {
            margin-right: 8px;
        }
        .submit-button {
            background: linear-gradient(90deg, #06bfff 0%, #2d73ff 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }
        .error {
            background: #d32f2f;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .radio-item {
            display: flex;
            align-items: center;
        }
        .fine-print {
            font-size: 0.8rem;
            color: #8f98a0;
            margin-top: 5px;
        }
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .file-input {
            padding: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="game-header">
            <h1 class="game-title">Upload New Game</h1>
            <div class="game-genre">Share your game with the community</div>
            <div style="margin-top: 15px;">
                <a href="dashboard.php" style="color: #66c0f4; text-decoration: none;">← Back to My Games</a>
            </div>
        </div>

        <div class="upload-form">
            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Game</label>
                    <div class="radio-group">
                        <div class="radio-item">
                            <input type="radio" name="hosting_type" value="ZIP" id="zip" checked>
                            <label for="zip">Zip: This game will be hosted on CRZ.Games.</label>
                        </div>
                        <div class="radio-item">
                            <input type="radio" name="hosting_type" value="URL" id="url" disabled>
                            <label for="url">URL: This game will be hosted on the given URL. (WIP due to CORS and other problems)</label>
                        </div>
                    </div>
                    <div class="fine-print">CRZ.Games will use the index.html as the game.</div>
                    <input type="file" name="game_file" class="form-input file-input" accept=".zip">
                    <input type="url" name="game_url" class="form-input" placeholder="Game URL" style="display:none;">
                </div>

                <div class="form-group">
                    <label class="form-label">Start as released?</label>
                    <div class="checkbox-group">
                        <label><input type="radio" name="status" value="PLAYABLE"> <?= $user['id'] == 1 ? 'Playable Immediately' : 'Submit for Approval' ?> (Recommended for games already created)</label>
                        <label><input type="radio" name="status" value="PUBLIC_UNPLAYABLE"> Public but unplayable (Recommended for works in progress)</label>
                        <label><input type="radio" name="status" value="DRAFT" checked> Draft</label>
                        <label><input type="checkbox" name="whitelist_visibility"> Whitelisted visibility</label>
                        <label><input type="checkbox" name="whitelist_play"> Whitelisted play ability</label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">CRENGINE</label>
                    <label><input type="checkbox" name="uses_crengine" id="crengine"> Uses CREngine</label>
                    <div id="crengine-mod" style="display:none;">
                        <label><input type="checkbox" name="is_crengine_mod"> Is this game a CRENGINE mod</label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="title">Title</label>
                    <input type="text" id="title" name="title" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="genre">Game Genre</label>
                    <input type="text" id="genre" name="genre" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-textarea"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="version">Starting Version</label>
                    <input type="text" id="version" name="current_version" class="form-input" value="0.1.0">
                </div>

                <div class="form-group">
                    <label class="form-label" for="thumb_small">Small Thumbnail</label>
                    <input type="file" id="thumb_small" name="thumbnail_small" class="form-input file-input" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label" for="thumb_big">Big Thumbnail</label>
                    <input type="file" id="thumb_big" name="thumbnail_big" class="form-input file-input" accept="image/*">
                </div>

                <button type="submit" class="submit-button">Upload Game</button>
            </form>

            <script>
                document.getElementById('crengine').addEventListener('change', function() {
                    document.getElementById('crengine-mod').style.display = this.checked ? 'block' : 'none';
                });
                
                document.querySelectorAll('input[name="hosting_type"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        const fileInput = document.querySelector('input[name="game_file"]');
                        const urlInput = document.querySelector('input[name="game_url"]');
                        if (this.value === 'ZIP') {
                            fileInput.style.display = 'block';
                            urlInput.style.display = 'none';
                        } else {
                            fileInput.style.display = 'none';
                            urlInput.style.display = 'block';
                        }
                    });
                });
            </script>
        </div>
    </div>
</body>
</html>