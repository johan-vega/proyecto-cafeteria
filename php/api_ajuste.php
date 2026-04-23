<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    responderError('Acceso no autorizado.', 401);
}

$pdo = conectarBaseDatos();
$request = obtenerDatosRequest();
$metodo = obtenerMetodo();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS CONFIGURACION_LOCAL (
        ID_CONFIGURACION INT PRIMARY KEY,
        NOMBRE_LOCAL VARCHAR(120),
        CORREO_LOCAL VARCHAR(120),
        DIRECCION_LOCAL VARCHAR(180),
        TELEFONO_LOCAL VARCHAR(20),
        ANIO_LOCAL INT
    )
");

$pdo->exec("
    INSERT IGNORE INTO CONFIGURACION_LOCAL
    VALUES (1, 'Star Coffee', 'starcoffee@local.com', 'Av. Principal 123', '999111222', 2026)
");

try {
    switch ($metodo) {
        case 'GET':
            obtenerConfiguracion($pdo);
            break;

        case 'POST':
            if (($request['tipo'] ?? '') !== 'password') {
                responderError('Tipo no valido.', 400);
            }
            cambiarPassword($pdo, $request);
            break;

        case 'PUT':
        case 'PATCH':
            actualizarConfiguracion($pdo, $request);
            break;

        default:
            responderError('Metodo no permitido.', 405);
    }
} catch (PDOException $e) {
    responderError('No se pudieron procesar los ajustes.', 500);
}

function obtenerConfiguracion(PDO $pdo): void
{
    $stmt = $pdo->query("
        SELECT
            ID_CONFIGURACION AS id_configuracion,
            NOMBRE_LOCAL AS nombre_local,
            CORREO_LOCAL AS correo_local,
            DIRECCION_LOCAL AS direccion_local,
            TELEFONO_LOCAL AS telefono_local,
            ANIO_LOCAL AS anio_local
        FROM CONFIGURACION_LOCAL
        WHERE ID_CONFIGURACION = 1
    ");

    $configuracion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$configuracion) {
        responderError('No se encontro la configuracion del local.', 404);
    }

    $configuracion['id_configuracion'] = (int) $configuracion['id_configuracion'];
    $configuracion['anio_local'] = (int) $configuracion['anio_local'];

    responderExito($configuracion);
}

function actualizarConfiguracion(PDO $pdo, array $data): void
{
    $tipo = (string) ($data['tipo'] ?? inferirTipoActualizacion($data));

    if ($tipo === 'local') {
        actualizarLocal($pdo, $data);
    }

    if ($tipo === 'anio') {
        actualizarAnio($pdo, $data);
    }

    responderError('Tipo no valido.', 400);
}

function inferirTipoActualizacion(array $data): string
{
    if (isset($data['anio']) && !isset($data['nombre']) && !isset($data['correo'])) {
        return 'anio';
    }

    if (isset($data['nombre']) || isset($data['correo']) || isset($data['direccion']) || isset($data['telefono'])) {
        return 'local';
    }

    return '';
}

function actualizarLocal(PDO $pdo, array $data): void
{
    $nombre = trim((string) ($data['nombre'] ?? ''));
    $correo = trim((string) ($data['correo'] ?? ''));
    $direccion = trim((string) ($data['direccion'] ?? ''));
    $telefono = trim((string) ($data['telefono'] ?? ''));

    if ($nombre === '' || $correo === '' || $direccion === '' || $telefono === '') {
        responderError('Completa todos los datos del local.', 422);
    }

    $stmt = $pdo->prepare("
        UPDATE CONFIGURACION_LOCAL
        SET NOMBRE_LOCAL = ?,
            CORREO_LOCAL = ?,
            DIRECCION_LOCAL = ?,
            TELEFONO_LOCAL = ?
        WHERE ID_CONFIGURACION = 1
    ");

    $stmt->execute([$nombre, $correo, $direccion, $telefono]);

    responderExito(null, 'Datos del local actualizados.');
}

function actualizarAnio(PDO $pdo, array $data): void
{
    $anio = (int) ($data['anio'] ?? 0);

    if ($anio < 2024 || $anio > 2100) {
        responderError('Año invalido.', 422);
    }

    $stmt = $pdo->prepare("
        UPDATE CONFIGURACION_LOCAL
        SET ANIO_LOCAL = ?
        WHERE ID_CONFIGURACION = 1
    ");

    $stmt->execute([$anio]);

    responderExito(['anio_local' => $anio], 'Año actualizado.');
}

function cambiarPassword(PDO $pdo, array $data): void
{
    $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

    if ($usuarioId <= 0) {
        responderError('Sesion invalida.', 401);
    }

    $passwordActual = (string) ($data['actual'] ?? '');
    $passwordNueva = (string) ($data['nueva'] ?? '');
    $passwordConfirmar = (string) ($data['confirmar'] ?? '');

    if ($passwordActual === '' || $passwordNueva === '' || $passwordConfirmar === '') {
        responderError('Completa todos los campos de contraseña.', 422);
    }

    $stmt = $pdo->prepare('SELECT PASSWORD_HASH FROM USUARIO WHERE ID = ?');
    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || !password_verify($passwordActual, $usuario['PASSWORD_HASH'])) {
        responderError('Password incorrecto.', 422);
    }

    if ($passwordNueva !== $passwordConfirmar) {
        responderError('No coincide la nueva contraseña.', 422);
    }

    $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('UPDATE USUARIO SET PASSWORD_HASH = ? WHERE ID = ?');
    $stmt->execute([$hash, $usuarioId]);

    responderExito(null, 'Contraseña actualizada.');
}

function conectarBaseDatos(): PDO
{
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=bd_cafeteria;charset=utf8mb4', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        responderError('No se pudo conectar con la base de datos.', 500);
    }
}

function obtenerMetodo(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function obtenerDatosRequest(): array
{
    $datos = [];

    if (!empty($_GET)) {
        $datos = array_merge($datos, $_GET);
    }

    if (!empty($_POST)) {
        $datos = array_merge($datos, $_POST);
    }

    $raw = file_get_contents('php://input') ?: '';

    if ($raw !== '') {
        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $datos = array_merge($datos, $json);
        } else {
            parse_str($raw, $formData);
            if (!empty($formData)) {
                $datos = array_merge($datos, $formData);
            }
        }
    }

    return $datos;
}

function responderExito($data = null, ?string $mensaje = null, int $status = 200): void
{
    http_response_code($status);

    $payload = [
        'success' => true,
        'exito' => true,
        'data' => $data
    ];

    if ($mensaje !== null) {
        $payload['message'] = $mensaje;
        $payload['mensaje'] = $mensaje;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function responderError(string $mensaje, int $status = 400): void
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'exito' => false,
        'error' => $mensaje,
        'message' => $mensaje,
        'mensaje' => $mensaje
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
