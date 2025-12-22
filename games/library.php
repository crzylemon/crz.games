<?php
require_once '../db.php';
require_once '../user/session.php';

$current_user = getCurrentUser();
if (!$current_user) {
    header('Location: ../user/login.php');
    exit;
}

$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? 'all';

// Get user categories
$categories = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM library_categories WHERE user_id = ? ORDER BY name");
    $stmt->execute([$current_user['id']]);
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {}

// Get blocked engines for current user
$blocked_engines = [];
try {
    $stmt = $pdo->prepare("SELECT blocked_engines FROM accounts WHERE id = ?");
    $stmt->execute([$current_user['id']]);
    $result = $stmt->fetchColumn();
    if ($result) {
        $blocked_engines = explode(',', $result);
    }
} catch (PDOException $e) {}

try {
    $sql = "SELECT g.*, a.username, a.display_name FROM games g JOIN accounts a ON g.owner_user_id = a.id JOIN user_library ul ON g.id = ul.game_id WHERE ul.user_id = ?";
    $params = [$current_user['id']];
    
    if ($search) {
        $sql .= " AND (g.title LIKE ? OR g.description LIKE ? OR g.genre LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($engine_filter) {
        $sql .= " AND g.engine = ?";
        $params[] = $engine_filter;
    }
    
    if (!empty($blocked_engines)) {
        $placeholders = str_repeat('?,', count($blocked_engines) - 1) . '?';
        $sql .= " AND g.engine NOT IN ($placeholders)";
        $params = array_merge($params, $blocked_engines);
    }
    
    $sql .= " ORDER BY ul.added_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $games = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library - CRZ.Games</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .library-container {
            display: flex;
            height: calc(100vh - 120px);
            gap: 0;
        }
        .library-sidebar {
            width: 300px;
            background: #1b2838;
            border-right: 1px solid #3c4043;
            overflow-y: auto;
        }
        .library-main {
            flex: 1;
            background: #0e1419;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 15px;
            background: #171a21;
            border-bottom: 1px solid #3c4043;
        }
        .sidebar-search {
            width: 100%;
            padding: 8px;
            background: #316282;
            border: none;
            border-radius: 3px;
            color: white;
            font-size: 0.9rem;
        }
        .sidebar-categories {
            padding: 10px 0;
        }
        .category-header {
            padding: 8px 15px;
            color: #66c0f4;
            font-size: 0.9rem;
            font-weight: bold;
            background: #1e2329;
            border-bottom: 1px solid #2a475e;
        }
        .game-item {
            display: flex;
            align-items: center;
            padding: 6px 25px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .game-item:hover, .game-item.selected {
            background: #2a475e;
        }
        .game-item.selected {
            background: #4c6b22;
        }
        .game-icon {
            width: 24px;
            height: 24px;
            object-fit: cover;
            border-radius: 3px;
            margin-right: 8px;
        }
        .game-info {
            flex: 1;
        }
        .game-name {
            color: #c7d5e0;
            font-size: 0.85rem;
            font-weight: 400;
        }
        .game-engine {
            color: #8f98a0;
            font-size: 0.8rem;
        }
        .game-detail {
            padding: 30px;
            display: none;
        }
        .game-detail.active {
            display: block;
        }
        .detail-header {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }
        .detail-image {
            width: 300px;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        .detail-info {
            flex: 1;
        }
        .detail-title {
            font-size: 2rem;
            font-weight: bold;
            color: #c7d5e0;
            margin-bottom: 10px;
        }
        .detail-meta {
            color: #8f98a0;
            margin-bottom: 15px;
        }
        .detail-description {
            color: #c7d5e0;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .detail-actions {
            display: flex;
            gap: 10px;
        }
        .play-button, .remove-button {
            background: #5c7e10;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 3px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            width: 80px;
            text-align: center;
        }
        .play-button:hover {
            background: #7ba428;
        }
        .remove-button {
            background: #d32f2f;
        }
        .remove-button:hover {
            background: #f44336;
        }
        .context-menu {
            position: fixed;
            background: #2a475e;
            border: 1px solid #3c4043;
            border-radius: 4px;
            padding: 5px 0;
            z-index: 1000;
            display: none;
        }
        .context-menu-item {
            padding: 8px 15px;
            cursor: pointer;
            color: #c7d5e0;
            font-size: 0.9rem;
        }
        .context-menu-item:hover {
            background: #66c0f4;
        }
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: #2a475e;
            padding: 20px;
            border-radius: 8px;
            width: 300px;
        }
        .modal-input {
            width: 100%;
            padding: 8px;
            background: #1b2838;
            border: 1px solid #3c4043;
            border-radius: 4px;
            color: white;
            margin: 10px 0;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .modal-button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .modal-button.primary {
            background: #66c0f4;
            color: white;
        }
        .modal-button.secondary {
            background: #555;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 50px;
            color: #8f98a0;
        }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    
    <div class="library-container">
        <div class="library-sidebar">
            <div class="sidebar-header">
                <input type="text" class="sidebar-search" placeholder="Search library..." id="searchInput">
            </div>
            <div class="sidebar-categories">
                <?php
                // Get favorites
                $favoriteGames = [];
                try {
                    $stmt = $pdo->prepare("SELECT g.* FROM games g JOIN library_favorites f ON g.id = f.game_id JOIN user_library ul ON g.id = ul.game_id WHERE f.user_id = ? AND ul.user_id = ?");
                    $stmt->execute([$current_user['id'], $current_user['id']]);
                    $favoriteGames = $stmt->fetchAll();
                } catch (PDOException $e) {}
                
                // Get uncategorized games (exclude favorites and categorized games)
                $favoriteGameIds = array_column($favoriteGames, 'id');
                $categorizedGameIds = [];
                
                // Get all games that are in custom categories
                try {
                    $stmt = $pdo->prepare("SELECT DISTINCT game_id FROM library_game_categories WHERE user_id = ?");
                    $stmt->execute([$current_user['id']]);
                    $categorizedGameIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (PDOException $e) {}
                
                $uncategorizedGames = [];
                foreach ($games as $game) {
                    if (!in_array($game['id'], $favoriteGameIds) && !in_array($game['id'], $categorizedGameIds)) {
                        $uncategorizedGames[] = $game;
                    }
                }
                ?>
                
                <?php if (!empty($favoriteGames)): ?>
                    <div class="category-header">Favorites</div>
                    <?php foreach ($favoriteGames as $index => $game): ?>
                        <div class="game-item <?= $index === 0 && empty($uncategorizedGames) ? 'selected' : '' ?>" data-game-id="<?= $game['id'] ?>">
                            <?php if ($game['thumbnail_small']): ?>
                                <img src="<?= htmlspecialchars($game['thumbnail_small']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="game-icon">
                            <?php else: ?>
                                <div class="game-icon" style="background: #3c4043;"></div>
                            <?php endif; ?>
                            <div class="game-name"><?= htmlspecialchars($game['title']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($uncategorizedGames)): ?>
                    <div class="category-header">Uncategorized</div>
                    <?php foreach ($uncategorizedGames as $index => $game): ?>
                        <div class="game-item <?= $index === 0 && empty($favoriteGames) ? 'selected' : '' ?>" data-game-id="<?= $game['id'] ?>">
                            <?php if ($game['thumbnail_small']): ?>
                                <img src="<?= htmlspecialchars($game['thumbnail_small']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="game-icon">
                            <?php else: ?>
                                <div class="game-icon" style="background: #3c4043;"></div>
                            <?php endif; ?>
                            <div class="game-name"><?= htmlspecialchars($game['title']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php foreach ($categories as $category): ?>
                    <div class="category-header"><?= htmlspecialchars($category['name']) ?></div>
                    <?php
                    // Get games in this category
                    try {
                        $stmt = $pdo->prepare("SELECT g.* FROM games g JOIN library_game_categories lgc ON g.id = lgc.game_id JOIN user_library ul ON g.id = ul.game_id WHERE lgc.category_id = ? AND lgc.user_id = ? AND ul.user_id = ?");
                        $stmt->execute([$category['id'], $current_user['id'], $current_user['id']]);
                        $categoryGames = $stmt->fetchAll();
                        
                        foreach ($categoryGames as $game):
                    ?>
                        <div class="game-item" data-game-id="<?= $game['id'] ?>">
                            <?php if ($game['thumbnail_small']): ?>
                                <img src="<?= htmlspecialchars($game['thumbnail_small']) ?>" alt="<?= htmlspecialchars($game['title']) ?>" class="game-icon">
                            <?php else: ?>
                                <div class="game-icon" style="background: #3c4043;"></div>
                            <?php endif; ?>
                            <div class="game-name"><?= htmlspecialchars($game['title']) ?></div>
                        </div>
                    <?php
                        endforeach;
                    } catch (PDOException $e) {}
                    ?>
                <?php endforeach; ?>
                
                <div class="category-header" style="cursor: pointer; color: #66c0f4;" onclick="createCategory()">+ Add Category</div>
            </div>
        </div>
        
        <div class="library-main">
            <?php if (empty($games)): ?>
                <div class="empty-state">
                    <h2>Your library is empty</h2>
                    <p>Add games to your library to see them here</p>
                    <a href="index.php" style="color: #66c0f4;">Browse Games</a>
                </div>
            <?php else: ?>
                <?php foreach ($games as $index => $game): ?>
                    <div class="game-detail <?= $index === 0 ? 'active' : '' ?>" id="game-<?= $game['id'] ?>">
                        <?php if ($game['thumbnail_big']): ?>
                            <div style="width: 100%; height: 200px; position: relative; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
                                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: url('<?= htmlspecialchars($game['thumbnail_big']) ?>') center/cover; filter: blur(10px); transform: scale(1.1);"></div>
                                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: url('<?= htmlspecialchars($game['thumbnail_big']) ?>') center/contain no-repeat;"></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-header">
                            <div class="detail-info">
                                <div class="detail-title"><?= htmlspecialchars($game['title']) ?></div>
                                <div class="detail-meta">
                                    <div>Genre: <?= htmlspecialchars($game['genre'] ?? 'Not specified') ?></div>
                                    <div>Developer: <?= htmlspecialchars($game['display_name'] ?? $game['username']) ?></div>
                                </div>
                                <div class="detail-description">
                                    <?= nl2br(htmlspecialchars($game['description'] ?? 'No description available.')) ?>
                                </div>
                                <div class="detail-actions">
                                    <a href="play.php?slug=<?= urlencode($game['slug']) ?>" class="play-button">Play</a>
                                    <form method="POST" action="remove_from_library.php" style="display: inline;">
                                        <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                                        <button type="submit" class="remove-button" onclick="return confirm('Remove from library?')">Remove</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 30px; display: flex; gap: 20px;">
                            <div style="flex: 1;">
                                <h3 style="color: #66c0f4; margin-bottom: 15px;">Reviews</h3>
                                <?php
                                try {
                                    $reviewStmt = $pdo->prepare("SELECT COUNT(*) FROM game_reviews WHERE game_id = ?");
                                    $reviewStmt->execute([$game['id']]);
                                    $reviewCount = $reviewStmt->fetchColumn();
                                    echo "<div style='color: #8f98a0;'>Reviews: $reviewCount</div>";
                                } catch (PDOException $e) {
                                    echo "<div style='color: #8f98a0;'>Reviews: 0</div>";
                                }
                                ?>
                            </div>
                            <div style="flex: 1;">
                                <h3 style="color: #66c0f4; margin-bottom: 15px;">Community</h3>
                                <div style="color: #8f98a0;">
                                    <?php
                                    try {
                                        $guideStmt = $pdo->prepare("SELECT COUNT(*) FROM game_guides WHERE game_id = ?");
                                        $guideStmt->execute([$game['id']]);
                                        $guideCount = $guideStmt->fetchColumn();
                                        
                                        $discussionStmt = $pdo->prepare("SELECT COUNT(*) FROM game_discussions WHERE game_id = ?");
                                        $discussionStmt->execute([$game['id']]);
                                        $discussionCount = $discussionStmt->fetchColumn();
                                        
                                        echo "<div>Guides: $guideCount</div>";
                                        echo "<div>Discussions: $discussionCount</div>";
                                    } catch (PDOException $e) {
                                        echo "<div>Guides: 0</div>";
                                        echo "<div>Discussions: 0</div>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="context-menu" id="contextMenu">
        <div class="context-menu-item" id="favoriteMenuItem" onclick="toggleFavorite(currentGameId)">Add to Favorites</div>
        <div class="context-menu-item" onclick="addToCategory(currentGameId)">Add to Category</div>
    </div>

    <div class="modal" id="categorySelectModal">
        <div class="modal-content">
            <h3 style="color: #c7d5e0; margin-top: 0;">Select Category</h3>
            <div id="categoryList" style="max-height: 200px; overflow-y: auto;"></div>
            <div class="modal-buttons">
                <button class="modal-button secondary" onclick="closeCategorySelectModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div class="modal" id="categoryModal">
        <div class="modal-content">
            <h3 style="color: #c7d5e0; margin-top: 0;">Create Category</h3>
            <input type="text" id="categoryNameInput" class="modal-input" placeholder="Category name">
            <div class="modal-buttons">
                <button class="modal-button secondary" onclick="closeCategoryModal()">Cancel</button>
                <button class="modal-button primary" onclick="submitCategory()">Create</button>
            </div>
        </div>
    </div>

    <script>
        let currentGameId = null;
        
        // Game selection and right-click
        document.querySelectorAll('.game-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.game-item').forEach(i => i.classList.remove('selected'));
                document.querySelectorAll('.game-detail').forEach(d => d.classList.remove('active'));
                
                this.classList.add('selected');
                const gameId = this.dataset.gameId;
                currentGameId = gameId;
                document.getElementById('game-' + gameId).classList.add('active');
            });
            
            item.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                currentGameId = this.dataset.gameId;
                
                // Check if game is in favorites
                const isFavorite = document.querySelector(`[data-game-id="${currentGameId}"]`).closest('.sidebar-categories').querySelector('.category-header').textContent === 'Favorites';
                const favoriteMenuItem = document.getElementById('favoriteMenuItem');
                favoriteMenuItem.textContent = isFavorite ? 'Remove from Favorites' : 'Add to Favorites';
                
                const contextMenu = document.getElementById('contextMenu');
                contextMenu.style.display = 'block';
                contextMenu.style.left = e.pageX + 'px';
                contextMenu.style.top = e.pageY + 'px';
            });
        });
        
        // Hide context menu on click elsewhere
        document.addEventListener('click', function() {
            document.getElementById('contextMenu').style.display = 'none';
        });
        
        // Category management
        // (Removed old category management code)
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const gameItems = document.querySelectorAll('.game-item');
            
            gameItems.forEach(game => {
                const gameName = game.querySelector('.game-name').textContent.toLowerCase();
                const matches = gameName.includes(searchTerm);
                game.style.display = matches ? 'flex' : 'none';
            });
        });
        
        function toggleFavorite(gameId) {
            // Check if currently favorited by checking if it's in favorites section
            const gameElement = document.querySelector(`[data-game-id="${gameId}"]`);
            const favoritesSection = gameElement.closest('.sidebar-categories').querySelector('.category-header');
            const isFavorite = favoritesSection && favoritesSection.textContent === 'Favorites';
            
            const endpoint = isFavorite ? '/endpoints/remove_favorite.php' : '/endpoints/add_favorite.php';
            
            fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({game_id: gameId})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function addToCategory(gameId) {
            fetch('/endpoints/add_category.php', {
                method: 'GET'
            })
            .then(r => r.json())
            .then(data => {
                if (data.categories && data.categories.length > 0) {
                    const categoryList = document.getElementById('categoryList');
                    categoryList.innerHTML = '';
                    
                    data.categories.forEach(category => {
                        const categoryItem = document.createElement('div');
                        categoryItem.style.cssText = 'padding: 8px; cursor: pointer; border-radius: 4px; margin: 2px 0;';
                        categoryItem.textContent = category.name;
                        categoryItem.onclick = () => {
                            fetch('/endpoints/add_game_to_category.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({game_id: gameId, category_id: category.id})
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    location.reload();
                                }
                            });
                            closeCategorySelectModal();
                        };
                        categoryItem.onmouseover = () => categoryItem.style.background = '#66c0f4';
                        categoryItem.onmouseout = () => categoryItem.style.background = 'transparent';
                        categoryList.appendChild(categoryItem);
                    });
                    
                    document.getElementById('categorySelectModal').style.display = 'flex';
                }
            });
        }
        
        function createCategory() {
            document.getElementById('categoryModal').style.display = 'flex';
            document.getElementById('categoryNameInput').value = '';
            document.getElementById('categoryNameInput').focus();
        }
        
        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }
        
        function closeCategorySelectModal() {
            document.getElementById('categorySelectModal').style.display = 'none';
        }
        
        function submitCategory() {
            const name = document.getElementById('categoryNameInput').value.trim();
            if (name) {
                fetch('/endpoints/add_category.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({name: name})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
            closeCategoryModal();
        }
    </script>
</body>
</html>