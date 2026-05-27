# SIGRAT - Sistema de Gestión de Reservas y Administración Tecnológica
## Descripción General
Este documento detalla el procedimiento técnico para la inicialización y despliegue del proyecto SIGRAT. Su lectura exhaustiva es obligatoria para garantizar la integridad del entorno de desarrollo y producción.
## Requisitos Previos del Sistema
- **Servidor Web:** Apache (XAMPP/MAMP/LAMP recomendado).
- **Lenguaje Base:** PHP 8.0 o superior (con extensión PDO_MySQL habilitada).
- **Base de Datos:** MySQL 5.7+ o MariaDB (Motor InnoDB requerido para integridad relacional).
## Instrucciones de Instalación
### 1. Ubicación del Proyecto
El código fuente debe residir obligatoriamente en el directorio de acceso público del servidor web (`htdocs` para XAMPP).
Ruta base del proyecto esperada:
`[DIRECTORIO_XAMPP]/htdocs/creaciones antigravity/Estadias`
### 2. Inicialización de la Base de Datos
La integridad de los datos requiere la ejecución estricta del script de definición (DDL/DML).
1. Inicie el servicio de MySQL desde el panel de control de XAMPP.
2. Acceda a su cliente de administración de bases de datos preferido (e.g., phpMyAdmin, MySQL Workbench o terminal).
3. Cree una nueva base de datos estructurada con cotejamiento UTF-8:
   ```sql
   CREATE DATABASE sigrat_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. Importe el archivo `sigrat_db.sql` ubicado en la raíz del proyecto hacia la base de datos recién creada:
   ```bash
   mysql -u root -p sigrat_db < sigrat_db.sql
   ```
### 3. Configuración del Entorno (Backend)
Las variables de entorno son críticas para la conexión segura a la base de datos.
1. Navegue al directorio `backend/`.
2. Duplique el archivo `.env.example` y renómbrelo como `.env`.
3. Edite el archivo `.env` para reflejar las credenciales de su entorno de base de datos local:
   ```env
   PORT=3001
   DB_HOST=localhost
   DB_USER=root
   DB_PASSWORD=su_password_seguro
   DB_NAME=sigrat_db
   ```
   *Nota de Seguridad:* Jamás exponga el archivo `.env` en repositorios públicos.
### 4. Ejecución del Sistema
Una vez configurado el motor de base de datos y el archivo de entorno, proceda a verificar el correcto funcionamiento.
1. Inicie el servicio de Apache desde el panel de control de XAMPP.
2. Abra un navegador web moderno (Google Chrome, Mozilla Firefox).
3. Acceda al módulo frontend (interfaz de usuario) mediante la siguiente URI:
   ```http
   http://localhost/creaciones%20antigravity/Estadias/frontend/
   ```
4. Verifique que la pantalla de inicio cargue sin errores de conexión a la base de datos.
## Consideraciones de Seguridad y Mantenimiento
- Asegure la sanitización y validación de parámetros en todo nuevo módulo para prevenir inyección SQL.
- El sistema utiliza abstracción de base de datos mediante PDO. No utilice funciones mysql_* o mysqli_* directas.
- Mantenga los permisos de los directorios en niveles estándar (755 para directorios, 644 para archivos) para mitigar vulnerabilidades de escalado de privilegios.
