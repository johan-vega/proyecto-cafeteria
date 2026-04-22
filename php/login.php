<?php
// Endpoint de autenticación para el administrador.
header('Content-Type: application/json; charset=UTF-8');

$host = 'localhost';
$db = 'bd_cafeteria';
$user = 'root';
$pass = '';

try {
    // Conexión a la base de datos para validar credenciales.
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $usuarioIngresado = trim($_POST['user'] ?? '');
    $passwordIngresada = $_POST['pass'] ?? '';

    if ($usuarioIngresado === '' || $passwordIngresada === '') {
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Debes ingresar usuario y contraseña.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Consulta del usuario administrador activo.
    $stmt = $pdo->prepare(
        "SELECT id, username, password_hash, estado
        FROM usuario
        WHERE username = :usuario
        LIMIT 1"
    );
    $stmt->bindValue(':usuario', $usuarioIngresado, PDO::PARAM_STR);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || (int) $usuario['estado'] !== 1 || !password_verify($passwordIngresada, $usuario['password_hash'])) {
        echo json_encode([
            'exito' => false,
            'mensaje' => 'Usuario o contraseña incorrectos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Inicio de sesión protegido para el panel administrativo.
    session_start();
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['username'];
    $_SESSION['rol'] = 'admin';

    echo json_encode([
        'exito' => true,
        'mensaje' => 'Acceso concedido.',
        'redirect' => 'dashboard.php'
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'exito' => false,
        'mensaje' => 'No se pudo validar el acceso con la base de datos.'
    ], JSON_UNESCAPED_UNICODE);
}
