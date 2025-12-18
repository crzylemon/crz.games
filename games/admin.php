<?php
require_once '../db.php';
require_once '../user/session.php';

$user = getCurrentUser();
if (!$user || $user['id'] != 1) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_id = $_POST['game_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE games SET status = 'PLAYABLE' WHERE id = ?");
        $stmt->execute([$game_id]);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE games SET status = 'DRAFT' WHERE id = ?");
        $stmt->execute([$game_id]);
    }
    
    header('Location: admin.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id WHERE g.status = 'PENDING_APPROVAL' ORDER BY g.created_at ASC");
    $stmt->execute();
    $pending_games = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-header {
            background: #d32f2f;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .pending-games {
            background: #1e2329;
            border-radius: 8px;
            overflow: hidden;
        }
        .game-item {
            padding: 20px;
            border-bottom: 1px solid #3c4043;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .game-item:last-child {
            border-bottom: none;
        }
        .game-details h3 {
            margin: 0 0 5px 0;
            color: #66c0f4;
        }
        .game-meta {
            color: #8f98a0;
            font-size: 0.9rem;
        }
        .admin-actions {
            display: flex;
            gap: 10px;
        }
        .approve-btn {
            background: #4caf50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .reject-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .view-btn {
            background: #2196f3;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <button onclick="window.location.href='index.php'" style="position: fixed; top: 20px; right: 20px; background: #2a5298; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; z-index: 1000;">← Back to Games</button>
    <div class="container">
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <p>Manage game approvals and site administration</p>
        </div>

        <h2>Pending Game Approvals (<?= count($pending_games) ?>)</h2>
        
        <?php if (empty($pending_games)): ?>
            <div class="pending-games">
                <div class="game-item">
                    <p>No games pending approval.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="pending-games">
                <?php foreach ($pending_games as $game): ?>
                    <div class="game-item">
                        <div class="game-details">
                            <h3><?= htmlspecialchars($game['title']) ?></h3>
                            <div class="game-meta">
                                By <?= htmlspecialchars($game['display_name'] ?: $game['username']) ?> • 
                                <?= htmlspecialchars($game['genre']) ?> • 
                                Version <?= htmlspecialchars($game['current_version']) ?> • 
                                Submitted <?= date('M j, Y', strtotime($game['created_at'])) ?>
                            </div>
                            <?php if ($game['description']): ?>
                                <p style="margin: 10px 0 0 0; color: #c7d5e0;"><?= htmlspecialchars(substr($game['description'], 0, 150)) ?><?= strlen($game['description']) > 150 ? '...' : '' ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="admin-actions">
                            <a href="game.php?slug=<?= urlencode($game['slug']) ?>" class="view-btn">View</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                                <button type="submit" name="action" value="approve" class="approve-btn">Approve</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                                <button type="submit" name="action" value="reject" class="reject-btn">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>