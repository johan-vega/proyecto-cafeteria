<?php
// Endpoint de la sección Historial.
// Lista las ventas registradas y sus detalles.
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Solo el administrador autenticado puede consultar el historial.
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
    // Conexión a la base de datos usada por historial.
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

$accion = $_GET['accion'] ?? 'listar';

try {
    // Lista todas las ventas con su detalle para la sección Historial.
    if ($accion === 'listar') {
        $ventas = $pdo->query("
            SELECT
                ID_VENTA,
                CODIGO_VENTA,
                TOTAL,
                CANTIDAD_ITEMS,
                METODO_PAGO,
                CLIENTE_REFERENCIA,
                COMPROBANTE,
                FECHA_VENTA
            FROM VENTA
            ORDER BY FECHA_VENTA DESC, ID_VENTA DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $stmtDetalle = $pdo->prepare("
            SELECT
                ID_PRODUCTO,
                NOMBRE_PRODUCTO,
                CATEGORIA,
                CANTIDAD,
                PRECIO_UNITARIO,
                SUBTOTAL
            FROM VENTA_DETALLE
            WHERE ID_VENTA = :id_venta
            ORDER BY ID_DETALLE ASC
        ");

        foreach ($ventas as &$venta) {
            $stmtDetalle->execute([':id_venta' => $venta['ID_VENTA']]);
            $venta['detalle'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($ventas, JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Acción no válida en historial.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo leer el historial de ventas.'], JSON_UNESCAPED_UNICODE);
}
