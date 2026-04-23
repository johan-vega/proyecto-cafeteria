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

if (obtenerMetodo() !== 'GET') {
    responderError('Metodo no permitido en inicio.', 405);
}

$pdo = conectarBaseDatos();
asegurarTablasVentas($pdo);

try {
    $resumenProductos = $pdo->query("
        SELECT
            COUNT(*) AS total_productos,
            COALESCE(AVG(PRECIO), 0) AS precio_promedio,
            COALESCE(SUM(STOCK), 0) AS stock_total
        FROM PRODUCTO
    ")->fetch(PDO::FETCH_ASSOC);

    $resumenCategorias = $pdo->query("
        SELECT COUNT(*) AS total_categorias
        FROM CATEGORIA
    ")->fetch(PDO::FETCH_ASSOC);

    $categorias = $pdo->query("
        SELECT
            c.ID_CATEGORIA AS id_categoria,
            c.NOM_CATEGORIA AS nombre_categoria,
            COUNT(p.ID_PRODUCTO) AS total_productos
        FROM CATEGORIA c
        LEFT JOIN PRODUCTO p ON p.ID_CATEGORIA = c.ID_CATEGORIA
        GROUP BY c.ID_CATEGORIA, c.NOM_CATEGORIA
        ORDER BY c.NOM_CATEGORIA ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $productosDestacados = $pdo->query("
        SELECT
            p.ID_PRODUCTO AS id_producto,
            p.NOMBRE_PRODUCTO AS nombre_producto,
            p.PRECIO AS precio,
            p.STOCK AS stock,
            c.ID_CATEGORIA AS id_categoria,
            c.NOM_CATEGORIA AS nombre_categoria
        FROM PRODUCTO p
        INNER JOIN CATEGORIA c ON c.ID_CATEGORIA = p.ID_CATEGORIA
        ORDER BY p.ID_PRODUCTO DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    $ventasHoy = $pdo->query("
        SELECT
            COUNT(*) AS total_ventas_hoy,
            COALESCE(SUM(TOTAL), 0) AS ingreso_total_hoy
        FROM VENTA
        WHERE DATE(FECHA_VENTA) = CURDATE()
    ")->fetch(PDO::FETCH_ASSOC);

    responderExito([
        'total_productos' => (int) ($resumenProductos['total_productos'] ?? 0),
        'total_categorias' => (int) ($resumenCategorias['total_categorias'] ?? 0),
        'precio_promedio' => (float) ($resumenProductos['precio_promedio'] ?? 0),
        'stock_total' => (int) ($resumenProductos['stock_total'] ?? 0),
        'total_ventas_hoy' => (int) ($ventasHoy['total_ventas_hoy'] ?? 0),
        'ingreso_total_hoy' => (float) ($ventasHoy['ingreso_total_hoy'] ?? 0),
        'categorias' => array_map(static function (array $categoria): array {
            return [
                'id_categoria' => $categoria['id_categoria'],
                'nombre_categoria' => $categoria['nombre_categoria'],
                'total_productos' => (int) $categoria['total_productos']
            ];
        }, $categorias),
        'productos_destacados' => array_map(static function (array $producto): array {
            return [
                'id_producto' => (int) $producto['id_producto'],
                'nombre_producto' => $producto['nombre_producto'],
                'precio' => (float) $producto['precio'],
                'stock' => (int) $producto['stock'],
                'id_categoria' => $producto['id_categoria'],
                'nombre_categoria' => $producto['nombre_categoria']
            ];
        }, $productosDestacados)
    ]);
} catch (Throwable $e) {
    responderError('No se pudo cargar el resumen de inicio.', 500);
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
