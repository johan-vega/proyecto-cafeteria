<?php
session_start();

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: index.php');
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
    <title>Star Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <script src="https://kit.fontawesome.com/812c8ee19a.js" crossorigin="anonymous"></script>
    <link rel="shortcut icon" href="img/sinfondocoffee.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Saira+Stencil+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Contenedor general del sistema administrativo de la cafetería -->
    <div class="app-shell">
        <!-- Sidebar principal para cambiar entre secciones del dashboard -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <img src="img/sinfondocoffee.png" alt="Logo Star Coffee">
                <div>
                    <p class="brand-eyebrow">Sistema de ventas</p>
                    <h1>Star Coffee</h1>
                </div>
            </div>

            <ul class="sidebar-nav">
                <li class="active">
                    <button class="sidebar-link" type="button" data-module="moduloPanel">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </button>
                </li>
                <li>
                    <button class="sidebar-link" type="button" data-module="moduloInicio">
                        <i class="fa-solid fa-house"></i>
                        <span>Inicio</span>
                    </button>
                </li>
                <li>
                    <button class="sidebar-link" type="button" data-module="moduloPedido">
                        <i class="fa-solid fa-cash-register"></i>
                        <span>Nuevo pedido</span>
                    </button>
                </li>
                <li>
                    <button class="sidebar-link" type="button" data-module="moduloHistorial">
                        <i class="fa-solid fa-receipt"></i>
                        <span>Historial</span>
                    </button>
                </li>
                <li>
                    <button class="sidebar-link" type="button" data-module="moduloInventario">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <span>Inventario</span>
                    </button>
                </li>
                <li>
                    <button class="sidebar-link" type="button" data-module="moduloAjustes">
                        <i class="fa-solid fa-gear"></i>
                        <span>Ajustes</span>
                    </button>
                </li>
                <li>
                    <button class="sidebar-link" id="btnLogoutAside" type="button">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Cerrar sesión</span>
                    </button>
                </li>
            </ul>

            <div class="sidebar-card">
                <p class="sidebar-card-label">Turno activo</p>
                <strong>08:00 AM - 06:00 PM</strong>
                <span>Mostrador y delivery en línea</span>
            </div>
        </aside>

        <!-- Área principal donde se muestran todos los módulos del dashboard -->
        <main class="content">
            <!-- Barra superior en móvil para abrir el menú lateral -->
            <div class="mobile-topbar">
                <button class="menu-toggle" id="menuToggle" type="button" aria-label="Abrir menú" aria-expanded="false"
                    aria-controls="sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="mobile-topbar-brand">
                    <span>Star Coffee</span>
                    <small>Cafetería</small>
                </div>
            </div>

            <!-- Módulo principal del dashboard con resumen administrativo -->
            <section id="moduloPanel" class="module">
                <header class="hero-panel">
                    <div class="hero-copy">
                        <p class="section-kicker">Panel de administración</p>
                        <h2>Todo el sistema de la cafetería en un solo dashboard.</h2>
                        <p>
                            Desde aquí puedes revisar el estado del turno, abrir Nuevo Pedido y navegar entre
                            las secciones preparadas para inventario, historial y configuración.
                        </p>
                        <button id="btnIrAPedidoPanel" class="primary-action" type="button">Ir a Nuevo Pedido</button>
                    </div>

                    <div class="hero-stats">
                        <article class="hero-stat">
                            <span>Acceso actual</span>
                            <strong>Admin</strong>
                            <p>Sesión protegida para la administración del negocio.</p>
                        </article>
                        <article class="hero-stat">
                            <span>Base activa</span>
                            <strong>Catálogo</strong>
                            <p>El sistema ya está enlazado con productos y categorías.</p>
                        </article>
                    </div>
                </header>

                <!-- Sección de gráficos principales del dashboard -->
                <section class="charts-grid">
                    <article class="surface-card chart-surface">
                        <div class="card-heading">
                            <div>
                                <p class="section-kicker">Gráfico general</p>
                                <h3>Productos por categoría</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="graficoCategorias"></canvas>
                        </div>
                    </article>

                    <article class="surface-card chart-surface">
                        <div class="card-heading">
                            <div>
                                <p class="section-kicker">Gráfico operativo</p>
                                <h3>Stock por producto</h3>
                            </div>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="graficoStock"></canvas>
                        </div>
                    </article>
                </section>
            </section>

            <!-- Módulo de bienvenida con indicadores rápidos -->
            <section id="moduloInicio" class="module">
                <header class="hero-panel">
                    <div class="hero-copy">
                        <p class="section-kicker">Tablero general</p>
                        <h2>Un punto de venta listo para atender rápido.</h2>
                        <p>
                            Revisa el estado del turno y entra al módulo de pedidos para operar la cafetería.
                        </p>
                        <button id="btnIrAPedido" class="primary-action" type="button">Abrir Nuevo Pedido</button>
                    </div>

                    <div class="hero-stats">
                        <article class="hero-stat">
                            <span>Pedidos activos</span>
                            <strong>18</strong>
                            <p>7 en preparación y 11 listos para entregar.</p>
                        </article>
                        <article class="hero-stat">
                            <span>Turno actual</span>
                            <strong>4 baristas</strong>
                            <p>Caja principal y mostrador trabajando en paralelo.</p>
                        </article>
                    </div>
                </header>

                <section class="overview">
                    <article class="overview-card">
                        <span>Productos Disponibles</span>
                        <strong id="inicioCantidadProductos">0</strong>
                        <p>Nuevos productos agregados recientemente.</p>
                    </article>
                    <article class="overview-card">
                        <span>Categorías</span>
                        <strong id="inicioCantidadCategorias">0</strong>
                        <p>Listas de los productos disponibles.</p>
                    </article>
                </section>

                <section class="preview-grid">
                    <article class="surface-card">
                        <div class="card-heading">
                            <div>
                                <p class="section-kicker">Vista rápida</p>
                                <h3>Categorías disponibles</h3>
                            </div>
                        </div>
                        <div id="previewCategorias" class="preview-stack"></div>
                    </article>

                    <article class="surface-card">
                        <div class="card-heading">
                            <div>
                                <p class="section-kicker">Recomendados</p>
                                <h3>Productos destacados</h3>
                            </div>
                        </div>
                        <div id="previewProductosInicio" class="preview-stack"></div>
                    </article>
                </section>
            </section>

            <!-- Módulo operativo del punto de venta -->
            <section id="moduloPedido" class="module">
                <header class="module-header">
                    <div>
                        <p class="section-kicker">Nuevo pedido</p>
                        <h2>Todos los productos</h2>
                        <p class="module-description">
                            Filtra por categoría, busca por nombre y agrega productos al carrito.
                        </p>
                    </div>

                    <div class="header-status">
                        <span id="resumenProductos" class="header-meta">Cargando catálogo...</span>
                    </div>
                </header>

                <!-- Vista principal del catálogo y carrito -->
                <div id="pantallaCatalogo" class="pedido-screen active">
                    <div class="pedido-layout">
                        <section class="catalogo-panel surface-card">
                            <div class="card-heading">
                                <div>
                                    <p class="section-kicker">Catálogo</p>
                                    <h3>Todos los productos</h3>
                                </div>
                                <div class="catalogo-search">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input id="buscadorProductos" type="search" placeholder="Buscar café, postre o bebida fría">
                                </div>
                            </div>

                            <div id="listaCategorias" class="category-strip"></div>
                            <div id="listaProductos" class="products-grid"></div>
                        </section>

                        <aside class="pedido-sidebar">
                            <section class="surface-card cart-panel">
                                <div class="card-heading">
                                    <div>
                                        <p class="section-kicker">Carrito</p>
                                        <h3>Resumen del pedido</h3>
                                    </div>
                                    <button id="btnVaciarCarrito" class="ghost-action" type="button">Vaciar</button>
                                </div>

                                <div id="carrito" class="cart-items"></div>

                                <div class="totals-panel">
                                    <div>
                                        <span>Subtotal</span>
                                        <strong id="subtotalPedido">S/ 0.00</strong>
                                    </div>
                                    <div>
                                        <span>IGV referencial</span>
                                        <strong id="igvPedido">S/ 0.00</strong>
                                    </div>
                                    <div class="total-line">
                                        <span>Total</span>
                                        <strong id="totalPedido">S/ 0.00</strong>
                                    </div>
                                </div>

                                <button id="btnIrPago" class="primary-action primary-block" type="button">Ir a pago</button>
                            </section>

                            <section class="surface-card ticket-panel">
                                <div class="card-heading">
                                    <div>
                                        <p class="section-kicker">Atención</p>
                                        <h3>Estado del mostrador</h3>
                                    </div>
                                </div>

                                <div class="ticket-metric">
                                    <span>Cafés en cola</span>
                                    <strong>06</strong>
                                </div>
                                <div class="ticket-metric">
                                    <span>Pedidos listos</span>
                                    <strong>11</strong>
                                </div>
                                <div class="ticket-metric">
                                    <span>Tiempo promedio</span>
                                    <strong>10 min</strong>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>

                <!-- Vista visual del cobro del pedido -->
                <div id="pantallaPago" class="pedido-screen">
                    <section class="payment-view surface-card">
                        <div class="payment-header">
                            <div>
                                <p class="section-kicker">Paso final</p>
                                <h3>Pantalla de pago</h3>
                                <p>Esta vista es visual y no registra pagos reales.</p>
                            </div>
                            <button id="btnVolverCatalogo" class="ghost-action" type="button">Volver al catálogo</button>
                        </div>

                        <div class="payment-grid">
                            <div class="payment-methods">
                                <article class="payment-card active" data-metodo-pago="Efectivo">
                                    <span class="payment-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                                    <div>
                                        <h4>Efectivo</h4>
                                        <p>Cambio sugerido en caja.</p>
                                    </div>
                                </article>
                                <article class="payment-card" data-metodo-pago="Tarjeta">
                                    <span class="payment-icon"><i class="fa-solid fa-credit-card"></i></span>
                                    <div>
                                        <h4>Tarjeta</h4>
                                        <p>Pago con tarjeta de crédito o débito.</p>
                                    </div>
                                </article>
                                <article class="payment-card" data-metodo-pago="QR / Yape">
                                    <span class="payment-icon"><i class="fa-solid fa-qrcode"></i></span>
                                    <div>
                                        <h4>QR / Yape</h4>
                                        <p>Pago digital para retiro rápido.</p>
                                    </div>
                                </article>
                            </div>

                            <div class="payment-summary">
                                <div class="summary-amount">
                                    <span>Total a cobrar</span>
                                    <strong id="checkoutTotal">S/ 0.00</strong>
                                </div>

                                <div class="payment-meta">
                                    <div>
                                        <span>Cliente</span>
                                        <strong>Consumidor final</strong>
                                    </div>
                                    <div>
                                        <span>Comprobante</span>
                                        <strong>Boleta referencial</strong>
                                    </div>
                                    <div>
                                        <span>Orden</span>
                                        <strong id="checkoutCantidad">0 productos</strong>
                                    </div>
                                </div>

                                <div id="checkoutItems" class="checkout-items"></div>

                                <button id="btnSimularPago" class="primary-action primary-block" type="button">Confirmar pago visual</button>
                            </div>
                        </div>
                    </section>
                </div>
            </section>

            <!-- Módulo del historial de ventas registradas -->
            <section id="moduloHistorial" class="module module-placeholder">
                <div class="surface-card inventory-card">
                    <div class="card-heading">
                        <div>
                            <p class="section-kicker">Historial</p>
                            <h3>Ventas registradas</h3>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Método</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody id="tablaHistorial">
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state compact">Aún no hay ventas registradas.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Módulo reservado para control de inventario -->
            <section id="moduloInventario" class="module module-placeholder">
                <!-- Sección del CRUD de productos para controlar inventario -->
                <div class="surface-card inventory-card">
                    <div class="card-heading">
                        <div>
                            <p class="section-kicker">Inventario</p>
                            <h3>Gestión de productos</h3>
                        </div>
                        <button id="btnNuevoProducto" class="primary-action" type="button">Nuevo producto</button>
                    </div>

                    <div class="table-wrap">
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Stock</th>
                                    <th>Precio</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tablaInventario">
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state compact">Cargando productos del inventario...</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Módulo de configuración general del local -->
            <section id="moduloAjustes" class="module module-placeholder">
                <div class="settings-stack">
                    <section class="surface-card settings-card">
                        <div class="settings-header">
                            <h3>Datos del Local</h3>
                        </div>
                        <form id="formDatosLocal" class="modal-form">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="ajusteNombreLocal">Nombre del Local</label>
                                    <input id="ajusteNombreLocal" type="text" required>
                                </div>
                                <div class="form-field">
                                    <label for="ajusteCorreoLocal">Correo del Local</label>
                                    <input id="ajusteCorreoLocal" type="email" required>
                                </div>
                                <div class="form-field">
                                    <label for="ajusteDireccionLocal">Dirección</label>
                                    <input id="ajusteDireccionLocal" type="text" required>
                                </div>
                                <div class="form-field">
                                    <label for="ajusteTelefonoLocal">Teléfono</label>
                                    <input id="ajusteTelefonoLocal" type="text" required>
                                </div>
                            </div>
                            <div class="modal-actions">
                                <button class="primary-action" type="submit">Guardar cambios</button>
                            </div>
                        </form>
                    </section>

                    <section class="surface-card settings-card">
                        <div class="settings-header">
                            <h3>Año Activo del Local</h3>
                        </div>
                        <form id="formAnioLocal" class="modal-form">
                            <div class="form-grid single-grid">
                                <div class="form-field">
                                    <label for="ajusteAnioLocal">Año Activo Vigente</label>
                                    <select id="ajusteAnioLocal"></select>
                                </div>
                            </div>
                            <div class="modal-actions">
                                <button class="primary-action" type="submit">Guardar cambios</button>
                            </div>
                        </form>
                    </section>

                    <section class="surface-card settings-card">
                        <div class="settings-header">
                            <h3>Cambiar Contraseña del Administrador</h3>
                        </div>
                        <form id="formPasswordAdmin" class="modal-form">
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="passwordActualAdmin">Contraseña Actual</label>
                                    <input id="passwordActualAdmin" type="password" required>
                                </div>
                                <div class="form-field">
                                    <label for="passwordNuevaAdmin">Nueva Contraseña</label>
                                    <input id="passwordNuevaAdmin" type="password" required>
                                </div>
                                <div class="form-field">
                                    <label for="passwordConfirmarAdmin">Confirmar Contraseña</label>
                                    <input id="passwordConfirmarAdmin" type="password" required>
                                </div>
                            </div>
                            <div class="modal-actions">
                                <button class="primary-action" type="submit">Cambiar contraseña</button>
                            </div>
                        </form>
                    </section>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal del CRUD para crear y editar productos -->
    <div id="modalProducto" class="modal-overlay">
        <div class="modal-panel">
            <div class="modal-header">
                <div>
                    <p class="section-kicker">Inventario</p>
                    <h3 id="modalProductoTitulo">Nuevo producto</h3>
                </div>
                <button id="btnCerrarModalProducto" class="ghost-action" type="button">Cerrar</button>
            </div>

            <form id="formProducto" class="modal-form">
                <input type="hidden" id="productoId">

                <div class="form-grid">
                    <div class="form-field">
                        <label for="productoNombre">Nombre del producto</label>
                        <input id="productoNombre" type="text" required>
                    </div>

                    <div class="form-field">
                        <label for="productoCategoria">Categoría</label>
                        <select id="productoCategoria" required></select>
                    </div>

                    <div class="form-field">
                        <label for="productoStock">Stock</label>
                        <input id="productoStock" type="number" min="0" required>
                    </div>

                    <div class="form-field">
                        <label for="productoPrecio">Precio</label>
                        <input id="productoPrecio" type="number" min="0.01" step="0.01" required>
                    </div>
                </div>

                <div class="modal-actions">
                    <button class="ghost-action" id="btnCancelarModalProducto" type="button">Cancelar</button>
                    <button class="primary-action" type="submit">Guardar producto</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/dashboard.js"></script>
    <script src="js/login.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
        crossorigin="anonymous"></script>
</body>
</html>
