<?php
// apiregusu.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/db.php';

if (!isset($mysqli) || $mysqli->connect_errno) {
    echo json_encode([
        'ok'  => 0,
        'msg' => 'Error de conexión a la base de datos'
    ]);
    exit;
}

$mysqli->set_charset('utf8mb4');

// ==================== 1. LEER ENTRADA ====================

// Obligatorios
$nombre   = trim($_POST['nombre']   ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$usuario  = trim($_POST['usuario']  ?? '');
$claveRaw = trim($_POST['clave']    ?? '');

// Opcionales
$departamentoId = isset($_POST['departamento_id']) && $_POST['departamento_id'] !== ''
    ? (int)$_POST['departamento_id']
    : null;

$rol = strtoupper(trim($_POST['rol'] ?? 'OPERADOR'));  // por defecto OPERADOR

// activo → se guarda en columna "estado"
$estado = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;
$estado = $estado === 0 ? 0 : 1; // solo 0 o 1

// Normalizar rol a un set limitado (evita valores raros)
$rolesPermitidos = ['OPERADOR', 'ADMIN_DEPTO', 'ADMIN_GENERAL'];
if (!in_array($rol, $rolesPermitidos, true)) {
    $rol = 'OPERADOR';
}

// ==================== 2. VALIDACIONES BÁSICAS ====================

if ($nombre === '' || $apellido === '' || $usuario === '' || $claveRaw === '') {
    echo json_encode([
        'ok'  => 0,
        'msg' => 'Faltan datos obligatorios'
    ]);
    exit;
}

if (!filter_var($usuario, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'ok'  => 0,
        'msg' => 'Email inválido'
    ]);
    exit;
}

// ¿Usuario ya existe?
$stmt = $mysqli->prepare('SELECT IdUsu FROM usuarios WHERE usuario = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['ok' => 0, 'msg' => 'Error en prepare (unique)']);
    exit;
}
$stmt->bind_param('s', $usuario);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    echo json_encode([
        'ok'  => 0,
        'msg' => 'El usuario ya existe'
    ]);
    exit;
}
$stmt->close();

// ==================== 3. HASH DE CONTRASEÑA ====================

$hash = password_hash($claveRaw, PASSWORD_BCRYPT, ['cost' => 12]);
if ($hash === false) {
    echo json_encode([
        'ok'  => 0,
        'msg' => 'No se pudo generar el hash de la contraseña'
    ]);
    exit;
}

// ==================== 4. INSERTAR NUEVO USUARIO ====================

$sql = 'INSERT INTO usuarios (nombre, apellido, usuario, clave, estado, departamento_id, rol)
        VALUES (?, ?, ?, ?, ?, ?, ?)';

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'ok'  => 0,
        'msg' => 'Error en prepare (insert)'
    ]);
    exit;
}

// departamento_id puede ser NULL
// tipos: nombre(s), apellido(s), usuario(s), clave(s), estado(i), departamento_id(i), rol(s)
// => "ssssiis"
$stmt->bind_param(
    'ssssiis',
    $nombre,
    $apellido,
    $usuario,
    $hash,
    $estado,
    $departamentoId,
    $rol
);

if (!$stmt->execute()) {
    $msg = 'Error al insertar usuario';
    if ($stmt->errno === 1062) {
        $msg = 'El usuario ya existe (duplicado)';
    }
    $stmt->close();
    echo json_encode([
        'ok'  => 0,
        'msg' => $msg
    ]);
    exit;
}

$idNuevo = (int)$stmt->insert_id;
$stmt->close();

// ==================== 5. RESPUESTA COMPLETA ====================

echo json_encode([
    'ok'             => 1,
    'id'             => $idNuevo,
    'nombre'         => $nombre,
    'apellido'       => $apellido,
    'usuario'        => $usuario,
    'rol'            => $rol,
    'departamento_id'=> $departamentoId,
    'estado'         => $estado
]);
