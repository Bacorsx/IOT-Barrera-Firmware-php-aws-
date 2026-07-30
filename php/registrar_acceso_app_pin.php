<?php
// registrar_acceso_app_pin.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require_once __DIR__ . "/db.php";   // conexión $mysqli

if (!isset($mysqli) || $mysqli->connect_errno) {
    echo json_encode(["ok" => false, "msg" => "Error de conexión"]);
    exit;
}

$mysqli->set_charset("utf8mb4");


// ================================
// 1. VALIDAR ENTRADA
// ================================
$usuario_id = intval($_POST['usuario_id'] ?? 0);
$lector_id  = trim($_POST['lector_id'] ?? "");

// La app envía "dispositivo", pero dejamos compatibilidad con "tipo"
$dispositivo = trim($_POST['dispositivo'] ?? ($_POST['tipo'] ?? "APP_PIN"));

// fecha enviada por la app (DEBE venir en formato MySQL: yyyy-MM-dd HH:mm:ss)
$fecha_app  = trim($_POST['fecha_app'] ?? "");

if ($usuario_id <= 0 || $lector_id === "") {
    echo json_encode([
        "ok"  => false,
        "msg" => "Datos incompletos (usuario_id o lector_id faltan)"
    ]);
    exit;
}


// ================================
// 2. BUSCAR CREDENCIAL APP_PIN DEL USUARIO
// ================================
$credencial_id = null;     // por defecto
$resultado     = "DENEGADO";
$motivo        = "Credencial no registrada";

$stmtC = $mysqli->prepare("
    SELECT id, activo
    FROM nfc_credencial
    WHERE usuario_id = ? AND tipo = 'APP_PIN'
    LIMIT 1
");

if ($stmtC) {
    $stmtC->bind_param("i", $usuario_id);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    $cred = $resC->fetch_assoc();
    $stmtC->close();

    if ($cred) {
        if ((int)$cred['activo'] === 1) {
            $credencial_id = (int)$cred['id'];
            $resultado     = "PERMITIDO";
            $motivo        = "Credencial activa";
        } else {
            $resultado = "DENEGADO";
            $motivo    = "Credencial inactiva";
        }
    }
}


// ================================
// 3. FECHA REAL A REGISTRAR
// ================================
if ($fecha_app !== "") {
    $ts = strtotime($fecha_app);
    if ($ts === false) {
        $fecha_final = date("Y-m-d H:i:s");
    } else {
        $fecha_final = date("Y-m-d H:i:s", $ts);
    }
} else {
    $fecha_final = date("Y-m-d H:i:s");
}


// ================================
// 4. REGISTRAR INTENTO DE ACCESO
//    (si hay credencial, incluimos credencial_id;
//     si no hay credencial, lo dejamos NULL usando otro INSERT)
// ================================
if ($credencial_id !== null) {
    // Con credencial asociada
    $stmt2 = $mysqli->prepare("
        INSERT INTO nfc_intento_acceso
        (credencial_id, usuario_id, lector_id, fecha, resultado, dispositivo, motivo)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt2) {
        echo json_encode([
            "ok"  => false,
            "msg" => "Error preparando inserción intento (con credencial): " . $mysqli->error
        ]);
        exit;
    }

    $stmt2->bind_param(
        "iisssss",
        $credencial_id,
        $usuario_id,
        $lector_id,
        $fecha_final,
        $resultado,
        $dispositivo,
        $motivo
    );
} else {
    // Sin credencial asociada -> credencial_id queda NULL
    $stmt2 = $mysqli->prepare("
        INSERT INTO nfc_intento_acceso
        (usuario_id, lector_id, fecha, resultado, dispositivo, motivo)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt2) {
        echo json_encode([
            "ok"  => false,
            "msg" => "Error preparando inserción intento (sin credencial): " . $mysqli->error
        ]);
        exit;
    }

    $stmt2->bind_param(
        "isssss",
        $usuario_id,
        $lector_id,
        $fecha_final,
        $resultado,
        $dispositivo,
        $motivo
    );
}

if (!$stmt2->execute()) {
    $error = $stmt2->error;
    $stmt2->close();
    echo json_encode([
        "ok"  => false,
        "msg" => "Error ejecutando inserción intento: " . $error
    ]);
    exit;
}

$stmt2->close();


// ================================
// 5. SI ES PERMITIDO, INSERTAR COMANDO PARA EL SERVO
// ================================
if ($resultado === "PERMITIDO") {
    $check = $mysqli->query("SHOW TABLES LIKE 'comandos_puerta'");
    if ($check && $check->num_rows > 0) {
        $stmt3 = $mysqli->prepare("
            INSERT INTO comandos_puerta (lector_id, abrir, created_at)
            VALUES (?, 1, NOW())
        ");
        if ($stmt3) {
            $stmt3->bind_param("s", $lector_id);
            $stmt3->execute();
            $stmt3->close();
        }
    }
}


// ================================
// 6. RESPUESTA JSON
// ================================
echo json_encode([
    "ok"         => true,
    "registrado" => true,
    "resultado"  => $resultado,
    "mensaje"    => $motivo,
    "credencial_id" => $credencial_id
], JSON_UNESCAPED_UNICODE);

exit;
