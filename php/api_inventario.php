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
$accion = resolverAccionInventario($metodo, $request);

try {
    switch ($accion) {
        case 'categorias':
            listarCategorias($pdo);
            break;

        case 'listar':
            listarProductos($pdo);
            break;

        case 'crear':
            crearProducto($pdo, $request);
            break;

        case 'editar':
            editarProducto($pdo, $request);
            break;

        case 'eliminar':
            eliminarProducto($pdo, $request);
            break;

        default:
            responderError('Accion no valida en inventario.', 400);
    }
} catch (PDOException $e) {
    responderError('No se pudo completar la operacion de inventario.', 500);
}

function resolverAccionInventario(string $metodo, array $request): string
{
    $accion = $request['accion'] ?? $request['action'] ?? '';
    if ($accion !== '') {
        return (string) $accion;
    }

    if ($metodo === 'GET') {
        $recurso = $request['recurso'] ?? $request['resource'] ?? '';
        return $recurso === 'categorias' ? 'categorias' : 'listar';
    }

    if ($metodo === 'POST') {
        return 'crear';
    }

    if ($metodo === 'PUT' || $metodo === 'PATCH') {
        return 'editar';
    }

    if ($metodo === 'DELETE') {
        return 'eliminar';
    }

    return '';
}

function listarCategorias(PDO $pdo): void
{
    $categorias = $pdo->query("
        SELECT
            ID_CATEGORIA AS id_categoria,
            NOM_CATEGORIA AS nombre_categoria
        FROM CATEGORIA
        ORDER BY NOM_CATEGORIA ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    responderExito($categorias);
}

function listarProductos(PDO $pdo): void
{
    $productos = $pdo->query("
        SELECT
            p.ID_PRODUCTO AS id_producto,
            p.NOMBRE_PRODUCTO AS nombre_producto,
            p.STOCK AS stock,
            p.PRECIO AS precio,
            c.ID_CATEGORIA AS id_categoria,
            c.NOM_CATEGORIA AS nombre_categoria
        FROM PRODUCTO p
        INNER JOIN CATEGORIA c ON c.ID_CATEGORIA = p.ID_CATEGORIA
        ORDER BY c.NOM_CATEGORIA ASC, p.NOMBRE_PRODUCTO ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $productos = array_map(static function (array $producto): array {
        return [
            'id_producto' => (int) $producto['id_producto'],
            'nombre_producto' => $producto['nombre_producto'],
            'stock' => (int) $producto['stock'],
            'precio' => (float) $producto['precio'],
            'id_categoria' => $producto['id_categoria'],
            'nombre_categoria' => $producto['nombre_categoria']
        ];
    }, $productos);

    responderExito($productos);
}

function crearProducto(PDO $pdo, array $request): void
{
    $nombre = trim((string) ($request['nombre_producto'] ?? ''));
    $stock = (int) ($request['stock'] ?? 0);
    $precio = (float) ($request['precio'] ?? 0);
    $categoriaId = trim((string) ($request['id_categoria'] ?? ''));

    validarProducto($pdo, $nombre, $stock, $precio, $categoriaId);

    $stmt = $pdo->prepare("
        INSERT INTO PRODUCTO (NOMBRE_PRODUCTO, STOCK, ID_CATEGORIA, PRECIO)
        VALUES (:nombre, :stock, :categoria, :precio)
    ");
    $stmt->execute([
        ':nombre' => $nombre,
        ':stock' => $stock,
        ':categoria' => $categoriaId,
        ':precio' => $precio
    ]);

    responderExito(['id_producto' => (int) $pdo->lastInsertId()], 'Producto registrado correctamente.', 201);
}

function editarProducto(PDO $pdo, array $request): void
{
    $id = (int) ($request['id_producto'] ?? 0);
    $nombre = trim((string) ($request['nombre_producto'] ?? ''));
    $stock = (int) ($request['stock'] ?? 0);
    $precio = (float) ($request['precio'] ?? 0);
    $categoriaId = trim((string) ($request['id_categoria'] ?? ''));

    if ($id <= 0) {
        responderError('Producto no valido.', 422);
    }

    validarProducto($pdo, $nombre, $stock, $precio, $categoriaId);

    $stmt = $pdo->prepare("
        UPDATE PRODUCTO
        SET NOMBRE_PRODUCTO = :nombre,
            STOCK = :stock,
            ID_CATEGORIA = :categoria,
            PRECIO = :precio
        WHERE ID_PRODUCTO = :id
    ");
    $stmt->execute([
        ':nombre' => $nombre,
        ':stock' => $stock,
        ':categoria' => $categoriaId,
        ':precio' => $precio,
        ':id' => $id
    ]);

    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM PRODUCTO WHERE ID_PRODUCTO = :id');
        $exists->execute([':id' => $id]);
        if ((int) $exists->fetchColumn() === 0) {
            responderError('El producto no existe.', 404);
        }
    }

    responderExito(['id_producto' => $id], 'Producto actualizado correctamente.');
}

function eliminarProducto(PDO $pdo, array $request): void
{
    $id = (int) ($request['id_producto'] ?? 0);

    if ($id <= 0) {
        responderError('Producto no valido.', 422);
    }

    $stmt = $pdo->prepare('DELETE FROM PRODUCTO WHERE ID_PRODUCTO = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        responderError('El producto no existe.', 404);
    }

    responderExito(['id_producto' => $id], 'Producto eliminado correctamente.');
}

function validarProducto(PDO $pdo, string $nombre, int $stock, float $precio, string $categoriaId): void
{
    if ($nombre === '' || $categoriaId === '' || $stock < 0 || $precio <= 0) {
        responderError('Completa correctamente los datos del producto.', 422);
    }

    $stmtCategoria = $pdo->prepare('SELECT COUNT(*) FROM CATEGORIA WHERE ID_CATEGORIA = :id');
    $stmtCategoria->execute([':id' => $categoriaId]);

    if ((int) $stmtCategoria->fetchColumn() === 0) {
        responderError('La categoria seleccionada no existe.', 422);
    }
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
