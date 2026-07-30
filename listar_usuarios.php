<?php
require __DIR__ . "/_db.php";
header('Content-Type: application/json; charset=utf-8');

$stmt = $pdo->query("SELECT IdUsu AS id, nombre, apellido FROM usuarios ORDER BY nombre, apellido");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(["ok"=>true, "usuarios"=>$rows], JSON_UNESCAPED_UNICODE);
