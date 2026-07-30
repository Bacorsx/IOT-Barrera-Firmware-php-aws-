<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(function($ex){
  http_response_code(500);
  echo json_encode(["ok"=>0,"error"=>"php_exception","err"=>$ex->getMessage()]);
  exit;
});

require_once 'db.php';

// Autoload PHPMailer
$autoload = __DIR__.'/vendor/autoload.php';
if (!file_exists($autoload)) {
  echo json_encode(["ok"=>0,"error"=>"missing_autoload","hint"=>"composer require phpmailer/phpmailer"]);
  exit;
}
require_once $autoload;

// Config SMTP (idempotente)
if (!defined('SMTP_HOST')) require_once 'config_mail.php';

use PHPMailer\PHPMailer\PHPMailer;

function inparam($k){
  if (isset($_POST[$k])) return $_POST[$k];
  $raw = file_get_contents('php://input');
  if ($raw) { $j = json_decode($raw,true); if (json_last_error()===JSON_ERROR_NONE && isset($j[$k])) return $j[$k]; }
  return null;
}
function pin6(){
  return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

$usu = trim((string)(inparam('usu') ?? ''));
if ($usu === '' || strpos($usu,'@') === false) {
  http_response_code(400);
  echo json_encode(["ok"=>0,"error"=>"bad_request","error_desc"=>"Debes enviar un correo válido en 'usu'"]);
  exit;
}

// Buscar usuario por correo (en tu esquema: usuario = email)
$stmt = $mysqli->prepare("SELECT IdUsu, usuario FROM usuarios WHERE usuario=? LIMIT 1");
if(!$stmt){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"prepare_failed"]); exit; }
$stmt->bind_param("s", $usu);
if(!$stmt->execute()){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"query_failed"]); exit; }
$res = $stmt->get_result();
if($res->num_rows===0){
  // Éxito genérico (no revelar existencia)
  echo json_encode(["ok"=>1,"notice"=>"email_instructions_if_configured"]);
  exit;
}
$row = $res->fetch_assoc();

// Generar y guardar PIN con expiración (10 min)
$pin  = pin6();
$exp  = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

$u = $mysqli->prepare("UPDATE usuarios SET pin_recovery=?, pin_expires_at=? WHERE IdUsu=?");
if(!$u){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"prepare_failed"]); exit; }
$u->bind_param("ssi", $pin, $exp, $row['IdUsu']);
if(!$u->execute()){ http_response_code(500); echo json_encode(["ok"=>0,"error"=>"update_failed"]); exit; }

// Enviar correo con el PIN
try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = SMTP_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = SMTP_USER;           // ej: iecflores2016@gmail.com
  $mail->Password   = SMTP_PASS;           // App Password (16)
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = SMTP_PORT;           // 587
  $mail->SMTPAutoTLS= true;
  $mail->CharSet    = 'UTF-8';

  // From fijo (usa la misma cuenta del SMTP para Gmail)
  $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
  $mail->addAddress($row['usuario']);      // destino

  $pinEsc = htmlspecialchars($pin);
  $mail->isHTML(true);
  $mail->Subject = 'Tu PIN de recuperación';
  $mail->Body    = "<p>Usa este PIN para recuperar tu cuenta:</p><h2 style='letter-spacing:2px'>{$pinEsc}</h2><p>Vence en 10 minutos.</p>";
  $mail->AltBody = "PIN: {$pin}\nVence en 10 minutos.";

  $mail->send();
  echo json_encode(["ok"=>1,"notice"=>"email_sent"]);
  exit;

} catch (\Throwable $e) {
  echo json_encode(["ok"=>0,"error"=>"smtp_fail","error_desc"=>$e->getMessage()]);
  exit;
}
