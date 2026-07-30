<?php
// apidepartamentos.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/db.php'; // ajusta la ruta si es necesario

if (!isset($mysqli) || $mysqli->connect_errno) {
    echo json_encode([
        'ok'   => 0,
        'msg'  => 'Error de conexión a la base de datos'
    ]);
    exit;
}

$mysqli->set_charset('utf8mb4');

$sql = "SELECT id, nombre 
        FROM departamentos
        WHERE activo = 1
        ORDER BY nombre ASC";

$res = $mysqli->query($sql);

if (!$res) {
    echo json_encode([
        'ok'  => 0,
        'msg' => 'Error al consultar departamentos'
    ]);
    exit;
}

$departamentos = [];
while ($row = $res->fetch_assoc()) {
    $departamentos[] = [
        'id'     => (int)$row['id'],
        'nombre' => $row['nombre'],
    ];
}

echo json_encode([
    'ok'            => 1,
    'departamentos' => $departamentos
]);
