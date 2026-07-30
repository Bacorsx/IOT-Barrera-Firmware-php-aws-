<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'db.php'; // asegúrate de que conecta correctamente

// --- FUNCIÓN PARA LEER JSON Y $_POST ---
function get_json_param($key) {
    if (isset($_POST[$key])) return $_POST[$key];
    $raw = file_get_contents("php://input");
    if ($raw) {
        $data = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data[$key])) {
            return $data[$key];
        }
    }
    return null;
}

// --- LECTURA DE PARÁMETROS ---
$idUsu = get_json_param('IdUsu') ?? get_json_param('idusu');
$pin = get_json_param('pin');

// --- VALIDACIONES ---
if (!$idUsu || !$pin) {
    echo json_encode(["ok" => 0, "error" => "faltan_parametros"]);
    exit;
}

if (!preg_match('/^[0-9]{6}$/', $pin)) {
    echo json_encode(["ok" => 0, "error" => "pin_invalido", "error_desc" => "El PIN debe tener 6 dígitos"]);
    exit;
}

// --- ACTUALIZAR O CREAR PIN ---
$stmt = $mysqli->prepare("UPDATE usuarios SET pin_recovery = ? WHERE IdUsu = ?");
$stmt->bind_param("si", $pin, $idUsu);
$ok = $stmt->execute();

if ($ok && $stmt->affected_rows > 0) {
    echo json_encode(["ok" => 1, "msg" => "PIN actualizado correctamente"]);
} else {
    echo json_encode(["ok" => 0, "error" => "no_actualizado"]);
}

?>
