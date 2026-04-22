<?php
header("Content-Type: application/json");

// CONEXIÓN A LA BD (ajusta si es necesario)
$conexion = new mysqli("localhost", "root", "", "tu_base_de_datos");

if ($conexion->connect_error) {
    echo json_encode(["error" => "Error de conexión"]);
    exit;
}

// CONSULTA: ventas por día
$sql = "
    SELECT 
        DATE(fecha) as dia,
        SUM(total) as total
    FROM pedidos
    GROUP BY DATE(fecha)
    ORDER BY dia ASC
";

$resultado = $conexion->query($sql);

$data = [];

if ($resultado) {
    while ($fila = $resultado->fetch_assoc()) {
        $data[] = [
            "dia" => $fila["dia"],
            "total" => floatval($fila["total"])
        ];
    }
}

echo json_encode($data);
?>