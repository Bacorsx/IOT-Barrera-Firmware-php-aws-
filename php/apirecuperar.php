<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'db.php';

function get_param($k){
  if (isset($_POST[$k])) return $_POST[$k];
  $raw = file_get_contents('php://input');
  if ($raw){ $j=json_decode($raw,true); if(json_last_error()===JSON_ERROR_NONE && isset($j[$k])) return $j[$k];}
  return null;
}

$usuario = trim((string)get_param('usu'));
$pin     = trim((string)get_param('pin')); // 6 dígitos

if ($usuario==='' || $pin==='' || !preg_match('/^[A-Za-z0-9._-]{3,60}$/',$usuario) || !preg_match('/^\d{6}$/',$pin)) {
  http_response_code(400); echo json_encode(["ok"=>0,"error"=>"parametros_invalidos"]); exit;
}

$sql="SELECT IdUsu, pin_recovery FROM usuarios WHERE usuario=? LIMIT 1";
$stmt=$mysqli->prepare($sql);
if(!$stmt){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"prepare"]); exit; }
$stmt->bind_param("s",$usuario);
$stmt->execute();
$res=$stmt->get_result();
if($res->num_rows===0){ echo json_encode(["ok"=>0,"error"=>"usuario_no_encontrado"]); exit; }
$row=$res->fetch_assoc();

if ($row['pin_recovery']===null || $row['pin_recovery']!==$pin){
  echo json_encode(["ok"=>0,"error"=>"pin_invalido"]); exit;
}

function random_password($len=12){
  $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*?';
  $max=strlen($alphabet)-1; $out='';
  for($i=0;$i<$len;$i++) $out.=$alphabet[random_int(0,$max)];
  return $out;
}
$temp = random_password(12);
$hash = password_hash($temp, PASSWORD_DEFAULT);

// guardar hash y obligar cambio
$u=$mysqli->prepare("UPDATE usuarios SET clave=?, must_change_pwd=1 WHERE IdUsu=?");
$u->bind_param("si",$hash,$row['IdUsu']);
$ok=$u->execute();

if($ok){
  echo json_encode(["ok"=>1,"temp_password"=>$temp], JSON_UNESCAPED_UNICODE);
}else{
  http_response_code(500); echo json_encode(["ok"=>0,"error"=>"update_failed"]);
}
?>
