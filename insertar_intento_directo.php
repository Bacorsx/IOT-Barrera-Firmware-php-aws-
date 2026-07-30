<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Para preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require "db.php"; // Debe definir $con (mysqli)

$uid_tag   = isset($_POST['uid_tag'])   ? trim($_POST['uid_tag'])   : '';
$lector_id = isset($_POST['lector_id']) ? trim($_POST['lector_id']) : 'LECTOR_1';
$disp      = isset($_POST['dispositivo']) ? trim($_POST['dispositivo']) : 'LLAVERO';
// Esperado: $disp = "LLAVERO" o "TARJETA"

if ($uid_tag === '') {
    echo json_encode([
        "ok"        => false,
        "permitido" => false,
        "mensaje"   => "Falta uid_tag"
    ]);
    exit;
}

// 1) Buscar credencial en nfc_credencial (solo llavero/tarjeta)
$sql = "SELECT c.id AS cred_id, c.usuario_id, c.tipo, c.activo,
               u.nombre, u.apellido
        FROM nfc_credencial c
        LEFT JOIN usuarios u ON u.IdUsu = c.usuario_id
        WHERE c.uid_tag = ?
          AND c.tipo IN ('NFC_LLAVERO','NFC_TARJETA')
        LIMIT 1";

$stmt = $con->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "ok"        => false,
        "permitido" => false,
        "mensaje"   => "Error en prepare: " . $con->error
    ]);
    exit;
}

$stmt->bind_param("s", $uid_tag);
$stmt->execute();
$res = $stmt->get_result();

$permitido  = false;
$cred_id    = null;
$usuario_id = null;
$motivo     = "";

if ($row = $res->fetch_assoc()) {
    $cred_id    = (int)$row['cred_id'];
    $usuario_id = $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null;
    $activo     = (int)$row['activo'];

    if ($activo === 1 && $usuario_id !== null) {
        $permitido = true;
        $motivo    = "Credencial válida y activa";
    } else if ($activo === 0) {
        $motivo = "Credencial inactiva";
    } else {
        $motivo = "Credencial sin usuario asociado";
    }
} else {
    $motivo = "UID no registrado";
}

$resultado = $permitido ? "PERMITIDO" : "DENEGADO";

// 2) Insertar intento en nfc_intento_acceso
$fecha = date("Y-m-d H:i:s");

$sql2 = "INSERT INTO nfc_intento_acceso
         (credencial_id, usuario_id, resultado, dispositivo, lector_id, motivo, fecha)
         VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt2 = $con->prepare($sql2);
if (!$stmt2) {
    echo json_encode([
        "ok"        => false,
        "permitido" => $permitido,
        "resultado" => $resultado,
        "mensaje"   => "Error en prepare insert: " . $con->error
    ]);
    exit;
}

// credencial_id y usuario_id pueden ser null
$stmt2->bind_param(
    "iisssss",
    $cred_id,
    $usuario_id,
    $resultado,
    $disp,
    $lector_id,
    $motivo,
    $fecha
);
$stmt2->execute();

// 3) Responder al ESP8266
echo json_encode([
    "ok"           => true,
    "permitido"    => $permitido,
    "resultado"    => $resultado,
    "motivo"       => $motivo,
    "usuario_id"   => $usuario_id,
    "credencial_id"=> $cred_id,
    "fecha"        => $fecha
]);
