<?php
// Centralized admin management system
require_once dirname(__DIR__) . '/../user/session.php';

function isAdmin($userId = null) {
    if ($userId === null) {
        $user = getCurrentUser();
        $userId = $user['id'] ?? null;
    }
    
    if (!$userId) {
        return false;
    }
    
    // Fallback: user ID 1 is always admin
    if ((int)$userId === 1) {
        return true;
    }
    
    // Get admin list from database
    global $pdo;
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'admin_users'");
        $stmt->execute();
        $adminList = $stmt->fetchColumn();
        
        if ($adminList) {
            $adminIds = array_map('intval', explode(',', $adminList));
            return in_array((int)$userId, $adminIds);
        }
    } catch (PDOException $e) {
        // Fallback if database fails
    }
    
    return false;
}

function requireAdmin() {
    if (!isAdmin()) {
        exit("Access denied. Please log in as an admin.");
    }
}

function getAdminList() {
    global $pdo;
    if (!$pdo) {
        return [1];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'admin_users'");
        $stmt->execute();
        $adminList = $stmt->fetchColumn();
        
        if ($adminList) {
            $adminIds = array_map('intval', explode(',', $adminList));
            // Always ensure user ID 1 is in the list
            if (!in_array(1, $adminIds)) {
                $adminIds[] = 1;
            }
            return $adminIds;
        }
    } catch (PDOException $e) {
        // Return default if database fails
    }
    
    return [1]; // Default: user ID 1 is admin
}

function addAdmin($userId) {
    global $pdo;
    if (!$pdo) {
        return false;
    }
    
    $adminIds = getAdminList();
    
    if (!in_array((int)$userId, $adminIds)) {
        $adminIds[] = (int)$userId;
        $adminList = implode(',', $adminIds);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES ('admin_users', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$adminList]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    return false;
}

function removeAdmin($userId) {
    global $pdo;
    if (!$pdo) {
        return false;
    }
    
    // Don't allow removing user ID 1
    if ((int)$userId === 1) {
        return false;
    }
    
    $adminIds = getAdminList();
    $key = array_search((int)$userId, $adminIds);
    if ($key !== false) {
        unset($adminIds[$key]);
        $adminList = implode(',', array_values($adminIds));
        
        try {
            $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'admin_users'");
            $stmt->execute([$adminList]);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    return false;
}
?>