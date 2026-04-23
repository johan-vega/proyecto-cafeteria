<?php

// Guarda datos del local, año activo y cambio de contraseña del administrador.
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Solo el administrador autenticado puede consultar o guardar ajustes.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Acceso no autorizado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$host = 'sql100.infinityfree.com';
$db = 'if0_41711637_bd_cafeteria';
$user = 'if0_41711637';
$pass = 'xOAWNxbCu1';

try {
    // Conexión a la base de datos usada por ajustes.
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Crea la tabla de configuración si todavía no existe.
$pdo->exec("
    CREATE TABLE IF NOT EXISTS CONFIGURACION_LOCAL (
        ID_CONFIGURACION INT PRIMARY KEY,
        NOMBRE_LOCAL VARCHAR(120) NOT NULL,
        CORREO_LOCAL VARCHAR(120) NOT NULL,
        DIRECCION_LOCAL VARCHAR(180) NOT NULL,
        TELEFONO_LOCAL VARCHAR(20) NOT NULL,
        ANIO_LOCAL INT NOT NULL
    )
");

$pdo->exec("
    INSERT INTO CONFIGURACION_LOCAL (ID_CONFIGURACION, NOMBRE_LOCAL, CORREO_LOCAL, DIRECCION_LOCAL, TELEFONO_LOCAL, ANIO_LOCAL)
    SELECT 1, 'Star Coffee', 'starcoffee@local.com', 'Av. Principal 123', '999111222', 2026
    WHERE NOT EXISTS (
        SELECT 1 FROM CONFIGURACION_LOCAL WHERE ID_CONFIGURACION = 1
    )
");

$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'obtener';

try {
    // Devuelve la configuración actual del local.
    if ($accion === 'obtener') {
        $stmt = $pdo->query("
            SELECT
                NOMBRE_LOCAL AS nombre_local,
                CORREO_LOCAL AS correo_local,
                DIRECCION_LOCAL AS direccion_local,
                TELEFONO_LOCAL AS telefono_local,
                ANIO_LOCAL AS anio_local
            FROM CONFIGURACION_LOCAL
            WHERE ID_CONFIGURACION = 1
            LIMIT 1
        ");

        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Guarda los datos principales del local.
    if ($accion === 'guardar_local') {
        $stmt = $pdo->prepare("
            UPDATE CONFIGURACION_LOCAL
            SET
                NOMBRE_LOCAL = :nombre,
                CORREO_LOCAL = :correo,
                DIRECCION_LOCAL = :direccion,
                TELEFONO_LOCAL = :telefono
            WHERE ID_CONFIGURACION = 1
        ");
        $stmt->execute([
            ':nombre' => trim($_POST['nombre_local'] ?? ''),
            ':correo' => trim($_POST['correo_local'] ?? ''),
            ':direccion' => trim($_POST['direccion_local'] ?? ''),
            ':telefono' => trim($_POST['telefono_local'] ?? '')
        ]);

        echo json_encode(['exito' => true, 'mensaje' => 'Datos del local actualizados correctamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Guarda el año activo del local.
    if ($accion === 'guardar_anio') {
        $anio = (int) ($_POST['anio_local'] ?? 0);

        if ($anio < 2024 || $anio > 2100) {
            http_response_code(422);
            echo json_encode(['error' => 'Ingresa un año válido para el local.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE CONFIGURACION_LOCAL
            SET ANIO_LOCAL = :anio
            WHERE ID_CONFIGURACION = 1
        ");
        $stmt->execute([':anio' => $anio]);

        echo json_encode(['exito' => true, 'mensaje' => 'Año activo del local actualizado correctamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Cambia la contraseña del administrador autenticado.
    if ($accion === 'cambiar_password') {
        $passwordActual = $_POST['password_actual'] ?? '';
        $passwordNueva = $_POST['password_nueva'] ?? '';
        $passwordConfirmar = $_POST['password_confirmar'] ?? '';

        if ($passwordNueva === '' || $passwordNueva !== $passwordConfirmar) {
            http_response_code(422);
            echo json_encode(['error' => 'La nueva contraseña no coincide con la confirmación.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtUsuario = $pdo->prepare("
            SELECT ID, PASSWORD_HASH
            FROM USUARIO
            WHERE ID = :id
            LIMIT 1
        ");
        $stmtUsuario->execute([':id' => $_SESSION['usuario_id']]);
        $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($passwordActual, $usuario['PASSWORD_HASH'])) {
            http_response_code(422);
            echo json_encode(['error' => 'La contraseña actual no es correcta.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $nuevoHash = password_hash($passwordNueva, PASSWORD_DEFAULT);

        $stmtUpdate = $pdo->prepare("
            UPDATE USUARIO
            SET PASSWORD_HASH = :password
            WHERE ID = :id
        ");
        $stmtUpdate->execute([
            ':password' => $nuevoHash,
            ':id' => $_SESSION['usuario_id']
        ]);

        echo json_encode(['exito' => true, 'mensaje' => 'Contraseña actualizada correctamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Acción no válida en ajustes.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo completar la operación de ajustes.'], JSON_UNESCAPED_UNICODE);
}
