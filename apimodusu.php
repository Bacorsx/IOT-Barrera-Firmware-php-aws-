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
  if ($raw){ $j=json_decode($raw,true); if (json_last_error()===JSON_ERROR_NONE && isset($j[$k])) return $j[$k]; }
  return null;
}

$IdUsu    = intval(inparam('IdUsu') ?? 0);
$nombre   = trim((string)(inparam('nombre')   ?? ''));
$apellido = trim((string)(inparam('apellido') ?? ''));
$clave    = (string)(inparam('clave') ?? '');

if ($IdUsu <= 0) { http_response_code(400); echo json_encode(["ok"=>0,"error"=>"param_IdUsu"]); exit; }

$sets = [];
$params = [];
$types = '';

if ($nombre !== '')   { $sets[] = "nombre=?";   $params[]=$nombre;   $types.='s'; }
if ($apellido !== '') { $sets[] = "apellido=?"; $params[]=$apellido; $types.='s'; }
if ($clave !== '') {
  $hash = password_hash($clave, PASSWORD_DEFAULT);
  $sets[] = "clave=?"; $params[]=$hash; $types.='s';
  // si cambió la clave, ya no obligamos cambio
  $sets[] = "must_change_pwd=0";
}

if (empty($sets)) { http_response_code(400); echo json_encode(["ok"=>0,"error"=>"nada_que_actualizar"]); exit; }

$sql = "UPDATE usuarios SET ".implode(", ", $sets)." WHERE IdUsu=?";
$params[] = $IdUsu; $types.='i';

$stmt = $mysqli->prepare($sql);
if(!$stmt){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"prepare_failed"]); exit; }
$stmt->bind_param($types, ...$params);
if(!$stmt->execute()){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"update_failed"]); exit; }

echo json_encode(["ok"=>1, "IdUsu"=>$IdUsu]);
