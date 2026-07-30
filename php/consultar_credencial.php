<?php
require_once __DIR__ . "/db.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($mysqli) || $mysqli->connect_errno) {
    echo json_encode(["ok" => false, "msg" => "Error de conexión"]);
    exit;
}

$mysqli->set_charset("utf8mb4");

// ========================
// 1) Validar entrada
// ========================
$uid = trim($_POST['uid_tag'] ?? '');
if ($uid === '') {
    echo json_encode(["ok" => false, "msg" => "Falta uid_tag"]);
    exit;
}

// Opcional: normalizar como lo guardas en BD
$uid = strtoupper($uid);

// ========================
// 2) Buscar credencial por UID
//    - Tomamos la ÚLTIMA (id más alta)
// ========================
$sql = "
    SELECT 
        c.id,
        c.uid_tag,
        c.tipo,
        c.activo,           -- 👈 o 'c.estado' si así se llama la columna
        c.usuario_id,
        u.nombre,
        u.apellido
    FROM nfc_credencial AS c
    LEFT JOIN usuarios AS u ON u.IdUsu = c.usuario_id
    WHERE c.uid_tag = ?
    ORDER BY c.id DESC
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "ok" => false,
        "msg" => "Error en prepare: " . $mysqli->error
    ]);
    exit;
}
$stmt->bind_param("s", $uid);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo json_encode([
        "ok"     => true,
        "existe" => 0
    ]);
    exit;
}

$row = $res->fetch_assoc();

$nombreCompleto = trim(($row["nombre"] ?? "") . " " . ($row["apellido"] ?? ""));

// Si tu columna real se llama 'estado' en lugar de 'activo', usa eso:
$activo = isset($row["activo"]) ? (int)$row["activo"] : 0;
// $activo = isset($row["estado"]) ? (int)$row["estado"] : 0;

echo json_encode([
    "ok"             => true,
    "existe"         => 1,
    "usuario_id"     => (int)$row["usuario_id"],
    "usuario_nombre" => $nombreCompleto,
    "tipo"           => $row["tipo"],
    "activo"         => $activo
], JSON_UNESCAPED_UNICODE);
