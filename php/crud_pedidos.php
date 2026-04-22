<?php
// Endpoint de la sección Nuevo pedido.
// Registra ventas reales desde el POS y descuenta stock del inventario.
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Solo el administrador autenticado puede operar sobre pedidos.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Acceso no autorizado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$host = 'localhost';
$db = 'bd_cafeteria';
$user = 'root';
$pass = '';

try {
    // Conexión a la base de datos usada por pedidos y ventas.
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Crea las tablas de ventas si todavía no existen.
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

$input = json_decode(file_get_contents('php://input'), true);
$accion = $_GET['accion'] ?? $_POST['accion'] ?? ($input['accion'] ?? '');

try {
    // Registra una venta completa desde el checkout del POS.
    if ($accion === 'registrar_venta') {
        $items = $input['items'] ?? [];
        $metodoPago = trim($input['metodo_pago'] ?? 'Efectivo');
        $cliente = trim($input['cliente'] ?? 'Consumidor final');
        $comprobante = trim($input['comprobante'] ?? 'Boleta referencial');

        if (!is_array($items) || count($items) === 0) {
            http_response_code(422);
            echo json_encode(['error' => 'No hay productos para registrar en la venta.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pdo->beginTransaction();

        $total = 0;
        $cantidadItems = 0;

        foreach ($items as $item) {
            $productoId = (int) ($item['id'] ?? 0);
            $cantidad = (int) ($item['cantidad'] ?? 0);

            if ($productoId <= 0 || $cantidad <= 0) {
                throw new RuntimeException('Hay productos inválidos en la venta.');
            }

            $stmtStock = $pdo->prepare("
                SELECT p.ID_PRODUCTO, p.NOMBRE_PRODUCTO, p.STOCK, p.PRECIO, c.NOM_CATEGORIA
                FROM PRODUCTO p
                INNER JOIN CATEGORIA c ON c.ID_CATEGORIA = p.ID_CATEGORIA
                WHERE p.ID_PRODUCTO = :id
                LIMIT 1
            ");
            $stmtStock->execute([':id' => $productoId]);
            $producto = $stmtStock->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                throw new RuntimeException('Uno de los productos ya no existe.');
            }

            if ((int) $producto['STOCK'] < $cantidad) {
                throw new RuntimeException("No hay stock suficiente para {$producto['NOMBRE_PRODUCTO']}.");
            }

            $subtotal = (float) $producto['PRECIO'] * $cantidad;
            $total += $subtotal;
            $cantidadItems += $cantidad;
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

        foreach ($items as $item) {
            $productoId = (int) $item['id'];
            $cantidad = (int) $item['cantidad'];

            $stmtProducto = $pdo->prepare("
                SELECT p.NOMBRE_PRODUCTO, p.PRECIO, c.NOM_CATEGORIA
                FROM PRODUCTO p
                INNER JOIN CATEGORIA c ON c.ID_CATEGORIA = p.ID_CATEGORIA
                WHERE p.ID_PRODUCTO = :id
                LIMIT 1
            ");
            $stmtProducto->execute([':id' => $productoId]);
            $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

            $subtotal = (float) $producto['PRECIO'] * $cantidad;

            $stmtDetalle->execute([
                ':id_venta' => $ventaId,
                ':id_producto' => $productoId,
                ':nombre' => $producto['NOMBRE_PRODUCTO'],
                ':categoria' => $producto['NOM_CATEGORIA'],
                ':cantidad' => $cantidad,
                ':precio_unitario' => $producto['PRECIO'],
                ':subtotal' => $subtotal
            ]);

            $stmtDescontar->execute([
                ':cantidad' => $cantidad,
                ':id_producto' => $productoId
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'exito' => true,
            'mensaje' => 'Venta registrada correctamente.',
            'codigo_venta' => $codigoVenta
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Acción no válida en pedidos.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
