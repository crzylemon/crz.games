<?php
require_once 'db.php';

try {
    // Read and execute the schema
    $schema = file_get_contents('database_schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "Database tables created successfully!\n";
    
} catch(PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
?>