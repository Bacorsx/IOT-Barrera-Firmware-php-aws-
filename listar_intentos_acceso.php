<?php
// listar_intentos_acceso.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php'; // $mysqli
if (!isset($mysqli) || $mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"mensaje"=>"No se pudo conectar a la base de datos"]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// --- parámetros ---
$q       = isset($_GET['q'])        ? trim((string)$_GET['q'])        : '';
$limit   = isset($_GET['limit'])    ? max(1, min(500, (int)$_GET['limit'])) : 200;
$offset  = isset($_GET['offset'])   ? max(0, (int)$_GET['offset'])    : 0;
$lector  = isset($_GET['lector_id'])? trim((string)$_GET['lector_id']): ''; // opcional

// --- SQL base ---
$sql = "
SELECT
  ia.id,
  ia.usuario_id,
  ia.lector_id,
  COALESCE(u.nombre,'')   AS nombre,
  COALESCE(u.apellido,'') AS apellido,
  ia.resultado,
  ia.dispositivo,
  DATE_FORMAT(ia.fecha, '%Y-%m-%d %H:%i:%s') AS fecha,
  COALESCE(c.uid_tag,'')  AS uid_tag
FROM nfc_intento_acceso AS ia
LEFT JOIN usuarios       AS u ON u.IdUsu = ia.usuario_id
LEFT JOIN nfc_credencial AS c ON c.id    = ia.credencial_id
";

$w = [];
$types = '';
$params = [];

// filtros opcionales
if ($q !== '') {
    $w[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR ia.fecha LIKE ?)";
    $like = "%{$q}%";
    $params[] = &$like; $types .= 's';
    $params[] = &$like; $types .= 's';
    $params[] = &$like; $types .= 's';
}
if ($lector !== '') {
    $w[] = "ia.lector_id = ?";
    $params[] = &$lector; $types .= 's';
}
if ($w) {
    $sql .= " WHERE " . implode(" AND ", $w);
}
$sql .= " ORDER BY ia.id DESC LIMIT ? OFFSET ?";
$params[] = &$limit;  $types .= 'i';
$params[] = &$offset; $types .= 'i';

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"mensaje"=>"Error preparando consulta"]);
    exit;
}

// bind_param con número variable de args (por referencia)
array_unshift($params, $types);
call_user_func_array([$stmt, 'bind_param'], $params);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["ok"=>false,"mensaje"=>"Error ejecutando consulta"]);
    $stmt->close(); exit;
}

$res = $stmt->get_result();
$intentos = [];
while ($r = $res->fetch_assoc()) {
    $intentos[] = [
        "id"          => (string)$r["id"],
        "usuario_id"  => (string)($r["usuario_id"] ?? ""),
        "lector_id"   => (string)($r["lector_id"] ?? ""),
        "nombre"      => $r["nombre"],
        "apellido"    => $r["apellido"],
        "resultado"   => $r["resultado"],
        "dispositivo" => $r["dispositivo"],
        "fecha"       => $r["fecha"],
        "uid_tag"     => $r["uid_tag"],
    ];
}
$stmt->close();

echo json_encode(["ok"=>true, "intentos"=>$intentos], JSON_UNESCAPED_UNICODE);
