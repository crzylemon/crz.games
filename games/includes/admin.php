<?php
// Centralized admin management system
require_once dirname(__DIR__) . '/../user/session.php';

function hasAdminRank($userId = null, $requiredRank = null) {
    if ($userId === null) {
        $user = getCurrentUser();
        $userId = $user['id'] ?? null;
    }
    
    if (!$userId) {
        return false;
    }
    
    global $pdo;
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT rank FROM admin_ranks WHERE user_id = ?");
        $stmt->execute([$userId]);
        $ranks = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ranks)) {
            return false;
        }
        
        if ($requiredRank === null) {
            return !empty($ranks);
        }
        
        $rankHierarchy = ['game_moderator' => 1, 'featured_admin' => 2, 'admin' => 3, 'owner' => 4];
        $userMaxRank = max(array_map(fn($r) => $rankHierarchy[$r] ?? 0, $ranks));
        $requiredLevel = $rankHierarchy[$requiredRank] ?? 0;
        
        return $userMaxRank >= $requiredLevel;
    } catch (PDOException $e) {
        return false;
    }
}

function isAdmin($userId = null) {
    return hasAdminRank($userId, 'admin');
}

function canModerateGames($userId = null) {
    return hasAdminRank($userId, 'game_moderator');
}

function canManageFeatured($userId = null) {
    return hasAdminRank($userId, 'featured_admin');
}

function isOwner($userId = null) {
    return hasAdminRank($userId, 'owner');
}

function requireAdmin() {
    if (!isAdmin()) {
        exit("Access denied. Admin privileges required.");
    }
}

function requireGameModerator() {
    if (!canModerateGames()) {
        exit("Access denied. Game moderation privileges required.");
    }
}

function requireFeaturedAdmin() {
    if (!canManageFeatured()) {
        exit("Access denied. Featured management privileges required.");
    }
}

function requireOwner() {
    if (!isOwner()) {
        exit("Access denied. Owner privileges required.");
    }
}

function getAdminList() {
    global $pdo;
    if (!$pdo) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM admin_ranks");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

function getUserRanks($userId) {
    global $pdo;
    if (!$pdo) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT rank FROM admin_ranks WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

function addAdminRank($userId, $rank, $grantedBy) {
    global $pdo;
    if (!$pdo) {
        return false;
    }
    
    $validRanks = ['game_moderator', 'featured_admin', 'admin', 'owner'];
    if (!in_array($rank, $validRanks)) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO admin_ranks (user_id, rank, granted_by) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $rank, $grantedBy]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function removeAdminRank($userId, $rank) {
    global $pdo;
    if (!$pdo) {
        return false;
    }
    
    // Don't allow removing owner rank from user ID 1
    if ((int)$userId === 1 && $rank === 'owner') {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM admin_ranks WHERE user_id = ? AND rank = ?");
        $stmt->execute([$userId, $rank]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function getAllAdmins() {
    global $pdo;
    if (!$pdo) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT ar.user_id, ar.rank, a.username, a.display_name FROM admin_ranks ar JOIN accounts a ON ar.user_id = a.id ORDER BY ar.user_id, FIELD(ar.rank, 'owner', 'admin', 'featured_admin', 'game_moderator')");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
?>