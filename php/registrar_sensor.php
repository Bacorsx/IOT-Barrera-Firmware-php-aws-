<?php
// registrar_sensor.php
declare(strict_types=1);

require __DIR__ . "/db.php";

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

if (!isset($mysqli) || $mysqli->connect_errno) {
    echo json_encode(["ok" => false, "msg" => "Error de conexión a BD"]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// ---------------------------
// 1. Datos recibidos
// ---------------------------
$usuario_id  = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;   // dueño
$uid_tag     = trim($_POST['uid_tag'] ?? '');
$tipo        = trim($_POST['tipo'] ?? '');

// estado desde el switch
$activo      = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;  // 0 o 1

// usuario logueado (actor)
$actor_id    = isset($_POST['actor_id']) ? (int)$_POST['actor_id'] : 0;
$actor_rol   = $_POST['actor_rol'] ?? '';
$actor_depto = isset($_POST['actor_depto']) ? (int)$_POST['actor_depto'] : 0;

if ($usuario_id <= 0 || $uid_tag === '' || $tipo === '') {
    echo json_encode(["ok" => false, "msg" => "Datos incompletos"]);
    exit;
}

// ---------------------------
// 2. Departamento del usuario dueño
// ---------------------------
$sql = "SELECT departamento_id FROM usuarios WHERE IdUsu = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(["ok" => false, "msg" => "Error prepare SELECT"]);
    exit;
}
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->bind_result($usuario_depto);
$stmt->fetch();
$stmt->close();

$usuario_depto = (int)($usuario_depto ?? 0);

// ---------------------------
// 3. Reglas de permisos
// ---------------------------
if ($actor_rol === 'ADMIN_GENERAL') {
    // permitido
} elseif ($actor_rol === 'ADMIN_DEPTO') {
    if ($actor_depto === 0 || $actor_depto !== $usuario_depto) {
        echo json_encode([
            "ok"  => false,
            "msg" => "Solo puedes gestionar sensores de tu departamento"
        ]);
        exit;
    }
} else {
    echo json_encode([
        "ok"  => false,
        "msg" => "No tienes permisos para registrar sensores"
    ]);
    exit;
}

// ---------------------------
// 4. Insertar o actualizar credencial con el estado correcto
// ---------------------------

// ¿Ya existe credencial con ese UID?
$sqlSel = "SELECT id FROM nfc_credencial WHERE uid_tag = ? LIMIT 1";
$stmtSel = $mysqli->prepare($sqlSel);
if (!$stmtSel) {
    echo json_encode(["ok" => false, "msg" => "Error prepare SELECT credencial"]);
    exit;
}
$stmtSel->bind_param("s", $uid_tag);
$stmtSel->execute();
$stmtSel->bind_result($credId);
$existe = $stmtSel->fetch();
$stmtSel->close();

if ($existe) {
    // UPDATE
    $sqlUpd = "
        UPDATE nfc_credencial
        SET usuario_id = ?, tipo = ?, activo = ?
        WHERE id = ?
    ";
    $stmtUpd = $mysqli->prepare($sqlUpd);
    if (!$stmtUpd) {
        echo json_encode(["ok" => false, "msg" => "Error prepare UPDATE"]);
        exit;
    }
    $stmtUpd->bind_param("isii", $usuario_id, $tipo, $activo, $credId);
    $stmtUpd->execute();
    $stmtUpd->close();

    $msg = "Sensor actualizado correctamente";
} else {
    // INSERT
    $sqlIns = "
        INSERT INTO nfc_credencial (usuario_id, tipo, uid_tag, activo)
        VALUES (?, ?, ?, ?)
    ";
    $stmt2 = $mysqli->prepare($sqlIns);
    if (!$stmt2) {
        echo json_encode(["ok" => false, "msg" => "Error prepare INSERT"]);
        exit;
    }
    $stmt2->bind_param("issi", $usuario_id, $tipo, $uid_tag, $activo);
    $stmt2->execute();
    $credId = $stmt2->insert_id; // por si quieres usarlo a futuro
    $stmt2->close();

    $msg = "Sensor registrado correctamente";
}

// ---------------------------------------------
// 5. Regla de negocio: solo 1 usuario activo por credencial
// ---------------------------------------------
// Si esta credencial se marca como activa, desactivamos
// cualquier otra fila con el mismo uid_tag que esté asociada
// a OTROS usuarios.
if ($activo === 1) {
    $sqlInact = "
        UPDATE nfc_credencial
        SET activo = 0
        WHERE uid_tag = ?
          AND usuario_id <> ?
    ";
    $stmtInact = $mysqli->prepare($sqlInact);
    if ($stmtInact) {
        $stmtInact->bind_param("si", $uid_tag, $usuario_id);
        $stmtInact->execute();
        $stmtInact->close();
    }
}

echo json_encode([
    "ok"  => true,
    "msg" => $msg
], JSON_UNESCAPED_UNICODE);
