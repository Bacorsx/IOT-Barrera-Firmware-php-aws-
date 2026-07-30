<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');

set_exception_handler(function($ex){
  http_response_code(500);
  echo json_encode(["ok"=>0,"error"=>"php_exception","err"=>$ex->getMessage()]);
  exit;
});

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
function random_password($len=12){
  $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  $out=''; for($i=0;$i<$len;$i++) $out.=$alphabet[random_int(0, strlen($alphabet)-1)];
  return $out;
}

$usu = strtolower(trim((string)(inparam('usu') ?? '')));
$pin = preg_replace('/\D+/', '', (string)(inparam('pin') ?? ''));  // SOLO dígitos
if ($usu==='' || strlen($pin)!==6) {
  http_response_code(400);
  echo json_encode(["ok"=>0,"error"=>"faltan_parametros","error_desc"=>"Requiere usu (correo) y pin (6 dígitos)"]);
  exit;
}

/* 1) Busca y valida todo en la BD */
$sql = "SELECT IdUsu, pin_expires_at
        FROM usuarios
        WHERE LOWER(usuario)=?
          AND pin_recovery = ?
        LIMIT 1";
$s = $mysqli->prepare($sql);
if(!$s){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"prepare_failed"]); exit; }
$s->bind_param("ss", $usu, $pin);
if(!$s->execute()){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"query_failed"]); exit; }
$r = $s->get_result();
if($r->num_rows===0){
  echo json_encode(["ok"=>0,"error"=>"pin_mismatch","error_desc"=>"PIN incorrecto"]); exit;
}
$row = $r->fetch_assoc();

/* 2) Expiración 30 min (si la usas) */
$expStr = $row['pin_expires_at'] ?? '';
if ($expStr === '' || $expStr === '0000-00-00 00:00:00') {
  echo json_encode(["ok"=>0,"error"=>"pin_expired","error_desc"=>"PIN vencido"]); exit;
}
$now = new DateTime('now');
$exp = new DateTime($expStr);
if ($now > $exp) {
  echo json_encode(["ok"=>0,"error"=>"pin_expired","error_desc"=>"PIN vencido"]); exit;
}

/* 3) Éxito: invalidar PIN, forzar cambio, y emitir clave temporal */
$temp = random_password(10);
$hash = password_hash($temp, PASSWORD_DEFAULT);

$u = $mysqli->prepare("UPDATE usuarios
                       SET pin_recovery=NULL, pin_expires_at=NULL, must_change_pwd=1, clave=?
                       WHERE IdUsu=?");
if(!$u){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"prepare_failed"]); exit; }
$u->bind_param("si", $hash, $row['IdUsu']);
if(!$u->execute()){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"update_failed"]); exit; }

echo json_encode(["ok"=>1, "IdUsu"=>(int)$row['IdUsu'], "temp_password"=>$temp]);
