<?php
require_once '/user/session.php';
require_once 'games/includes/admin.php';
$current_user = getCurrentUser();
?>
<div class="account-nav">
    <?php if ($current_user): ?>
        <!--Left Links, like Store or Library-->
        <div class="left-links">
            <div class="nav-links">
                <a href="/videos/" class="nav-link">Videos</a>
                <a href="/games/messages.php" class="nav-link">Messages</a>
                <?php if (isAdmin($current_user['id'])): ?>
                    <a href="/games/admin.php" class="nav-link">Admin</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="user-menu">
            <span class="username">Welcome, <?= htmlspecialchars($current_user['display_name'] ?: $current_user['username']) ?></span>
            <div class="nav-links">
                <a href="/games/messages.php" class="nav-link">Messages</a>
                <a href="/games/settings.php" class="nav-link">Settings</a>
                <a href="/user/logout.php" class="nav-link">Logout</a>
            </div>
        </div>
    <?php else: ?>
        <div class="guest-menu">
            <div class="nav-links">
                <a href="/user/login.php" class="nav-link">Login</a>
                <a href="/user/signup.php" class="nav-link">Sign Up</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.account-nav {
    position: fixed;
    top: 20px;
    left: 20px;
    right: 20px;
    z-index: 1000;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.user-menu, .guest-menu {
    display: flex;
    align-items: center;
    gap: 15px;
}

.left-links {
    display: flex;
    align-items: center;
}


.username {
    color: #66c0f4;
    font-weight: bold;
    background: #1e2329;
    padding: 8px 12px;
    border-radius: 4px;
}

.nav-links {
    display: flex;
    gap: 8px;
}

.nav-link {
    background: #1e2329;
    color: #66c0f4;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 0.9rem;
    transition: background 0.2s;
}

.nav-link:hover {
    background: #2a3441;
}
</style>