<?php
require_once __DIR__.'/db.php';

// Modo 1: consulta por IdUsu (sin pedir usuario/clave)
// Modo 2: login normal con usu + pass

$idUsu = intval($_POST['IdUsu'] ?? 0);
$usu   = trim($_POST['usu'] ?? '');
$pass  = trim($_POST['pass'] ?? '');

// ------------------------------
//  MODO 1: traer datos por IdUsu111
// ------------------------------
if ($idUsu > 0) {
    $sql = "SELECT * FROM usuarios WHERE IdUsu = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $idUsu);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$row = $res->fetch_assoc()) {
        echo json_encode(["ok"=>0,"msg"=>"Usuario no existe"]);
        exit;
    }

    echo json_encode([
        "ok" => 1,
        "IdUsu" => (int)$row["IdUsu"],
        "nombre" => $row["nombre"],
        "apellido" => $row["apellido"],
        "rol" => $row["rol"],
        "departamento_id" => (int)$row["departamento_id"]
    ]);
    exit;
}

// ------------------------------
//  MODO 2: login con usuario/clave
// ------------------------------
$sql = "SELECT * FROM usuarios WHERE usuario = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $usu);
$stmt->execute();
$res = $stmt->get_result();

if (!$row = $res->fetch_assoc()) {
    echo json_encode(["ok"=>0,"msg"=>"Usuario no existe"]);
    exit;
}

// ------------------------------
//  Verificar contraseña
// ------------------------------
if (!password_verify($pass, $row["clave"])) {
    echo json_encode(["ok"=>0,"msg"=>"Clave incorrecta"]);
    exit;
}

// ------------------------------
//  Verificar que el usuario esté ACTIVO
//  (estado = 1 → activo, estado = 0 → desactivado)
// ------------------------------
$estado = isset($row["estado"]) ? (int)$row["estado"] : 1;

if ($estado !== 1) {
    echo json_encode([
        "ok" => 0,
        "msg" => "Usuario desactivado. Contacta al administrador."
    ]);
    exit;
}

// ------------------------------
//  LOGIN CORRECTO
// ------------------------------
echo json_encode([
    "ok" => 1,
    "IdUsu" => (int)$row["IdUsu"],
    "nombre" => $row["nombre"],
    "apellido" => $row["apellido"],
    "rol" => $row["rol"],
    "departamento_id" => (int)$row["departamento_id"],
    "estado" => $estado
]);

