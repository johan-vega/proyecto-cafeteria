<?php
// Endpoint base de la sección Inicio.
// Aquí podremos reunir métricas generales del dashboard.
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Solo el administrador autenticado puede consultar el resumen inicial.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Acceso no autorizado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Respuesta temporal mientras conectamos indicadores reales.
echo json_encode([
    'exito' => true,
    'seccion' => 'inicio',
    'mensaje' => 'La sección Inicio está preparada para implementación.'
], JSON_UNESCAPED_UNICODE);
