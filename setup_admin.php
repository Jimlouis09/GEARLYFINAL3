<?php

require 'config/database.php';

$email = 'admin@gearly.local';
$name = 'GEARLY Administrator';
$password = 'Admin123!';

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users(full_name,email,password,role) VALUES(?,?,?,'admin') ON DUPLICATE KEY UPDATE role='admin'");

$stmt->bind_param('sss', $name, $email, $hash);

$stmt->execute();

echo 'Admin account ready. Email: admin@gearly.local | Password: Admin123! Change this password after first login and delete setup_admin.php.';