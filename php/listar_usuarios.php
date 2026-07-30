<?php
require_once __DIR__ . "/db.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($mysqli) || $mysqli->connect_errno) {
    echo json_encode(["ok" => false, "msg" => "Error de conexión"]);
    exit;
}

$mysqli->set_charset("utf8mb4");

/**
 * Devolvemos:
 *  - id
 *  - nombre
 *  - apellido
 *  - departamento_id
 *
 * Puedes agregar WHERE estado = 1 si quieres solo usuarios activos.
 */
$sql = "SELECT 
            IdUsu       AS id,
            nombre,
            apellido,
            departamento_id
        FROM usuarios
        ORDER BY nombre, apellido";

$res = $mysqli->query($sql);

if (!$res) {
    echo json_encode(["ok" => false, "msg" => "Error en la consulta"]);
    exit;
}

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = [
        "id"              => (int)$row["id"],
        "nombre"          => $row["nombre"],
        "apellido"        => $row["apellido"],
        "departamento_id" => (int)$row["departamento_id"],
    ];
}

echo json_encode(["ok" => true, "usuarios" => $rows], JSON_UNESCAPED_UNICODE);
