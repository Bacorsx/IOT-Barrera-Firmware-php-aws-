<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD']==='OPTIONS'){ http_response_code(204); exit; }

require_once 'db.php';

function inparam($k){
  if (isset($_POST[$k])) return $_POST[$k];
  $raw = file_get_contents('php://input');
  if ($raw){
    $j = json_decode($raw,true);
    if (json_last_error()===JSON_ERROR_NONE && isset($j[$k])) return $j[$k];
  }
  return null;
}

// ======================
// 1. ENTRADA
// ======================
$IdUsu    = intval(inparam('IdUsu') ?? 0);
$nombre   = trim((string)(inparam('nombre')   ?? ''));
$apellido = trim((string)(inparam('apellido') ?? ''));
$clave    = (string)(inparam('clave') ?? '');

// NUEVO: rol y activo
$rol      = strtoupper(trim((string)(inparam('rol') ?? '')));

// Android manda "activo" = "1" o "0"
$activoRaw = inparam('activo');
$activo    = null;
if ($activoRaw !== null && $activoRaw !== '') {
  // forzamos a 0 ó 1
  $activo = (intval($activoRaw) === 1) ? 1 : 0;   // 1: activo, 0: desactivado
}

if ($IdUsu <= 0) {
  http_response_code(400);
  echo json_encode(["ok"=>0,"error"=>"param_IdUsu"]);
  exit;
}

// ======================
// 2. ARMAR UPDATE
// ======================
$sets = [];
$params = [];
$types = '';

// nombre / apellido
if ($nombre !== '')   {
  $sets[]   = "nombre=?";
  $params[] = $nombre;
  $types   .= 's';
}
if ($apellido !== '') {
  $sets[]   = "apellido=?";
  $params[] = $apellido;
  $types   .= 's';
}

// NUEVO: rol
if ($rol !== '') {
  // ⚠️ Asegúrate que la columna se llame "rol". Si es "perfil" o "tipo",
  // cambia aquí:
  $sets[]   = "rol=?";
  $params[] = $rol;
  $types   .= 's';
}

// NUEVO: activo -> estado
if ($activo !== null) {
  // ⚠️ Asegúrate que la columna se llame "estado".
  // Si tu tabla tiene "activo" o "estado_usu", cambia aquí:
  $sets[]   = "estado=?";
  $params[] = $activo;
  $types   .= 'i';
}

// clave
if ($clave !== '') {
  $hash = password_hash($clave, PASSWORD_DEFAULT);
  $sets[]   = "clave=?";
  $params[] = $hash;
  $types   .= 's';

  // si cambió la clave, ya no obligamos cambio
  $sets[] = "must_change_pwd=0";
}

if (empty($sets)) {
  http_response_code(400);
  echo json_encode(["ok"=>0,"error"=>"nada_que_actualizar"]);
  exit;
}

$sql = "UPDATE usuarios SET ".implode(", ", $sets)." WHERE IdUsu=?";
$params[] = $IdUsu;
$types   .= 'i';

$stmt = $mysqli->prepare($sql);
if(!$stmt){
  http_response_code(500);
  echo json_encode(["ok"=>0,"error"=>"prepare_failed"]);
  exit;
}

$stmt->bind_param($types, ...$params);
if(!$stmt->execute()){
  http_response_code(500);
  echo json_encode(["ok"=>0,"error"=>"update_failed"]);
  exit;
}

echo json_encode(["ok"=>1, "IdUsu"=>$IdUsu]);
