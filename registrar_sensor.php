<?php
require "db.php";
header('Content-Type: application/json; charset=utf-8');

$usuario_id = (int)$_POST['usuario_id'];
$uid_tag    = $_POST['uid_tag'] ?? '';
$tipo       = $_POST['tipo'] ?? '';
if (!$usuario_id || !$uid_tag || !$tipo) {
  echo json_encode(["ok"=>false,"msg"=>"Datos incompletos"]); exit;
}

$stmt = $mysqli->prepare("INSERT INTO nfc_credencial (usuario_id, tipo, uid_tag, activo) VALUES (?, ?, ?, 1)");
$stmt->bind_param("iss", $usuario_id, $tipo, $uid_tag);
$stmt->execute();

echo json_encode(["ok"=>true,"msg"=>"Sensor registrado"]);
