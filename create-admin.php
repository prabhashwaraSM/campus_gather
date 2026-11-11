<?php
declare(strict_types=1);

// CLI script to create an administrator / faculty staff account
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Run this command from a terminal only.');
}

$config = require __DIR__ . '/config.php';

echo "=== Faculty of IT Event Portal: Admin Setup ===\n";
echo "Full Name: ";
$name = trim(fgets(STDIN));

echo "Student / Staff ID (e.g. STAFF01 or 210001A): ";
$studentId = strtoupper(trim(fgets(STDIN)));

echo "University Email: ";
$email = trim(fgets(STDIN));

echo "Password (min 8 characters): ";
$password = trim(fgets(STDIN));

if ($name === '' || $studentId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    exit("Invalid input. Name, Student/Staff ID, valid email, and password (>= 8 chars) are required.\n");
}

try {
    $db = new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare('
        INSERT INTO users (name, student_id, email, password_hash, department, role)
        VALUES (?, ?, ?, ?, "Faculty of Information Technology", "admin")
        ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = "admin"
    ');
    $stmt->execute([$name, $studentId, $email, $hash]);

    echo "\n✓ Admin account created successfully! You can now sign in at http://localhost/campus-gather/login.php\n";
} catch (PDOException $e) {
    exit("Database error: " . $e->getMessage() . "\n");
}
