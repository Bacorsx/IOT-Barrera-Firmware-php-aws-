<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'db.php';

function inparam($k){
  if (isset($_POST[$k])) return $_POST[$k];
  $raw = file_get_contents('php://input');
  if ($raw){ $j=json_decode($raw,true); if (json_last_error()===JSON_ERROR_NONE && isset($j[$k])) return $j[$k]; }
  return null;
}

$usu  = strtolower(trim((string)(inparam('usu')  ?? '')));
$pass = (string)(inparam('pass') ?? '');

if ($usu==='' || $pass==='') {
  http_response_code(400);
  echo json_encode(["ok"=>0,"error"=>"faltan_parametros"]); exit;
}

$sql = "SELECT IdUsu, nombre, apellido, usuario, clave, estado, must_change_pwd
        FROM usuarios
        WHERE LOWER(usuario)=?
        LIMIT 1";
$stmt = $mysqli->prepare($sql);
if(!$stmt){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"prepare_failed"]); exit; }
$stmt->bind_param("s",$usu);
if(!$stmt->execute()){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"query_failed"]); exit; }
$res = $stmt->get_result();
if($res->num_rows===0){ echo json_encode(["ok"=>0,"error"=>"bad_credentials"]); exit; }

$row = $res->fetch_assoc();
if ((int)$row['estado'] !== 1) { echo json_encode(["ok"=>0,"error"=>"usuario_inactivo"]); exit; }

$hash = (string)$row['clave'];              // debe verse como $2y$...
if (!password_verify($pass, $hash)) {       // 👈 compara contra bcrypt
  echo json_encode(["ok"=>0,"error"=>"bad_credentials"]); exit;
}
// Re-hash si el costo por defecto cambió
if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
  $new = password_hash($pass, PASSWORD_DEFAULT);
  if ($u = $mysqli->prepare("UPDATE usuarios SET clave=? WHERE IdUsu=?")) {
    $u->bind_param("si", $new, $row['IdUsu']); $u->execute();
  }
}

echo json_encode([
  "ok" => 1,
  "IdUsu" => (int)$row['IdUsu'],
  "nombre" => $row['nombre'],
  "apellido" => $row['apellido'],
  "usuario" => $row['usuario'],
  "must_change_pwd" => (int)$row['must_change_pwd']
]);
