<?php
require_once '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $title));
    $description = $_POST['description'] ?? '';
    $genre = $_POST['genre'] ?? '';
    $hosting_type = $_POST['hosting_type'] ?? 'ZIP';
    $game_url = $_POST['game_url'] ?? '';
    $uses_crengine = isset($_POST['uses_crengine']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO games (slug, title, description, genre, owner_user_id, hosting_type, game_url, uses_crengine, status) VALUES (?, ?, ?, ?, 1, ?, ?, ?, 'DRAFT')");
        $stmt->execute([$slug, $title, $description, $genre, $hosting_type, $game_url, $uses_crengine]);
        
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
    <title>Upload Game - CRZ Games</title>
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
    </style>
</head>
<body>
    <div class="container">
        <div class="game-header">
            <h1 class="game-title">Upload New Game</h1>
            <div class="game-genre">Share your game with the community</div>
        </div>

        <div class="upload-form">
            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="title">Game Title</label>
                    <input type="text" id="title" name="title" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-textarea"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="genre">Genre</label>
                    <input type="text" id="genre" name="genre" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label" for="hosting_type">Hosting Type</label>
                    <select id="hosting_type" name="hosting_type" class="form-select">
                        <option value="ZIP">ZIP File</option>
                        <option value="URL">External URL</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="game_url">Game URL (for external hosting)</label>
                    <input type="url" id="game_url" name="game_url" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="uses_crengine" class="form-checkbox">
                        Uses CREngine
                    </label>
                </div>

                <button type="submit" class="submit-button">Upload Game</button>
            </form>
        </div>
    </div>
</body>
</html>