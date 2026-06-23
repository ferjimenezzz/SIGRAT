<?php
require_once __DIR__ . '/backend/config/Database.php';

try {
    $db = Config\Database::getConnection();
    
    $db->exec("DO $$
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'genero_enum') THEN
            CREATE TYPE genero_enum AS ENUM ('Masculino', 'Femenino');
        END IF;
    END
    $$;");

    $res = $db->query("SELECT column_name FROM information_schema.columns WHERE table_name='usuario' AND column_name='genero'");
    if ($res->rowCount() == 0) {
        $db->exec("ALTER TABLE usuario ADD COLUMN genero genero_enum NOT NULL DEFAULT 'Masculino'");
        echo "Columna agregada con éxito.\n";
    } else {
        echo "La columna ya existe.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
