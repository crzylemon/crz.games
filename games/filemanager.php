<?php
// SHOW ERRORS (Debug) - Must be first
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Debug: Starting filemanager.php<br>";

session_start();
require_once '../db.php';
require_once 'includes/admin.php';

echo "Debug: About to check admin<br>";
requireAdmin();
echo "Debug: Admin check passed<br>";

$basePath = '/home/crzy/Development/crz.games';
$currentPath = isset($_GET['path']) ? $_GET['path'] : '';
$fullPath = $basePath . '/' . ltrim($currentPath, '/');

// Security check - ensure path is within base directory
if (strpos(realpath($fullPath) ?: $fullPath, $basePath) !== 0) {
    $currentPath = '';
    $fullPath = $basePath;
}

$action = $_GET['action'] ?? '';
$message = '';

// Handle file operations
if ($_POST) {
    if ($action === 'save' && isset($_POST['content'], $_POST['file'])) {
        $filePath = $basePath . '/' . ltrim($_POST['file'], '/');
        if (strpos(realpath(dirname($filePath)) ?: dirname($filePath), $basePath) === 0) {
            file_put_contents($filePath, $_POST['content']);
            $message = 'File saved successfully';
        }
    } elseif ($action === 'create' && isset($_POST['name'], $_POST['type'])) {
        $newPath = $fullPath . '/' . $_POST['name'];
        if ($_POST['type'] === 'file') {
            file_put_contents($newPath, '');
        } else {
            mkdir($newPath, 0755, true);
        }
        $message = ucfirst($_POST['type']) . ' created successfully';
    } elseif ($action === 'delete' && isset($_POST['item'])) {
        $deletePath = $basePath . '/' . ltrim($_POST['item'], '/');
        if (strpos(realpath($deletePath) ?: $deletePath, $basePath) === 0) {
            if (is_dir($deletePath)) {
                rmdir($deletePath);
            } else {
                unlink($deletePath);
            }
            $message = 'Item deleted successfully';
        }
    }
}

// Get directory contents
$items = [];
if (is_dir($fullPath)) {
    $files = scandir($fullPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $itemPath = $fullPath . '/' . $file;
        $items[] = [
            'name' => $file,
            'type' => is_dir($itemPath) ? 'dir' : 'file',
            'size' => is_file($itemPath) ? filesize($itemPath) : 0,
            'modified' => filemtime($itemPath)
        ];
    }
}

// Handle file editing
$editingFile = '';
$fileContent = '';
if ($action === 'edit' && isset($_GET['file'])) {
    $editingFile = $_GET['file'];
    $editPath = $basePath . '/' . ltrim($editingFile, '/');
    if (is_file($editPath) && strpos(realpath($editPath) ?: $editPath, $basePath) === 0) {
        $fileContent = file_get_contents($editPath);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>File Manager - CRZ.Games Admin</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <style>
        .file-manager { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .breadcrumb { background: #333; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .breadcrumb a { color: #00d4ff; text-decoration: none; }
        .file-list { background: #222; border-radius: 4px; overflow: hidden; }
        .file-item { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #333; }
        .file-item:hover { background: #333; }
        .file-icon { width: 20px; margin-right: 10px; }
        .file-name { flex: 1; }
        .file-size { width: 100px; text-align: right; color: #888; }
        .file-actions { width: 150px; text-align: right; }
        .file-actions a { color: #00d4ff; text-decoration: none; margin-left: 10px; }
        .editor { margin-top: 20px; }
        .editor textarea { width: 100%; height: 500px; background: #111; color: white; border: 1px solid #333; padding: 10px; font-family: 'Courier New', monospace; font-size: 14px; line-height: 1.4; }
        .code-editor { position: relative; }
        .code-preview { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; padding: 10px; font-family: 'Courier New', monospace; font-size: 14px; line-height: 1.4; color: transparent; white-space: pre-wrap; overflow: hidden; }
        .toolbar { background: #333; padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .toolbar button { background: #00d4ff; color: white; border: none; padding: 8px 16px; margin-right: 10px; border-radius: 4px; cursor: pointer; }
        .toolbar input { background: #222; color: white; border: 1px solid #333; padding: 8px; margin-right: 10px; border-radius: 4px; }
        .message { background: #4caf50; color: white; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include 'includes/banner.php'; ?>
    <?php include 'includes/account_nav.php'; ?>
    <div class="file-manager" style="margin-top: 80px;">
        <h1>File Manager</h1>
        
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="breadcrumb">
            <a href="?">Home</a>
            <?php
            $pathParts = array_filter(explode('/', $currentPath));
            $buildPath = '';
            foreach ($pathParts as $part) {
                $buildPath .= '/' . $part;
                echo ' / <a href="?path=' . urlencode($buildPath) . '">' . htmlspecialchars($part) . '</a>';
            }
            ?>
        </div>
        
        <div class="toolbar">
            <form style="display: inline;" method="post" action="?action=create&path=<?= urlencode($currentPath) ?>">
                <input type="text" name="name" placeholder="Name" required>
                <select name="type">
                    <option value="file">File</option>
                    <option value="dir">Directory</option>
                </select>
                <button type="submit">Create</button>
            </form>
            <a href="/games/admin.php" style="color: #00d4ff; text-decoration: none; margin-left: 20px;">← Back to Admin</a>
        </div>
        
        <?php if ($editingFile): ?>
            <div class="editor">
                <h2>Editing: <?= htmlspecialchars($editingFile) ?></h2>
                <form method="post" action="?action=save" id="editor-form">
                    <input type="hidden" name="file" value="<?= htmlspecialchars($editingFile) ?>">
                    <textarea name="content" id="code-editor"><?= htmlspecialchars($fileContent) ?></textarea>
                    <br><br>
                    <button type="submit">Save File</button>
                    <a href="?path=<?= urlencode(dirname($editingFile)) ?>" style="color: #00d4ff; text-decoration: none; margin-left: 10px;">Cancel</a>
                </form>
            </div>
        <?php else: ?>
            <div class="file-list">
                <?php if ($currentPath): ?>
                    <div class="file-item">
                        <div class="file-icon">📁</div>
                        <div class="file-name">
                            <a href="?path=<?= urlencode(dirname($currentPath)) ?>" style="color: #00d4ff; text-decoration: none;">..</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php foreach ($items as $item): ?>
                    <div class="file-item">
                        <div class="file-icon"><?= $item['type'] === 'dir' ? '📁' : '📄' ?></div>
                        <div class="file-name">
                            <?php if ($item['type'] === 'dir'): ?>
                                <a href="?path=<?= urlencode($currentPath . '/' . $item['name']) ?>" style="color: white; text-decoration: none;">
                                    <?= htmlspecialchars($item['name']) ?>
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($item['name']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="file-size">
                            <?= $item['type'] === 'file' ? number_format($item['size']) . ' B' : '' ?>
                        </div>
                        <div class="file-actions">
                            <?php if ($item['type'] === 'file'): ?>
                                <a href="?action=edit&file=<?= urlencode($currentPath . '/' . $item['name']) ?>">Edit</a>
                            <?php endif; ?>
                            <form style="display: inline;" method="post" action="?action=delete&path=<?= urlencode($currentPath) ?>" onsubmit="return confirm('Delete this item?')">
                                <input type="hidden" name="item" value="<?= htmlspecialchars($currentPath . '/' . $item['name']) ?>">
                                <button type="submit" style="background: none; border: none; color: #ff4444; cursor: pointer;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('code-editor');
            if (textarea) {
                const ext = '<?= pathinfo($editingFile, PATHINFO_EXTENSION) ?>';
                let mode = 'text/plain';
                if (ext === 'php') mode = 'application/x-httpd-php';
                else if (ext === 'js') mode = 'javascript';
                else if (ext === 'css') mode = 'css';
                else if (ext === 'html') mode = 'htmlmixed';
                else if (ext === 'json') mode = 'application/json';
                
                const editor = CodeMirror.fromTextArea(textarea, {
                    lineNumbers: true,
                    theme: 'monokai',
                    mode: mode,
                    indentUnit: 4,
                    lineWrapping: true
                });
                editor.setSize('100%', '500px');
                
                document.getElementById('editor-form').onsubmit = function() {
                    editor.save();
                };
            }
        });
    </script>
</body>
</html>