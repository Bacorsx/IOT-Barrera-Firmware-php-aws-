<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');   // permite llamadas desde Android

require_once 'db.php';

$sql = "SELECT IdUsu, nombre, apellido, usuario FROM usuarios";
$result = $mysqli->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["ok"=>0, "error"=>"Error en la consulta: ".$mysqli->error]);
    exit;
}

$usuarios = [];
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

echo json_encode($usuarios, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$result->free();
$mysqli->close();
?>

