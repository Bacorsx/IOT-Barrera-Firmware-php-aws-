<?php
// apiregusu.php: registro de usuarios con hash y validaciones
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'db.php'; // define $mysqli

function in_json_or_post($k) {
  if (isset($_POST[$k])) return $_POST[$k];
  $raw = file_get_contents('php://input');
  if ($raw) { $j = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($j[$k])) return $j[$k];
  }
  return null;
}

$nombre   = trim((string) in_json_or_post('nombre'));
$apellido = trim((string) in_json_or_post('apellido'));
$usuario  = trim((string) in_json_or_post('usuario')); // puede ser email o username
$clave    = (string) in_json_or_post('clave');

if ($nombre === '' || $apellido === '' || $usuario === '' || $clave === '') {
  http_response_code(400);
  echo json_encode(["ok"=>0,"error"=>"parametros_incompletos"]); exit;
}
if (!preg_match('/^[A-Za-zÁÉÍÓÚÑáéíóúñ\s\.\'-]{2,80}$/u', $nombre)) {
  echo json_encode(["ok"=>0,"error"=>"nombre_invalido"]); exit;
}
if (!preg_match('/^[A-Za-zÁÉÍÓÚÑáéíóúñ\s\.\'-]{2,80}$/u', $apellido)) {
  echo json_encode(["ok"=>0,"error"=>"apellido_invalido"]); exit;
}
if (!preg_match('/^[A-Za-z0-9._@-]{3,120}$/', $usuario)) { // email o username simple
  echo json_encode(["ok"=>0,"error"=>"usuario_invalido"]); exit;
}
if (strlen($clave) < 8 || strlen($clave) > 128) {
  echo json_encode(["ok"=>0,"error"=>"clave_invalida"]); exit;
}

// ¿Usuario ya existe?
$chk = $mysqli->prepare("SELECT 1 FROM usuarios WHERE usuario = ? LIMIT 1");
if (!$chk) { http_response_code(500); echo json_encode(["ok"=>0,"error"=>"prepare_chk"]); exit; }
$chk->bind_param("s", $usuario);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) { echo json_encode(["ok"=>0,"error"=>"usuario_ya_existe"]); $chk->close(); exit; }
$chk->close();

// Insertar con hash
$hash = password_hash($clave, PASSWORD_DEFAULT);
$ins = $mysqli->prepare("INSERT INTO usuarios (nombre, apellido, usuario, clave, estado) VALUES (?,?,?,?,1)");
if (!$ins) { http_response_code(500); echo json_encode(["ok"=>0,"error"=>"prepare_ins"]); exit; }
$ins->bind_param("ssss", $nombre, $apellido, $usuario, $hash);

if ($ins->execute()) {
  echo json_encode(["ok"=>1, "id"=>$ins->insert_id]);
} else {
  http_response_code(500);
  echo json_encode(["ok"=>0, "error"=>"insert_failed", "detalle"=>$ins->error]);
}
$ins->close();
$mysqli->close();
?>