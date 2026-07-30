<?php
// db.php — conexión universal (PDO + MySQLi) y CORS básico

// --- CORS / preflight (no rompe páginas que ya envían headers propios) ---
if (php_sapi_name() !== 'cli') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

date_default_timezone_set('America/Santiago');

// --- Credenciales ---
$DB_HOST    = '';       
$DB_NAME    = '';
$DB_USER    = '';    
$DB_PASS    = '';     
$DB_CHARSET = 'utf8mb4';

// --- MySQLi (para scripts existentes que usan mysqli_* o $conn) ---
mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli && !$mysqli->connect_errno) {
    @$mysqli->set_charset($DB_CHARSET);
} else {
    $mysqli = null;
}

// --- PDO (para scripts nuevos o que usen $pdo) ---
try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    $pdo = null;
    // No echo aquí para no romper páginas que ya imprimen salida
    error_log('PDO connect failed: ' . $e->getMessage());
}

// --- Si ambas conexiones fallan, responde JSON 500 coherente ---
if (!$mysqli && !$pdo) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo conectar a la base de datos']);
    exit;
}

// --- Aliases para máxima compatibilidad con scripts existentes ---
$conn     = $mysqli;   // nombre habitual
$conexion = $mysqli;   // otro alias común
$link     = $mysqli;   // otro alias común

// --- Helpers opcionales para quien quiera tipado ---
function db(): PDO {
    global $pdo;
    if (!$pdo) { throw new RuntimeException('Conexión PDO no disponible'); }
    return $pdo;
}
function dbi(): mysqli {
    global $mysqli;
    if (!$mysqli) { throw new RuntimeException('Conexión MySQLi no disponible'); }
    return $mysqli;
}
