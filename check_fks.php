<?php
$env = parse_ini_file(__DIR__ . '/.env');
$host = $env['DB_HOST'];
$port = $env['DB_PORT'];
$db   = $env['DB_DATABASE'];
$user = $env['DB_USERNAME'];
$pass = $env['PASSWORD'] ?? $env['DB_PASSWORD']; // Use PASSWORD from .env

$dsn = "pgsql:host=$host;port=$port;dbname=$db";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "--- FOREIGN KEYS para 'usuario' ---\n";
$stmt = $pdo->query("
    SELECT
        tc.table_name, 
        kcu.column_name,
        rc.update_rule,
        rc.delete_rule
    FROM information_schema.table_constraints AS tc 
    JOIN information_schema.key_column_usage AS kcu
      ON tc.constraint_name = kcu.constraint_name
      AND tc.table_schema = kcu.table_schema
    JOIN information_schema.referential_constraints AS rc
      ON tc.constraint_name = rc.constraint_name
    WHERE tc.constraint_type = 'FOREIGN KEY'
      AND rc.unique_constraint_name IN (
          SELECT constraint_name 
          FROM information_schema.table_constraints 
          WHERE table_name = 'usuario' AND constraint_type IN ('PRIMARY KEY', 'UNIQUE')
      )
");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- COLUMNAS de 'bitacora' ---\n";
$stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'bitacora'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
