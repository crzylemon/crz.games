<?php
require_once 'db.php';
try {
    // Add featured column
    $pdo->exec("ALTER TABLE games ADD COLUMN featured TINYINT(1) DEFAULT 0");
    echo "Added featured column<br>";
} catch (PDOException $e) {
    echo "Featured column exists or error: " . $e->getMessage() . "<br>";
}

try {
    // Add trailer_url column
    $pdo->exec("ALTER TABLE games ADD COLUMN trailer_url VARCHAR(500)");
    echo "Added trailer_url column<br>";
} catch (PDOException $e) {
    echo "Trailer_url column exists or error: " . $e->getMessage() . "<br>";
}

try {
    // Add screenshots column
    $pdo->exec("ALTER TABLE games ADD COLUMN screenshots TEXT");
    echo "Added screenshots column<br>";
} catch (PDOException $e) {
    echo "Screenshots column exists or error: " . $e->getMessage() . "<br>";
}

echo "Database update complete!";
?>