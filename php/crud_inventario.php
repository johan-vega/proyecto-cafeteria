<?php
// Atiende el catálogo del POS y la tabla administrativa de productos.
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Solo el administrador autenticado puede operar sobre inventario.
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
    // Conexión a la base de datos usada por inventario.
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar con la base de datos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'listar';

try {
    // Lista las categorías para los selectores del formulario de inventario.
    if ($accion === 'categorias') {
        $stmt = $pdo->query("
            SELECT ID_CATEGORIA AS id_categoria, NOM_CATEGORIA AS nombre_categoria
            FROM CATEGORIA
            ORDER BY NOM_CATEGORIA ASC
        ");

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Lista los productos con su categoría para el POS y la tabla de inventario.
    if ($accion === 'listar') {
        $stmt = $pdo->query("
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
        ");

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Crea un nuevo producto dentro del inventario.
    if ($accion === 'crear') {
        $nombre = trim($_POST['nombre_producto'] ?? '');
        $stock = (int) ($_POST['stock'] ?? 0);
        $precio = (float) ($_POST['precio'] ?? 0);
        $categoriaId = trim($_POST['id_categoria'] ?? '');

        if ($nombre === '' || $categoriaId === '' || $stock < 0 || $precio <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Completa correctamente los datos del producto.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

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

        echo json_encode(['exito' => true, 'mensaje' => 'Producto registrado correctamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Actualiza un producto existente del inventario.
    if ($accion === 'editar') {
        $id = (int) ($_POST['id_producto'] ?? 0);
        $nombre = trim($_POST['nombre_producto'] ?? '');
        $stock = (int) ($_POST['stock'] ?? 0);
        $precio = (float) ($_POST['precio'] ?? 0);
        $categoriaId = trim($_POST['id_categoria'] ?? '');

        if ($id <= 0 || $nombre === '' || $categoriaId === '' || $stock < 0 || $precio <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Completa correctamente los datos del producto.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

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

        echo json_encode(['exito' => true, 'mensaje' => 'Producto actualizado correctamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Elimina un producto del inventario.
    if ($accion === 'eliminar') {
        $id = (int) ($_POST['id_producto'] ?? 0);

        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['error' => 'Producto no válido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM PRODUCTO WHERE ID_PRODUCTO = :id");
        $stmt->execute([':id' => $id]);

        echo json_encode(['exito' => true, 'mensaje' => 'Producto eliminado correctamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo completar la operación de inventario.'], JSON_UNESCAPED_UNICODE);
}
