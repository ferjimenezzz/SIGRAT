-- Ejecutar este script en el editor SQL de Supabase
CREATE TABLE IF NOT EXISTS NOTIFICACION (
    not_id SERIAL PRIMARY KEY,
    us_id INT NOT NULL, 
    tipo VARCHAR(50) CHECK (tipo IN ('Prestamo', 'Reserva', 'Sistema')) NOT NULL,
    mensaje TEXT NOT NULL,
    enlace VARCHAR(255), 
    leido BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_notificacion FOREIGN KEY (us_id) REFERENCES USUARIO(us_id) ON DELETE CASCADE
);

-- Opcional: Índice para búsquedas rápidas por usuario
CREATE INDEX idx_notificacion_us_id ON NOTIFICACION(us_id);
