<?php
require_once 'db.php';
try {
    $pdo->exec("ALTER TABLE games ADD COLUMN trailer_url VARCHAR(500)");
    echo "Column added successfully";
} catch (PDOException $e) {
    echo "Error or column exists: " . $e->getMessage();
}
?>