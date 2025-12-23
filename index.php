<?php 
require_once 'db.php';
require_once 'user/session.php';
$user = getCurrentUser();
// show errors
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

?>
<!DOCTYPE html>
<html lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRZ.Network</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <header class="header thing">
        <div class="logo">
            <h1>CRZ.Network</h1>
        </div>
        <div class="search">
            <input type="text" placeholder="Search CRZ Network...">
            <button><img src="default.svg" style="width: 16px; height: 16px;"></button>
        </div>
        <div class="actions">
            <?php if ($user): ?>
                <div class="user-dropdown">
                    <button class="user-button thingbutton"><?php echo htmlspecialchars($user['display_name']); ?></button>
                    <div class="dropdown-content">
                        <a href="/user/settings.php">Settings</a>
                        <a href="/user/logout.php">Sign Out</a>
                    </div>
                </div>
            <?php else: ?>
                <a class="thingbutton" href="/user/login.php">Sign In</a>
                <a class="thingbutton" href="/user/signup.php">Sign Up</a>
            <?php endif; ?>
        </div>
    </header>

    <nav class="sidebar">
        <div class="section">
            <a href="/" class="menu-item active">
                <img src="default.svg">
                <span>Home</span>
            </a>
            <a href="/vid/" class="menu-item">
                <img src="videos.svg">
                <span>Videos</span>
            </a>
            <a href="/vid/shows.php" class="menu-item">
                <img src="default.svg">
                <span>Shows</span>
            </a>
        </div>
        
        <div class="section">
            <div class="section-title">Gaming</div>
            <a href="/games/" class="menu-item">
                <img src="games.svg">
                <span>CRZ.Games</span>
            </a>
            <a href="/games/leaderboards.php" class="menu-item">
                <img src="default.svg">
                <span>Leaderboards</span>
            </a>
        </div>
        
        <div class="section">
            <div class="section-title">Community</div>
            <a href="https://discord.gg/KTKGeGrJEC" class="menu-item">
                <img src="discord.svg">
                <span>Discord</span>
            </a>
        </div>
    </nav>

    <main class="main-content">
        <section class="hero thing">
            <div class="logo-container" onclick="document.querySelector('object').data = document.querySelector('object').data;">
                <object data="/animatedlogo.svg" type="image/svg+xml" style="height: 120px; margin-bottom: 24px; pointer-events: none;"></object>
                <div class="hover-text">Click to replay</div>
            </div>
            <p>The ultimate destination for gaming, videos, and community content</p>
            <a href="/games/" class="cta thingbutton">Start Playing</a>
            <a href="/videos/" class="cta thingbutton">Start Watching</a>
        </section>
        <?php
        // get the stats
        // without api
        // with sql
        $stats = [];
        // videos
        $stmt = $pdo_videos->prepare("SELECT COUNT(*) as count FROM videos");
        $stmt->execute();
        $stats['videos'] = $stmt->fetchColumn();
        // users
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM accounts");
        $stmt->execute();
        $stats['users'] = $stmt->fetchColumn();
        // games
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM games");
        $stmt->execute();
        $stats['games'] = $stmt->fetchColumn();
        // community support i guess, just average all the user's (lse - lss) (last session end - last session start)
        $stmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(SECOND, lss, lse)) as avg FROM accounts WHERE lss IS NOT NULL AND lse IS NOT NULL AND lse > lss");
        $stmt->execute();
        $avg_seconds = $stmt->fetchColumn();
        $stats['community_support'] = $avg_seconds ? round($avg_seconds / 60, 1) . 'm' : '0m';
        ?>
        <section class="stats">
            <div class="stat-card thing">
                <div class="number"><?php echo $stats['videos']; ?></div>
                <div class="label">Videos Uploaded</div>
            </div>
            <div class="stat-card thing">
                <div class="number"><?php echo $stats['users']; ?></div>
                <div class="label">Active Users</div>
            </div>
            <div class="stat-card thing">
                <div class="number"><?php echo $stats['games']; ?></div>
                <div class="label">Games Available</div>
            </div>
            <div class="stat-card thing">
                <div class="number"><?php echo $stats['community_support'];?></div>
                <div class="label">Average session time</div>
            </div>
        </section>

        <?php
        $stmt = $pdo->prepare("SELECT * FROM reviews ORDER BY created_at DESC LIMIT 3");
        $stmt->execute();
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <section style="margin: 40px 0; padding: 30px;" class="thing">
            <h2 style="text-align: center; margin-bottom: 30px;">What Our Users Think of Us</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit,300px); gap: 20px;justify-content: center;">
                <?php if (empty($reviews)): ?>
                <div style="padding: 20px;" class="thing">
                    <p>No reviews available at the moment.</p>
                </div>
                <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                <div style="padding: 20px;" class="thing">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <strong><?php echo htmlspecialchars($review['name'] ?? 'Anonymous'); ?></strong>
                        <div>
                            <?php for ($i = 0; $i < ($review['rating'] ?? 5); $i++): ?>
                            <img src="starfilled.svg" style="width: 16px; height: 16px;">
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p><?php echo htmlspecialchars($review['comment'] ?? ''); ?></p>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="/vid/review.php" style="padding: 10px 20px;text-decoration: none;" class="thingbutton">Leave a Review</a>
            </div>
        </section>

        <section class="services-grid">
            <div class="service-card thing">
                <div class="thumbnail"><img src="games.svg" style="width: 128px; height: 128px;"></div>
                <div class="content">
                    <h3>CRZ.Games</h3>
                    <p>Play classic games, compete with friends, and discover new gaming experiences in our interactive gaming platform.</p>
                    <a href="/games/" class="btn thingbutton">Play Now</a>
                </div>
            </div>
            
            <div class="service-card thing">
                <div class="thumbnail"><img src="videos.svg" style="width: 128px; height: 128px;"></div>
                <div class="content">
                    <h3>CRZ.Videos</h3>
                    <p>Upload, share, and discover amazing videos. Create playlists, add captions, and engage with our growing community.</p>
                    <a href="/videos/" class="btn thingbutton">Watch Videos</a>
                </div>
            </div>
        </section>
        <footer class="footer thing" id="about">
            <div class="footer-content">
                <h3>About CRZ Network</h3>
                <p>CRZ Network is a comprehensive entertainment platform combining gaming, video sharing, and community features. Join thousands of users creating and sharing content every day.</p>
                <p>All user content on this website is human-made or human-uploaded. All graphics are made by the site's creator (Crzy) using <a href="https://penguinmod.com/">PenguinMod</a>. We have a strict NO-AI policy for user generated content.</p>
                <p style="margin-top: 20px; opacity: 0.7;">© 2025 CRZ Network. All rights reserved.</p>
            </div>
        </footer>
    </main>
</body>
</html>