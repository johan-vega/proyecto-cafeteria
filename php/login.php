<?php
// Indicar que la respuesta será en formato JSON
header('Content-Type: application/json');

// Credenciales de la Base de Datos
$host = 'localhost';
$db   = 'bd_cafeteria';
$user = 'root';
$pass = ''; // Cambiar si tu MySQL tiene contraseña

// Manejar logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_start();
    // Limpiar todas las variables de sesión
    $_SESSION = array();
    // Destruir la cookie de sesión
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    echo json_encode(['exito' => true, 'mensaje' => 'Sesión cerrada']);
    exit;
}

try {
    // Conexión segura usando PDO
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Recibir los datos del POST (enviados por AJAX)
    $usuarioIngresado = $_POST['user'] ?? '';
    $passwordIngresada = $_POST['pass'] ?? '';

    // Preparar la consulta SQL para buscar al usuario
    $sql = "SELECT id, password_hash FROM usuario WHERE username = :usuario LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':usuario', $usuarioIngresado);
    $stmt->execute();

    $usuarioFila = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validar si el usuario existe y si la contraseña coincide con el hash
    if ($usuarioFila && password_verify($passwordIngresada, $usuarioFila['password_hash'])) {

        // Iniciar sesión en PHP (opcional pero recomendado para mantener el estado)
        session_start();
        $_SESSION['usuario_id'] = $usuarioFila['id'];

        echo json_encode([
            "exito" => true,
            "mensaje" => "Login correcto"
        ], JSON_UNESCAPED_UNICODE);
    } else {
        // Credenciales incorrectas
        echo json_encode([
            "exito" => false,
            "mensaje" => "Usuario o contraseña incorrectos."
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    // Manejo de errores de base de datos
    echo json_encode([
        "exito" => false,
        "mensaje" => "Error de conexión a la BD."
    ], JSON_UNESCAPED_UNICODE);
}
