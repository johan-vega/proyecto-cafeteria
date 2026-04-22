<?php
session_start();

if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    header('Location: dashboard.php');
    exit;
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Star Coffee | Acceso Administrador</title>
    <link rel="shortcut icon" href="img/starcoffee.jpeg" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Saira+Stencil+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Layout principal del acceso administrativo -->
    <main class="login-shell">
        <!-- Bloque visual de presentación de la cafetería -->
        <section class="brand-panel">
            <div class="brand-copy">
                <span class="brand-badge"><i class="fa-solid fa-mug-hot"></i> Panel de cafetería</span>
                <h1>STAR COFFEE</h1>
                <p>
                    Accede al panel administrativo para revisar ventas, gestionar el catálogo y operar el punto de venta.
                </p>
                <ul class="brand-list">
                    <li><i class="fa-solid fa-receipt"></i><span>Control y flujo de cobro.</span></li>
                    <li><i class="fa-solid fa-boxes-stacked"></i><span>Catálogo de productos con categorías y stock.</span></li>
                    <li><i class="fa-solid fa-chart-line"></i><span>Panel preparado para métricas del turno.</span></li>
                </ul>
            </div>

            <div class="brand-footer">
                <span>Administrador Unico</span>
                <span>Star Coffee © 2026</span>
            </div>
        </section>

        <!-- Tarjeta de login para el administrador -->
        <section class="login-card">
            <div class="login-top">
                <div class="login-brand">
                    <img src="img/sinfondocoffee.png" alt="Logo Star Coffee">
                    <div>
                        <h2>Acceso administrador</h2>
                        <p>Inicio seguro para el panel de control.</p>
                    </div>
                </div>
                <span class="helper-chip"><i class="fa-solid fa-shield-halved"></i> Solo personal autorizado</span>
            </div>

            <!-- Formulario de autenticación -->
            <form id="loginForm" class="login-form">
                <div class="input-group">
                    <label for="txtUsuario">Usuario</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="txtUsuario" placeholder="Nombre de usuario" required autocomplete="username">
                    </div>
                </div>

                <div class="input-group">
                    <label for="txtPassword">Contraseña</label>
                    <div class="input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="txtPassword" placeholder="Tu contraseña" required autocomplete="current-password">
                        <i class="fa-solid fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="btnLogin">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    <span>Ingresar al sistema</span>
                </button>
            </form>

            <p class="login-footer">¿Problemas para acceder? <span>Contacta al administrador principal.</span></p>
        </section>
    </main>

    <script src="js/login.js"></script>
</body>
</html>
