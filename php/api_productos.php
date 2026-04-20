<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// CONEXIÓN A TU BD (usa los mismos datos que ya tienes)
$host = "localhost";
$db = "bd_cafeteria";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    echo json_encode(["error" => "Error de conexión"]);
    exit;
}

// CONSULTA PRODUCTOS
try {
    $sql = "SELECT ID_PRODUCTO, NOMBRE_PRODUCTO, PRECIO FROM PRODUCTO";
    $stmt = $pdo->query($sql);

    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($productos);

} catch (PDOException $e) {
    echo json_encode(["error" => "Error al obtener productos"]);
}
?>