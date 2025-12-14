<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: ../user/login.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE owner_user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $user_games = $stmt->fetchAll();

$mapStatusToLabel = [
    'PLAYABLE' => '[HideMe]',
    'PUBLIC_UNPLAYABLE' => 'Non-playable',
    'DRAFT' => 'Draft',
    'WHITELISTED' => 'Whitelisted',
];
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Games - CRZ Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .upload-btn {
            background: linear-gradient(90deg, #06bfff 0%, #2d73ff 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: bold;
        }
        .games-table {
            background: #1e2329;
            border-radius: 8px;
            overflow: hidden;
        }
        .table-header {
            background: #16202d;
            padding: 15px;
            font-weight: bold;
            color: #66c0f4;
            display: grid;
            grid-template-columns: 1fr 120px 120px 100px 120px;
            gap: 15px;
        }
        .game-row {
            padding: 15px;
            border-bottom: 1px solid #3c4043;
            display: grid;
            grid-template-columns: 1fr 120px 120px 100px 120px;
            gap: 15px;
            align-items: center;
        }
        .game-row:last-child {
            border-bottom: none;
        }
        .game-title {
            font-weight: bold;
        }
        .game-genre {
            color: #8f98a0;
            font-size: 0.9rem;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            text-align: center;
        }
        .status-playable {
            background: #4caf50;
            color: white;
        }
        .status-public_unplayable {
            background: #ff9800;
            color: white;
        }
        .status-draft {
            background: #757575;
            color: white;
        }
        .actions {
            display: flex;
            gap: 8px;
        }
        .btn-small {
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
        }
        .btn-edit {
            background: #2196f3;
            color: white;
        }
        .btn-delete {
            background: #f44336;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8f98a0;
        }
        .empty-state h3 {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <div>
                <h1 class="game-title">My Games</h1>
                <div class="game-genre">Manage your uploaded games</div>
            </div>
            <a href="upload.php" class="upload-btn">+ Upload New Game</a>
        </div>

        <?php if (empty($user_games)): ?>
            <div class="games-table">
                <div class="empty-state">
                    <h3>No games yet</h3>
                    <p>Upload your first game to get started!</p>
                    <a href="upload.php" class="upload-btn" style="display: inline-block; margin-top: 20px;">Upload Game</a>
                </div>
            </div>
        <?php else: ?>
            <div class="games-table">
                <div class="table-header">
                    <div>Game</div>
                    <div>Status</div>
                    <div>Version</div>
                    <div>Created</div>
                    <div>Actions</div>
                </div>
                
                <?php foreach ($user_games as $game): ?>
                    <div class="game-row">
                        <div>
                            <div class="game-title"><?= htmlspecialchars($game['title']) ?></div>
                            <?php if ($game['genre']): ?>
                                <div class="game-genre"><?= htmlspecialchars($game['genre']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php $statusLabel = $mapStatusToLabel[$game['status']] ?? $game['status']; ?>
                            <span class="status-badge status-<?= strtolower($game['status']) ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        </div>
                        <div><?= htmlspecialchars($game['current_version']) ?></div>
                        <div><?= date('M j, Y', strtotime($game['created_at'])) ?></div>
                        <div class="actions">
                            <a href="game.php?slug=<?= urlencode($game['slug']) ?>" class="btn-small btn-edit">View</a>
                            <a href="edit.php?id=<?= $game['id'] ?>" class="btn-small btn-edit">Edit</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>