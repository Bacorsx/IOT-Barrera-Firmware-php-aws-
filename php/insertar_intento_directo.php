<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require __DIR__ . "/db.php"; // aquí debe definirse $mysqli

if (!isset($mysqli) || $mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode([
        "ok"        => false,
        "permitido" => false,
        "mensaje"   => "Error de conexión a BD"
    ]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// 🔹 Datos que envía el ESP
$uid_tag   = isset($_POST['uid_tag'])   ? trim((string)$_POST['uid_tag'])   : '';
$lector_id = isset($_POST['lector_id']) ? trim((string)$_POST['lector_id']) : 'LECTOR_1';

if ($uid_tag === '') {
    echo json_encode([
        "ok"        => false,
        "permitido" => false,
        "mensaje"   => "Falta uid_tag"
    ]);
    exit;
}

// 1) Buscar credencial en nfc_credencial
$sql = "
    SELECT c.id AS cred_id, c.usuario_id, c.tipo, c.activo,
           u.nombre, u.apellido
    FROM nfc_credencial c
    LEFT JOIN usuarios u ON u.IdUsu = c.usuario_id
    WHERE c.uid_tag = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "ok"        => false,
        "permitido" => false,
        "mensaje"   => "Error prepare SELECT: " . $mysqli->error
    ]);
    exit;
}

$stmt->bind_param("s", $uid_tag);
$stmt->execute();
$res = $stmt->get_result();

$permitido   = false;
$cred_id     = null;
$usuario_id  = null;
$motivo      = "";
$dispositivo = "OTRO"; // valor por defecto si no sabemos el tipo

if ($row = $res->fetch_assoc()) {
    $cred_id    = (int)$row['cred_id'];
    $usuario_id = $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null;
    $activo     = (int)$row['activo'];
    $tipo       = (string)$row['tipo'];

    // Mapeo tipo BD -> dispositivo para la tabla nfc_intento_acceso
    if ($tipo === 'NFC_LLAVERO') {
        $dispositivo = 'LLAVERO';
    } elseif ($tipo === 'NFC_TARJETA') {
        $dispositivo = 'TARJETA';
    } elseif ($tipo === 'APP_PIN') {
        $dispositivo = 'APP';
    }

    if ($activo === 1 && $usuario_id !== null) {
        $permitido = true;
        $motivo    = "Credencial válida y activa";
    } else if ($activo === 0) {
        $motivo = "Credencial inactiva";
    } else {
        $motivo = "Credencial sin usuario asociado";
    }
} else {
    // UID no registrado: guardamos el UID en el motivo para que el admin lo vea
    $motivo = "{$uid_tag}";
    $dispositivo = "OTRO";
}
$stmt->close();

$resultado = $permitido ? "PERMITIDO" : "DENEGADO";

// 2) Insertar intento en nfc_intento_acceso
$fecha = date("Y-m-d H:i:s");

$sql2 = "
    INSERT INTO nfc_intento_acceso
    (credencial_id, usuario_id, resultado, dispositivo, lector_id, motivo, fecha)
    VALUES (?, ?, ?, ?, ?, ?, ?)
";
$stmt2 = $mysqli->prepare($sql2);
if (!$stmt2) {
    http_response_code(500);
    echo json_encode([
        "ok"        => false,
        "permitido" => $permitido,
        "resultado" => $resultado,
        "mensaje"   => "Error prepare INSERT: " . $mysqli->error
    ]);
    exit;
}

$stmt2->bind_param(
    "iisssss",
    $cred_id,
    $usuario_id,
    $resultado,
    $dispositivo,
    $lector_id,
    $motivo,
    $fecha
);
$stmt2->execute();
$stmt2->close();

// 3) Respuesta al ESP8266
echo json_encode([
    "ok"            => true,
    "permitido"     => $permitido,
    "resultado"     => $resultado,
    "motivo"        => $motivo,
    "usuario_id"    => $usuario_id,
    "credencial_id" => $cred_id,
    "dispositivo"   => $dispositivo,
    "fecha"         => $fecha
]);
