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
    if (!($game['status'] === 'PLAYABLE' || $user['id'] === 1 || $user['id'] === $game['owner_user_id']) || $user['id'] === 2) {
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
// if the game is rejected, pending approval, draft, or whitelisted (where you're not on list) then kick out
if (!($game['status'] === 'PLAYABLE' || $game['status'] === 'PUBLIC_UNPLAYABLE' || ($user && ($user['id'] === 1 || $user['id'] === $game['owner_user_id'])))) {
    header('Location: /games/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" style="overflow: hidden;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($game['title']) ?> - CRZ.Games</title>
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
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
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
            box-sizing: border-box;
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
    <button id="overlayToggle" onclick="toggleOverlay()" style="position: fixed; top: 10px; right: 10px; z-index: 999; background: rgba(0,0,0,0.7); color: white; border: 1px solid #555; padding: 5px 10px; border-radius: 3px; font-size: 12px; cursor: pointer;">☰</button>
    
    <div class="overlay" id="overlay">
        <div class="overlay-topbar">
            <button class="overlay-button" onclick="openWindow('gameInfo')">Game Info</button>
            <button class="overlay-button" onclick="openWindow('settings')">Settings</button>
            <button class="overlay-button" onclick="openWindow('help')">Help</button>
            <button class="overlay-button" onclick="window.location.href='/games/game.php?slug=<?= $game['slug'] ?>'">Exit Game</button>
            <button class="overlay-button overlay-close" onclick="closeOverlay()" style="margin-left: auto;">×</button>
        </div>
        
        
        <div class="window" id="settings" style="top: 150px; left: 200px;">
            <div class="window-header">
                <span class="window-title">Settings</span>
                <button class="window-close" onclick="closeWindow('settings')">×</button>
            </div>
            <div class="window-content">
                <h3>Game Settings</h3>
                <p><label><input type="checkbox" id="fullscreen" disabled> Fullscreen Mode</label></p>
                <p><button onclick="document.documentElement.requestFullscreen()" style="background: #2a5298; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Enter Fullscreen</button></p>
                <p><button onclick="document.exitFullscreen()" style="background: #d32f2f; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Exit Fullscreen</button></p>
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
                <p><strong>Shift+Tab:</strong> Toggle overlay (when page has focus)</p>
                <p><strong>☰ Button:</strong> Toggle overlay (always works)</p>
                <p><strong>Drag:</strong> Move windows by their title bar</p>

            </div>
        </div>

        <div class="window" id="gameInfo" style="top: 150px; left: 500px;">
            <div class="window-header">
                <span class="window-title">Game</span>
                <button class="window-close" onclick="closeWindow('gameInfo')">×</button>
            </div>
            <div class="window-content">
                <h3>Game</h3>
                <p><?= htmlspecialchars($game['title']) ?></p>
                <!-- if created with crengine, show that it is, otherwise show "Not created with crengine" -->
                <?php if ($game['uses_crengine']): ?>
                <p>This game was created using CRENGINE.</p>
                <?php else: ?>
                <p>This game was not created using CRENGINE.</p>
                <?php endif; ?>
                <!-- if created with crengine, show the keybinds and concommands/convars -->
                <?php if ($game['uses_crengine']): ?>
                <h3>CRENGINE Keybinds</h3>
                <ul>
                    <li><strong>F1:</strong> Toggle Debug Info</li>
                    <li><strong>`:</strong> Toggle Console</li>
                </ul>
                <h3>CRENGINE Console Tips And Tricks</h3>
                <ul>
                    <li><strong>help:</strong> Show all commands</li>
                    <li><strong>bind &lt;key&gt; &lt;command&gt;:</strong> Bind a command to a key (Use bind key "" to unbind)</li>
                    <li><strong>sv_cheats 1</strong>: Enable cheats (use 0 instead of 1 to disable)</li>
                    <li><strong>bindlist</strong>: Check all your binded keys</li>
                </ul>
                <?php endif; ?>
                <h3>Support</h3>
                <p>Having issues? Contact support or check the game's page for more information.</p>
            </div>
        </div>

        
    </div>
    <script>
        function autoScale() {
            if (!document.getElementById('autoScale').checked) return;
            const iframe = document.querySelector('.game-frame');
            
            // Try to get content dimensions for same-origin
            try {
                const doc = iframe.contentDocument || iframe.contentWindow.document;
                const body = doc.body;
                if (body && body.scrollWidth > 0 && body.scrollHeight > 0) {
                    iframe.style.width = body.scrollWidth + 'px';
                    iframe.style.height = body.scrollHeight + 'px';
                    const scaleX = window.innerWidth / body.scrollWidth;
                    const scaleY = window.innerHeight / body.scrollHeight;
                    const scale = Math.min(scaleX, scaleY);
                    iframe.style.transform = `translate(-50%, -50%) scale(${scale})`;
                    return;
                }
            } catch (e) {}
            
            // External source: use common game dimensions
            const gameWidth = 800;
            const gameHeight = 600;
            iframe.style.width = gameWidth + 'px';
            iframe.style.height = gameHeight + 'px';
            const scaleX = window.innerWidth / gameWidth;
            const scaleY = window.innerHeight / gameHeight;
            const scale = Math.min(scaleX, scaleY);
            iframe.style.transform = `translate(-50%, -50%) scale(${scale})`;
        }
        window.addEventListener('resize', autoScale);
        
        // Global key capture using document focus trick
        document.addEventListener('keydown', function(e) {
            if (e.shiftKey && e.code === 'Tab') {
                e.preventDefault();
                toggleOverlay();
            }
        });
        

        
        // Listen for messages from iframe
        window.addEventListener('message', function(e) {
            if (e.data && e.data.type === 'shiftTab') {
                toggleOverlay();
            }
        });
        
        
        function toggleOverlay() {
            const overlay = document.getElementById('overlay');
            overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
            // Ensure parent window gets focus when overlay opens
            if (overlay.style.display === 'block') {
                window.focus();
            }
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
        
        document.getElementById('fullscreen').addEventListener('change', function() {
            // Don't auto-trigger fullscreen, let user control manually
            this.checked = document.fullscreenElement !== null;
        });
        
        // Update checkbox when fullscreen state changes
        document.addEventListener('fullscreenchange', function() {
            document.getElementById('fullscreen').checked = document.fullscreenElement !== null;
        });
        
        document.getElementById('autoScale').addEventListener('change', function() {
            if (this.checked) {
                autoScale();
                window.addEventListener('resize', autoScale);
            } else {
                document.querySelector('.game-frame').style.transform = 'none';
                window.removeEventListener('resize', autoScale);
            }
        });
    </script>
</body>
</html>