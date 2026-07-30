<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once 'db.php';

$IdUsu = isset($_POST['IdUsu']) ? intval($_POST['IdUsu']) : 0;
if ($IdUsu <= 0) {
    http_response_code(400);
    echo json_encode(["ok"=>0, "error"=>"Id inválido"]);
    exit;
}

$sql = "DELETE FROM usuarios WHERE IdUsu = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["ok"=>0, "error"=>"Prepare failed"]);
    exit;
}
$stmt->bind_param("i", $IdUsu);
$ok = $stmt->execute();

if ($ok && $stmt->affected_rows > 0) {
    echo json_encode(["ok"=>1, "IdUsu"=>$IdUsu]);
} else {
    http_response_code(404);
    echo json_encode(["ok"=>0, "error"=>"Registro no existe o no se eliminó"]);
}
$stmt->close();
$mysqli->close();
?>
