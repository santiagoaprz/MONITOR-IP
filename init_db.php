<?php
// init_db.php - versión corregida y compatible
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Mexico_City');

$DB_HOST = '127.0.0.1';
$DB_NAME = 'monitor';
$DB_USER = 'root';
$DB_PASS = ''; // si tu root tiene contraseña, ponla aquí

try {
    // Conectar sin DB para crearla si hace falta
    $pdo = new PDO("mysql:host=" . $DB_HOST, $DB_USER, $DB_PASS, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ));

    // Crear DB y usarla
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . $DB_NAME . "`");

    // Tabla hosts
    $pdo->exec("CREATE TABLE IF NOT EXISTS hosts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        host VARCHAR(255) NOT NULL,
        label VARCHAR(255) DEFAULT NULL,
        type ENUM('ip','web','service') NOT NULL,
        port INT DEFAULT NULL,
        last_status ENUM('up','down') DEFAULT NULL,
        last_change DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla checks
    $pdo->exec("CREATE TABLE IF NOT EXISTS checks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        host_id INT NULL,
        host VARCHAR(255),
        type ENUM('ip','web','service'),
        status ENUM('up','down'),
        response_ms INT DEFAULT NULL,
        checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(host),
        FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "DB y tablas creadas.\n";

    // Hosts iniciales (ajusta si quieres)
    $hosts = array(
        // IPs
        array('10.16.1.3','10.16.1.3','ip',null),
        array('10.16.8.16','10.16.8.16','ip',null),
        array('10.16.1.7','10.16.1.7','ip',null),
        array('10.16.0.5','10.16.0.5','ip',null),
        array('10.16.0.127','10.16.0.127','ip',null),
        array('10.16.0.7','10.16.0.7','ip',null),
        array('172.16.1.1','172.16.1.1','ip',null),
        array('172.16.1.2','172.16.1.2','ip',null),
        array('10.16.8.249','10.16.8.249','ip',null),
        array('10.16.8.2','10.16.8.2','ip',null),
        array('10.16.8.5','10.16.8.5','ip',null),
        array('10.16.8.40','10.16.8.40','ip',null),
        array('10.16.8.4','10.16.8.4','ip',null),
        array('10.16.8.10','10.16.8.10','ip',null),
        array('10.16.8.7','10.16.8.7','ip',null),
        array('10.16.8.20','10.16.8.20','ip',null),
        array('10.16.8.25','10.16.8.25','ip',null),
        array('10.16.8.9','10.16.8.9','ip',null),
        array('10.16.8.61','10.16.8.61','ip',null),
        array('10.16.8.6','10.16.8.6','ip',null),
        array('10.16.8.11','10.16.8.11','ip',null),
        array('10.16.8.254','10.16.8.254','ip',null),
        array('10.16.1.8','10.16.1.8','ip',null),
        array('189.202.180.34','189.202.180.34','ip',null),
        array('189.202.180.40','189.202.180.40','ip',null),

        // Web hosts
        array('sips.tlalpan.gob.mx','sips.tlalpan.gob.mx','web',null),
        array('tlalpan.cdmx.gob.mx','tlalpan.cdmx.gob.mx','web',null),
        array('correo2.cdmx.gob.mx','correo2.cdmx.gob.mx','web',null),
        array('http://10.16.1.3/VU/','VU interno','web',null),

        // Servicios críticos (separados tipo 'service')
        array('10.16.8.16','Critical 10.16.8.16','service',null),
        array('10.16.8.4','Critical 10.16.8.4','service',null),
        array('10.16.8.2','Critical 10.16.8.2','service',null),
        array('10.16.1.3','Critical 10.16.1.3','service',null),
        array('8.8.8.8','Google DNS','service',null)
    );

    $stmt = $pdo->prepare("INSERT INTO hosts (host,label,type,port) VALUES (?,?,?,?)");
    foreach ($hosts as $h) {
        // evitar duplicados
        $chk = $pdo->prepare("SELECT id FROM hosts WHERE host = ?");
        $chk->execute(array($h[0]));
        if ($chk->fetch()) continue;
        $stmt->execute(array($h[0], $h[1], $h[2], $h[3]));
    }

    echo "Hosts iniciales insertados.\n";
    echo "Ejecuta monitor.php manualmente para probar.\n";

} catch (PDOException $e) {
    echo "Error DB: " . $e->getMessage() . "\n";
    exit(1);
}
