<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../user/login.php');
    exit;
}

$game_id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE id = ? AND owner_user_id = ?");
    $stmt->execute([$game_id, $user['id']]);
    $game = $stmt->fetch();
    
    if (!$game) {
        header('Location: dashboard.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM games WHERE id = ? AND owner_user_id = ?");
            $stmt->execute([$game_id, $user['id']]);
            
            // Delete game files
            $upload_dir = "uploads/games/$game_id/";
            if (is_dir($upload_dir)) {
                array_map('unlink', glob("$upload_dir*"));
                rmdir($upload_dir);
            }
            
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $error = "Error deleting game: " . $e->getMessage();
        }
    } else {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $genre = $_POST['genre'] ?? '';
        $status = $_POST['status'] ?? 'DRAFT';
        $current_version = $_POST['current_version'] ?? '0.1.0';
        
        // If not admin and trying to set to PLAYABLE, set to PENDING_APPROVAL instead
        if ($user['id'] != 1 && $status === 'PLAYABLE') {
            $status = 'PENDING_APPROVAL_P';
        }
        if ($user['id'] != 1 && $status === 'PUBLIC_UNPLAYABLE') {
            $status = 'PENDING_APPROVAL_PU';
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE games SET title = ?, description = ?, genre = ?, status = ?, current_version = ? WHERE id = ? AND owner_user_id = ?");
            $stmt->execute([$title, $description, $genre, $status, $current_version, $game_id, $user['id']]);
            
            // Handle new game file upload
            if (isset($_FILES['game_file']) && $_FILES['game_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = "uploads/games/$game_id/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                move_uploaded_file($_FILES['game_file']['tmp_name'], $upload_dir . 'game.zip');
            }
            
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?= htmlspecialchars($game['title']) ?> - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .edit-form {
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
        .submit-button {
            background: linear-gradient(90deg, #06bfff 0%, #2d73ff 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            margin-right: 10px;
        }
        .cancel-button {
            background: #757575;
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: bold;
        }
        .error {
            background: #d32f2f;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .delete-button {
            background: #d32f2f;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            margin-left: 10px;
        }
        .file-input {
            padding: 8px;
        }
    </style>
</head>
<body>
    <button onclick="window.location.href='dashboard.php'" style="position: fixed; top: 20px; right: 20px; background: #2a5298; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; z-index: 1000;">← Back to Dashboard</button>
    <div class="container">
        <div class="game-header">
            <h1 class="game-title">Edit Game</h1>
            <div class="game-genre">Update your game details</div>
        </div>

        <div class="edit-form">
            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label" for="title">Title</label>
                    <input type="text" id="title" name="title" class="form-input" value="<?= htmlspecialchars($game['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="genre">Game Genre</label>
                    <input type="text" id="genre" name="genre" class="form-input" value="<?= htmlspecialchars($game['genre']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-textarea"><?= htmlspecialchars($game['description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="version">Version</label>
                    <input type="text" id="version" name="current_version" class="form-input" value="<?= htmlspecialchars($game['current_version']) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="game_file">Upload New Version (Optional)</label>
                    <input type="file" id="game_file" name="game_file" class="form-input file-input" accept=".zip">
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="DRAFT" <?= $game['status'] === 'DRAFT' ? 'selected' : '' ?>>Draft</option>
                        <option value="PUBLIC_UNPLAYABLE" <?= $game['status'] === 'PUBLIC_UNPLAYABLE' ? 'selected' : '' ?>>Public but Unplayable</option>
                        <option value="PLAYABLE" <?= $game['status'] === 'PLAYABLE' ? 'selected' : '' ?>><?= $user['id'] == 1 ? 'Playable' : 'Submit for Approval' ?></option>
                    </select>
                </div>

                <button type="submit" class="submit-button">Update Game</button>
                <a href="dashboard.php" class="cancel-button">Cancel</a>
                <button type="submit" name="delete" class="delete-button" onclick="return confirm('Are you sure you want to delete this game? This cannot be undone.')">Delete Game</button>
            </form>
        </div>
    </div>
</body>
</html>