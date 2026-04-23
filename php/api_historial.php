<?php

declare(strict_types=1);

$host = 'localhost';
$db = 'bd_cafeteria';
$user = 'root';
$pass = '';

session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    responderError('Acceso no autorizado.', 401);
}

$metodo = obtenerMetodo();
$request = obtenerDatosRequest();
$accion = $request['accion'] ?? ($metodo === 'GET' ? 'listar' : '');

if ($metodo !== 'GET') {
    responderError('Metodo no permitido en historial.', 405);
}

if ($accion !== 'listar') {
    responderError('Accion no valida en historial.', 400);
}

$pdo = conectarBaseDatos();
asegurarTablasVentas($pdo);

try {
    $ventas = $pdo->query("
        SELECT
            ID_VENTA AS id_venta,
            CODIGO_VENTA AS codigo_venta,
            TOTAL AS total,
            CANTIDAD_ITEMS AS cantidad_items,
            METODO_PAGO AS metodo_pago,
            CLIENTE_REFERENCIA AS cliente_referencia,
            COMPROBANTE AS comprobante,
            FECHA_VENTA AS fecha_venta
        FROM VENTA
        ORDER BY FECHA_VENTA DESC, ID_VENTA DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $stmtDetalle = $pdo->prepare("
        SELECT
            ID_DETALLE AS id_detalle,
            ID_PRODUCTO AS id_producto,
            NOMBRE_PRODUCTO AS nombre_producto,
            CATEGORIA AS categoria,
            CANTIDAD AS cantidad,
            PRECIO_UNITARIO AS precio_unitario,
            SUBTOTAL AS subtotal
        FROM VENTA_DETALLE
        WHERE ID_VENTA = :id_venta
        ORDER BY ID_DETALLE ASC
    ");

    foreach ($ventas as &$venta) {
        $stmtDetalle->execute([':id_venta' => $venta['id_venta']]);
        $venta['detalle'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($venta);

    responderExito($ventas);
} catch (Throwable $e) {
    responderError('No se pudo leer el historial de ventas.', 500);
}

function conectarBaseDatos(): PDO
{
    try {
        global $host, $db, $user, $pass;

        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        responderError('No se pudo conectar con la base de datos.', 500);
    }
}

function asegurarTablasVentas(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS VENTA (
            ID_VENTA INT AUTO_INCREMENT PRIMARY KEY,
            CODIGO_VENTA VARCHAR(30) UNIQUE NOT NULL,
            TOTAL DECIMAL(10,2) NOT NULL,
            CANTIDAD_ITEMS INT NOT NULL,
            METODO_PAGO VARCHAR(30) NOT NULL,
            CLIENTE_REFERENCIA VARCHAR(80) DEFAULT 'Consumidor final',
            COMPROBANTE VARCHAR(40) DEFAULT 'Boleta referencial',
            FECHA_VENTA DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS VENTA_DETALLE (
            ID_DETALLE INT AUTO_INCREMENT PRIMARY KEY,
            ID_VENTA INT NOT NULL,
            ID_PRODUCTO INT NOT NULL,
            NOMBRE_PRODUCTO VARCHAR(100) NOT NULL,
            CATEGORIA VARCHAR(60) NOT NULL,
            CANTIDAD INT NOT NULL,
            PRECIO_UNITARIO DECIMAL(10,2) NOT NULL,
            SUBTOTAL DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (ID_VENTA) REFERENCES VENTA(ID_VENTA) ON DELETE CASCADE
        )
    ");
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
