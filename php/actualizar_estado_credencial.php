<?php
// actualizar_estado_credencial.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require_once __DIR__ . "/db.php";

if (!isset($mysqli) || $mysqli->connect_errno) {
    echo json_encode(["ok" => false, "msg" => "Error de conexión a BD"]);
    exit;
}

$mysqli->set_charset("utf8mb4");

// 1) ENTRADA
$uid_tag = isset($_POST['uid_tag']) ? trim($_POST['uid_tag']) : '';
$activo  = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;

if ($uid_tag === '') {
    echo json_encode(["ok" => false, "msg" => "Falta uid_tag"]);
    exit;
}
if ($activo !== 0 && $activo !== 1) {
    echo json_encode(["ok" => false, "msg" => "Valor de activo inválido"]);
    exit;
}

// 2) UPDATE
$sql = "UPDATE nfc_credencial
        SET activo = ?
        WHERE uid_tag = ?
        LIMIT 1";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "ok"  => false,
        "msg" => "Error en prepare: " . $mysqli->error
    ]);
    exit;
}

$stmt->bind_param("is", $activo, $uid_tag);

if (!$stmt->execute()) {
    echo json_encode([
        "ok"  => false,
        "msg" => "Error al ejecutar UPDATE: " . $stmt->error
    ]);
    exit;
}

if ($stmt->affected_rows === 0) {
    // No encontró credencial con ese UID
    echo json_encode([
        "ok"  => false,
        "msg" => "No se encontró credencial con ese UID ($uid_tag)"
    ]);
    exit;
}

echo json_encode([
    "ok"     => true,
    "msg"    => "Estado actualizado correctamente",
    "uid_tag"=> $uid_tag,
    "activo" => $activo
]);
