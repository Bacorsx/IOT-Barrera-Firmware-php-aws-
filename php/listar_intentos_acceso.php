<?php
// listar_intentos_acceso.php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php'; // $mysqli

if (!isset($mysqli) || $mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo conectar a la base de datos"
    ]);
    exit;
}

$mysqli->set_charset('utf8mb4');

// =========================
// 1) Parámetro limit
// =========================
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit <= 0) {
    $limit = 200;
}
if ($limit > 1000) {
    $limit = 1000;
}

// =========================
// 2) Consulta
//    - Si existe credencial: usamos c.uid_tag
//    - Si NO existe credencial (DENEGADO): usamos i.motivo como uid_tag
// =========================
$sql = "
    SELECT
        i.id,
        i.credencial_id,
        i.usuario_id,
        i.resultado,
        i.dispositivo,
        i.lector_id,
        i.motivo,
        i.fecha,
        COALESCE(c.uid_tag, i.motivo) AS uid_tag,
        c.tipo AS credencial_tipo,
        u.nombre,
        u.apellido,
        u.departamento_id
    FROM nfc_intento_acceso AS i
    LEFT JOIN nfc_credencial AS c ON c.id = i.credencial_id
    LEFT JOIN usuarios       AS u ON u.IdUsu = i.usuario_id
    ORDER BY i.id DESC
    LIMIT $limit
";

$res = $mysqli->query($sql);

if (!$res) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "Error en consulta SQL",
        "error" => $mysqli->error
    ]);
    exit;
}

$intentos = [];
while ($r = $res->fetch_assoc()) {
    $intentos[] = [
        "id"             => (string)$r["id"],
        "credencial_id"  => isset($r["credencial_id"]) ? (string)$r["credencial_id"] : "",
        "usuario_id"     => isset($r["usuario_id"]) ? (string)$r["usuario_id"] : "",
        "lector_id"      => (string)($r["lector_id"] ?? ""),
        "nombre"         => $r["nombre"] ?? "",
        "apellido"       => $r["apellido"] ?? "",
        "resultado"      => $r["resultado"] ?? "",
        "dispositivo"    => $r["dispositivo"] ?? "",
        "fecha"          => $r["fecha"] ?? "",
        "motivo"         => $r["motivo"] ?? "",
        // 👇 uid_tag de credencial o motivo si fue denegado sin credencial
        "uid_tag"         => $r["uid_tag"] ?? "",
        "credencial_tipo" => $r["credencial_tipo"] ?? "",
        // 👇 Nuevo: departamento del usuario que hizo el intento
        "departamento_id" => isset($r["departamento_id"]) ? (int)$r["departamento_id"] : 0,
    ];
}

echo json_encode([
    "ok"       => true,
    "intentos" => $intentos
], JSON_UNESCAPED_UNICODE);
