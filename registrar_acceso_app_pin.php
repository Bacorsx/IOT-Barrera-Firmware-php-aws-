<?php
require __DIR__ . "/db.php"; // usa tu conexión $mysqli
header('Content-Type: application/json; charset=utf-8');

$usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0; // puede venir 0
$uid_tag    = $_POST['uid_tag']    ?? ''; // identificador del celular
$lector_id  = $_POST['lector_id']  ?? ''; // ej: puerta_lab_01
$dispositivo= $_POST['dispositivo']?? 'APP'; // siempre "APP"
$accion     = $_POST['accion']     ?? 'INGRESAR';

if ($uid_tag === '') {
    echo json_encode([
        "ok" => false,
        "registrado" => false,
        "mensaje" => "Faltan parámetros (uid_tag o lector_id)"
    ]);
    exit;
}
if ($lector_id === '') {
    $lector_id = 'APP_PIN_MOBILE';
}

// --- LÓGICA DE PERMISO ---
// Si la app ya conoce el usuario (usuario_id > 0), permitimos
// Si no, denegamos hasta que lo asocien en Act_listar_acceso
if ($usuario_id > 0) {
    $resultado = 'PERMITIDO';
    $motivo = null;
} else {
    $resultado = 'DENEGADO';
    $motivo = 'Usuario no asociado';
}

// Registrar el intento
$stmt = $mysqli->prepare("
    INSERT INTO nfc_intento_acceso
    (credencial_id, usuario_id, resultado, dispositivo, lector_id, motivo, fecha)
    VALUES (NULL, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("issss", $usuario_id, $resultado, $dispositivo, $lector_id, $motivo);
$stmt->execute();

// Si fue PERMITIDO, activar apertura del servo
if ($resultado === 'PERMITIDO') {

    // Registrar comando para el ESP8266
    $stmt2 = $mysqli->prepare("
        INSERT INTO comandos_puerta (lector_id, abrir, created_at)
        VALUES (?, 1, NOW())
    ");
    $stmt2->bind_param("s", $lector_id);
    $stmt2->execute();
}

echo json_encode([
    "ok" => true,
    "registrado" => true,
    "resultado" => $resultado,        // el Act_Sensores lo usa para mostrar "Autorizado / Denegado"
    "mensaje" => $motivo ?: "Ingreso autorizado"
], JSON_UNESCAPED_UNICODE);
