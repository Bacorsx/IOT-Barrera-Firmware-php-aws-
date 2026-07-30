<?php
// SOLO PARA DEBUG: mostrar errores en pantalla
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Respuesta en texto plano para que el ESP la pueda parsear fácil
header("Content-Type: text/plain; charset=utf-8");
header("Access-Control-Allow-Origin: *");

// Cargar la conexión (puede definir $con o $mysqli)
require __DIR__ . "/db.php";

// Detectar cuál variable de conexión existe
$db = null;

if (isset($con) && $con instanceof mysqli) {
    $db = $con;
} elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
}

// Si no hay conexión válida, devolvemos error
if (!$db) {
    echo "0|ERROR_CONEXION|NONE";
    exit;
}

$db->set_charset("utf8mb4");

// ===============================
// BUSCAR ÚLTIMO INTENTO
// ===============================
//
// Solo tomamos:
//   - resultado = 'PERMITIDO' o 'DENEGADO'
//   - el más reciente por id (sin filtro de fecha)
//   El control de "ya lo usé" lo hace el ESP con lastSeenIntentId.
//

$sql = "
    SELECT id, resultado, dispositivo
    FROM nfc_intento_acceso
    WHERE resultado IN ('PERMITIDO', 'DENEGADO')
    ORDER BY id DESC
    LIMIT 1
";

$res = $db->query($sql);

if (!$res) {
    // Mostramos el error SQL para depurar
    echo "0|ERROR_SQL|" . $db->error;
    exit;
}

if ($res->num_rows === 0) {
    // No hay intentos todavía
    echo "0|NONE|NONE";
    exit;
}

$row = $res->fetch_assoc();

// Formato que espera el ESP8266:
//   id|resultado|dispositivo
//   Ej: 107|PERMITIDO|APP
//       108|DENEGADO|TARJETA
$id          = (int)$row["id"];
$resultado   = $row["resultado"];    // PERMITIDO / DENEGADO
$dispositivo = $row["dispositivo"];  // APP / APP_PIN_MOBILE / TARJETA / etc.

echo $id . "|" . $resultado . "|" . $dispositivo;
