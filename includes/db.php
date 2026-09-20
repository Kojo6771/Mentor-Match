<?php
require_once __DIR__ . '/env.php';

$dsn = sprintf(
	'mysql:host=%s;port=%s;dbname=%s',
	required_env('DB_HOST'),
	required_env('DB_PORT'),
	required_env('DB_NAME')
);

$pdo = new PDO($dsn, required_env('DB_USER'), env('DB_PASSWORD', ''));
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

return $pdo;