<?php require_once 'db.php'; ?>
<!DOCTYPE html>
<html lang="en"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRZ.Network</title>
    <style>

        

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Roboto', 'Arial', sans-serif; background: #aaa; color: #333; }
        
        .header { background: #212121; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #303030; }
        .header .logo { display: flex; align-items: center; }
        .header .logo img { height: 32px; margin-right: 8px; }
        .header .logo h1 { font-size: 20px; font-weight: 400; color: #fff; }
        .header .search { flex: 1; max-width: 640px; margin: 0 40px; display: flex; }
        .header .search input { flex: 1; padding: 12px 16px; background: #121212; border: 1px solid #303030; border-radius: 2px 0 0 2px; color: #fff; font-size: 16px; }
        .header .search button { padding: 12px 20px; background: #303030; border: 1px solid #303030; border-left: none; border-radius: 0 2px 2px 0; color: #fff; cursor: pointer; }
        .header .actions { display: flex; gap: 16px; align-items: center; }
        /*.header .actions a { color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 18px; background: #ff4444; font-weight: 500; }*/
        
        .sidebar { position: fixed; left: 0; top: 73px; width: 240px; height: calc(100vh - 73px); background: linear-gradient(to bottom, white 0%, lightgray 10px, lightgray calc(100% - 10px), gray 100%); padding: 12px 0; overflow-y: auto; border-right: 2px solid #333;}
        .sidebar .section { margin-bottom: 24px; }
        .sidebar .section-title { padding: 8px 24px; font-size: 14px; font-weight: 500; text-transform: uppercase; text-shadow: 0px 1px 0px #0003;}
        .sidebar .menu-item { display: flex; align-items: center; padding: 10px 24px; color: inherit; text-decoration: none; background: linear-gradient(to bottom, white 0%, lightgray 10px, lightgray calc(100% - 10px), gray 100%);text-shadow: 0px 1px 0px #0003;}
        .sidebar .menu-item:hover { background: linear-gradient(to bottom, lightgray 0%, darkgray 10px, darkgray calc(100% - 10px), gray 100%); }
        .sidebar .menu-item.active { background: linear-gradient(to bottom, lightgray 0%, darkgray 10px, darkgray calc(100% - 10px), gray 100%); }
        .sidebar .menu-item span { margin-left: 24px; font-size: 14px; }
        .sidebar .menu-item img { width: 20px; height: 20px; }
        
        .main-content { margin-left: 240px; padding: 24px; }
        .hero {  padding: 60px 40px; border-radius: 5px; margin-bottom: 32px; text-align: center; border: 2px solid #333;box-shadow: 0px 5px 10px #0003;}
        .hero h1 { font-size: 48px; font-weight: 700; margin-bottom: 16px; }
        .thing p { font-size: 18px;  margin-bottom: 32px; text-shadow: 0px 1px 0px #0003;}
        .thingbutton {  color: #333; padding: 12px 24px; border-radius: 5px; text-decoration: none;  display: inline-block; background-image: linear-gradient(to top, gray, lightgray 25%, lightgrey 75%,white);border: 2px solid #333;box-shadow: 0px 5px 10px #0003;}
        .logo-container { position: relative; display: inline-block; cursor: pointer; }
        .logo-container .hover-text { position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.8); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 12px; opacity: 0; transition: opacity 0.3s; pointer-events: none; }
        .logo-container:hover .hover-text { opacity: 1; }
        
        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-bottom: 48px; }
        .service-card { background: #1e1e1e; border-radius: 12px; overflow: hidden; transition: transform 0.2s; }
        .service-card:hover { transform: translateY(-4px); }
        .service-card .thumbnail { height: 180px; background: linear-gradient(to bottom, lightgray 0%, darkgray 10px, darkgray calc(100% - 10px), gray 100%); display: flex; align-items: center; justify-content: center; font-size: 48px; }
        .service-card .content { padding: 16px; }
        .service-card h3 { font-size: 16px; font-weight: 500; margin-bottom: 8px; }
        .service-card p { font-size: 14px; margin-bottom: 16px; }
        .service-card .btn {padding: 8px 16px;text-decoration: none; font-size: 14px; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 48px; }
        .stat-card {  padding: 24px;  text-align: center; position: relative; }
        .stat-card .number { font-size: 32px; font-weight: 700; color: #ff4444; }
        .stat-card .label { font-size: 14px; color: #333; margin-top: 8px; }
        .stat-card .actual-number { font-size: 12px; color: #666; margin-top: 4px; opacity: 0; transition: opacity 0.3s; }
        .stat-card:hover .actual-number { opacity: 1; }
        
        .footer {padding: 48px 0; border-top: 2px solid #333;}
        .footer-content { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .footer h3 { margin-bottom: 16px; }
        .footer p {line-height: 1.6; }
        .footer a { color: #ff4444; text-decoration: none; }

        .thingbutton:hover {
            filter: contrast(1.1);
        }
        .thing {
            background: linear-gradient(to bottom, white 0%, lightgray 10px, lightgray calc(100% - 10px), gray 100%);
            border: 2px solid #333;
            box-shadow: 0px 5px 10px #0003;
            border-radius: 5px;
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .header .search { display: none; }
        }
    
    </style>
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
            <a class="thingbutton" href="/games/">Sign In</a>
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
            <a href="/vid/" class="cta thingbutton">Start Watching</a>
        </section>

        <?php
        require_once 'api.php';
        $stats = api('stats');
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
                <div class="number">24/7</div>
                <div class="label">Community Support</div>
            </div>
        </section>

        <?php
        $reviews = api('reviews');
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
                    <a href="/vid/" class="btn thingbutton">Watch Videos</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer thing" id="about">
        <div class="footer-content">
            <h3>About CRZ Network</h3>
            <p>CRZ Network is a comprehensive entertainment platform combining gaming, video sharing, and community features. Join thousands of users creating and sharing content every day.</p>
            <p>All user content on this website is human-made or human-uploaded. All graphics are made by the site's creator (Crzy) using <a href="https://penguinmod.com/">PenguinMod</a>. We have a strict NO-AI policy for user generated content.</p>
            <p style="margin-top: 20px; opacity: 0.7;">© 2025 CRZ Network. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>