<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$db = "bd_cafeteria";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "No se pudo conectar con la base de datos."]);
    exit;
}

try {
    $sql = "
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
    ";

    $stmt = $pdo->query($sql);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($productos, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "No se pudieron obtener los productos."]);
}
