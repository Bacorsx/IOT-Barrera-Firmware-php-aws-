<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

require_once "db.php";

$mysqli->set_charset("utf8mb4");

$sql = "SELECT 
            IdUsu,
            nombre,
            apellido,
            usuario,
            rol,
            departamento_id,
            estado AS activo
        FROM usuarios";

$res = $mysqli->query($sql);

$usuarios = [];

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {

        // Convertir valores a tipos correctos
        $usuarios[] = [
            "IdUsu"          => (int)$row["IdUsu"],
            "nombre"         => $row["nombre"],
            "apellido"       => $row["apellido"],
            "usuario"        => $row["usuario"],
            "rol"            => strtoupper($row["rol"]),
            "departamento_id"=> isset($row["departamento_id"]) 
                                ? (int)$row["departamento_id"] 
                                : 0,
            "activo"         => (int)$row["activo"]
        ];
    }
}

echo json_encode($usuarios, JSON_UNESCAPED_UNICODE);
