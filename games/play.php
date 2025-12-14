<?php
require_once '../db.php';
// show errors if something happens
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../user/session.php';
$user = getCurrentUser();
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: /games/');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM games WHERE slug = ?");
    $stmt->execute([$slug]);
    $game = $stmt->fetch();
    
    if (!$game) {
        header('HTTP/1.0 404 Not Found');
        echo "Game not found";
        exit;
    }
    //$game['status'] === 'PLAYABLE' || $user['id'] === 1 || $user['id'] === $game['owner_user_id']
    // if this does not match, error with "No access"
    if (!($game['status'] === 'PLAYABLE' || $user['id'] === 1 || $user['id'] === $game['owner_user_id'])) {
        header('HTTP/1.0 403 Forbidden');
        echo "Game is not playable";
        exit;
    }
    
    // Update play count
    $updateStmt = $pdo->prepare("UPDATE games SET play_count = play_count + 1 WHERE id = ?");
    $updateStmt->execute([$game['id']]);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

if ($game['uses_crengine']) {
    $gameUrl = "/games/crengine.html?game={$game['id']}";
} else {
    $gameUrl = $game['hosting_type'] === 'URL' ? $game['game_url'] : "/games/uploads/games/{$game['slug']}/{$game['entry_file']}";
}
?>
<!DOCTYPE html>
<html lang="en" style="overflow: hidden;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Playing <?= htmlspecialchars($game['title']) ?> - CRZ Games</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #000000ff;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .game-frame {
            width: 100vw;
            height: 100vh;
            border: none;
        }
        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .overlay-topbar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 60px;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            display: flex;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }
        .overlay-button {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            margin-right: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        .overlay-button:hover {
            background: rgba(255,255,255,0.2);
        }
        .overlay-close {
            margin-left: auto;
            background: #d32f2f;
            border: none;
        }
        .overlay-close:hover {
            background: #f44336;
        }
        .window {
            position: absolute;
            background: rgba(30, 30, 30, 0.95);
            border: 1px solid #555;
            border-radius: 8px;
            min-width: 300px;
            min-height: 200px;
            display: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.7);
        }
        .window-header {
            background: linear-gradient(135deg, #333, #444);
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            cursor: move;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #555;
        }
        .window-title {
            font-weight: bold;
            font-size: 14px;
        }
        .window-close {
            background: #d32f2f;
            border: none;
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .window-close:hover {
            background: #f44336;
        }
        .window-content {
            padding: 15px;
            font-size: 14px;
        }
        .window-content h3 {
            margin-top: 0;
            color: #00d4ff;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <h2>Loading <?= htmlspecialchars($game['title']) ?>...</h2>
    </div>
    <iframe src="<?= htmlspecialchars($gameUrl) ?>" class="game-frame" onload="document.getElementById('loading').style.display='none'; autoScale()"></iframe>
    
    <div class="overlay" id="overlay">
        <div class="overlay-topbar">
            <button class="overlay-button" onclick="openWindow('gameInfo')">Game Info</button>
            <button class="overlay-button" onclick="openWindow('settings')">Settings</button>
            <button class="overlay-button" onclick="openWindow('help')">Help</button>
            <button class="overlay-button overlay-close" onclick="closeOverlay()">×</button>
        </div>
        
        <div class="window" id="gameInfo" style="top: 100px; left: 100px;">
            <div class="window-header">
                <span class="window-title">Game Information</span>
                <button class="window-close" onclick="closeWindow('gameInfo')">×</button>
            </div>
            <div class="window-content">
                <h3><?= htmlspecialchars($game['title']) ?></h3>
                <p><strong>Play Count:</strong> <?= number_format($game['play_count']) ?></p>
                <p><strong>Developer:</strong> <?= htmlspecialchars($game['developer'] ?? 'Unknown') ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars($game['status']) ?></p>
                <p><strong>Game ID:</strong> <?= $game['id'] ?></p>
            </div>
        </div>
        
        <div class="window" id="settings" style="top: 150px; left: 200px;">
            <div class="window-header">
                <span class="window-title">Settings</span>
                <button class="window-close" onclick="closeWindow('settings')">×</button>
            </div>
            <div class="window-content">
                <h3>Game Settings</h3>
                <p><label><input type="checkbox" id="fullscreen"> Fullscreen Mode</label></p>
                <p><label><input type="range" id="volume" min="0" max="100" value="50"> Volume: <span id="volumeValue">50</span>%</label></p>
                <p><label><input type="checkbox" id="autoScale" checked> Auto Scale</label></p>
            </div>
        </div>
        
        <div class="window" id="help" style="top: 200px; left: 300px;">
            <div class="window-header">
                <span class="window-title">Help</span>
                <button class="window-close" onclick="closeWindow('help')">×</button>
            </div>
            <div class="window-content">
                <h3>Controls</h3>
                <p><strong>Shift+Tab:</strong> Toggle overlay</p>
                <p><strong>Drag:</strong> Move windows by their title bar</p>
                <p><strong>ESC:</strong> Close overlay</p>
                <h3>Support</h3>
                <p>Having issues? Contact support or check the game's page for more information.</p>
            </div>
        </div>
    </div>
    <script>
        function autoScale() {
            const iframe = document.querySelector('.game-frame');
            const rect = iframe.getBoundingClientRect();
            const scaleX = window.innerWidth / rect.width;
            const scaleY = window.innerHeight / rect.height;
            const scale = Math.min(scaleX, scaleY);
            iframe.style.transform = `scale(${scale})`;
            iframe.style.transformOrigin = 'top left';
        }
        window.addEventListener('resize', autoScale);
        
        // Overlay toggle
        document.addEventListener('keydown', function(e) {
            if (e.shiftKey && e.key === 'Tab') {
                e.preventDefault();
                toggleOverlay();
            }
            if (e.key === 'Escape') {
                closeOverlay();
            }
        });
        
        function toggleOverlay() {
            const overlay = document.getElementById('overlay');
            overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
        }
        
        function closeOverlay() {
            document.getElementById('overlay').style.display = 'none';
        }
        
        function openWindow(windowId) {
            document.getElementById(windowId).style.display = 'block';
        }
        
        function closeWindow(windowId) {
            document.getElementById(windowId).style.display = 'none';
        }
        
        // Make windows draggable
        let draggedWindow = null;
        let dragOffset = { x: 0, y: 0 };
        
        document.addEventListener('mousedown', function(e) {
            if (e.target.classList.contains('window-header') || e.target.parentElement.classList.contains('window-header')) {
                draggedWindow = e.target.closest('.window');
                const rect = draggedWindow.getBoundingClientRect();
                dragOffset.x = e.clientX - rect.left;
                dragOffset.y = e.clientY - rect.top;
                draggedWindow.style.zIndex = 1001;
            }
        });
        
        document.addEventListener('mousemove', function(e) {
            if (draggedWindow) {
                draggedWindow.style.left = (e.clientX - dragOffset.x) + 'px';
                draggedWindow.style.top = (e.clientY - dragOffset.y) + 'px';
            }
        });
        
        document.addEventListener('mouseup', function() {
            if (draggedWindow) {
                draggedWindow.style.zIndex = 'auto';
                draggedWindow = null;
            }
        });
        
        // Settings functionality
        document.getElementById('volume').addEventListener('input', function() {
            document.getElementById('volumeValue').textContent = this.value;
        });
    </script>
</body>
</html>