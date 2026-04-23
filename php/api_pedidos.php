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

$pdo = conectarBaseDatos();
asegurarTablasVentas($pdo);

$request = obtenerDatosRequest();
$metodo = obtenerMetodo();
$accion = $request['accion'] ?? ($metodo === 'POST' ? 'registrar_venta' : '');

if ($accion !== 'registrar_venta') {
    responderError('Accion no valida en pedidos.', 400);
}

if ($metodo !== 'POST') {
    responderError('Metodo no permitido en pedidos.', 405);
}

try {
    registrarVenta($pdo, $request);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $codigo = $e instanceof RuntimeException ? 422 : 500;
    responderError($e->getMessage(), $codigo);
}

function registrarVenta(PDO $pdo, array $request): void
{
    $items = $request['items'] ?? [];
    $metodoPago = trim((string) ($request['metodo_pago'] ?? 'Efectivo'));
    $cliente = trim((string) ($request['cliente'] ?? 'Consumidor final'));
    $comprobante = trim((string) ($request['comprobante'] ?? 'Boleta referencial'));

    if (!is_array($items) || count($items) === 0) {
        responderError('No hay productos para registrar en la venta.', 422);
    }

    $pdo->beginTransaction();

    $total = 0.0;
    $cantidadItems = 0;
    $productosVenta = [];

    $stmtProducto = $pdo->prepare("
        SELECT
            p.ID_PRODUCTO AS id_producto,
            p.NOMBRE_PRODUCTO AS nombre_producto,
            p.STOCK AS stock,
            p.PRECIO AS precio,
            c.NOM_CATEGORIA AS nombre_categoria
        FROM PRODUCTO p
        INNER JOIN CATEGORIA c ON c.ID_CATEGORIA = p.ID_CATEGORIA
        WHERE p.ID_PRODUCTO = :id
        LIMIT 1
    ");

    foreach ($items as $item) {
        $productoId = (int) ($item['id'] ?? 0);
        $cantidad = (int) ($item['cantidad'] ?? 0);

        if ($productoId <= 0 || $cantidad <= 0) {
            throw new RuntimeException('Hay productos invalidos en la venta.');
        }

        $stmtProducto->execute([':id' => $productoId]);
        $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            throw new RuntimeException('Uno de los productos ya no existe.');
        }

        if ((int) $producto['stock'] < $cantidad) {
            throw new RuntimeException("No hay stock suficiente para {$producto['nombre_producto']}.");
        }

        $subtotal = (float) $producto['precio'] * $cantidad;
        $total += $subtotal;
        $cantidadItems += $cantidad;
        $productosVenta[] = [
            'id_producto' => (int) $producto['id_producto'],
            'nombre_producto' => $producto['nombre_producto'],
            'categoria' => $producto['nombre_categoria'],
            'cantidad' => $cantidad,
            'precio_unitario' => (float) $producto['precio'],
            'subtotal' => $subtotal
        ];
    }

    $codigoVenta = 'VTA-' . date('YmdHis') . '-' . random_int(100, 999);

    $stmtVenta = $pdo->prepare("
        INSERT INTO VENTA (CODIGO_VENTA, TOTAL, CANTIDAD_ITEMS, METODO_PAGO, CLIENTE_REFERENCIA, COMPROBANTE)
        VALUES (:codigo, :total, :cantidad_items, :metodo_pago, :cliente, :comprobante)
    ");
    $stmtVenta->execute([
        ':codigo' => $codigoVenta,
        ':total' => $total,
        ':cantidad_items' => $cantidadItems,
        ':metodo_pago' => $metodoPago,
        ':cliente' => $cliente,
        ':comprobante' => $comprobante
    ]);

    $ventaId = (int) $pdo->lastInsertId();

    $stmtDetalle = $pdo->prepare("
        INSERT INTO VENTA_DETALLE (
            ID_VENTA, ID_PRODUCTO, NOMBRE_PRODUCTO, CATEGORIA, CANTIDAD, PRECIO_UNITARIO, SUBTOTAL
        ) VALUES (
            :id_venta, :id_producto, :nombre, :categoria, :cantidad, :precio_unitario, :subtotal
        )
    ");

    $stmtDescontar = $pdo->prepare("
        UPDATE PRODUCTO
        SET STOCK = STOCK - :cantidad
        WHERE ID_PRODUCTO = :id_producto
    ");

    foreach ($productosVenta as $productoVenta) {
        $stmtDetalle->execute([
            ':id_venta' => $ventaId,
            ':id_producto' => $productoVenta['id_producto'],
            ':nombre' => $productoVenta['nombre_producto'],
            ':categoria' => $productoVenta['categoria'],
            ':cantidad' => $productoVenta['cantidad'],
            ':precio_unitario' => $productoVenta['precio_unitario'],
            ':subtotal' => $productoVenta['subtotal']
        ]);

        $stmtDescontar->execute([
            ':cantidad' => $productoVenta['cantidad'],
            ':id_producto' => $productoVenta['id_producto']
        ]);
    }

    $pdo->commit();

    responderExito([
        'id_venta' => $ventaId,
        'codigo_venta' => $codigoVenta,
        'total' => $total,
        'cantidad_items' => $cantidadItems,
        'metodo_pago' => $metodoPago
    ], 'Venta registrada correctamente.', 201);
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
