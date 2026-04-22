const state = {
    productos: [],
    categorias: [],
    categoriaActiva: "TODOS",
    busqueda: "",
    carrito: [],
    metodoPagoSeleccionado: "Efectivo",
    historial: [],
    inventarioCategorias: [],
    charts: {
        categorias: null,
        stock: null
    }
};

const IGV_RATE = 0.18;

document.addEventListener("DOMContentLoaded", () => {
    // Inicialización general del POS y sus módulos.
    inicializarSidebar();
    inicializarNavegacion();
    inicializarAccionesPedido();
    inicializarInventario();
    inicializarSesion();
    inicializarAjustes();
    mostrarModulo("moduloPanel");
    cargarProductos();
    cargarHistorial();
    cargarAjustes();
});

function inicializarSidebar() {
    // Controla apertura y cierre del menú lateral en móvil.
    const body = document.body;
    const menuToggle = document.getElementById("menuToggle");
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");
    const mobileBreakpoint = 960;

    if (!menuToggle || !sidebarBackdrop) {
        return;
    }

    const closeSidebar = () => {
        body.classList.remove("sidebar-open");
        menuToggle.setAttribute("aria-expanded", "false");
    };

    const openSidebar = () => {
        body.classList.add("sidebar-open");
        menuToggle.setAttribute("aria-expanded", "true");
    };

    menuToggle.addEventListener("click", () => {
        if (body.classList.contains("sidebar-open")) {
            closeSidebar();
            return;
        }

        openSidebar();
    });

    sidebarBackdrop.addEventListener("click", closeSidebar);

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeSidebar();
        }
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth > mobileBreakpoint) {
            closeSidebar();
        }
    });
}

function inicializarNavegacion() {
    // Gestiona el cambio entre módulos desde la barra lateral.
    const navItems = document.querySelectorAll(".sidebar-nav li");
    const navButtons = document.querySelectorAll(".sidebar-link[data-module]");
    const btnIrAPedido = document.getElementById("btnIrAPedido");
    const btnIrAPedidoPanel = document.getElementById("btnIrAPedidoPanel");

    navButtons.forEach((button) => {
        button.addEventListener("click", () => {
            navItems.forEach((item) => item.classList.remove("active"));
            button.closest("li")?.classList.add("active");
            mostrarModulo(button.dataset.module);
            document.body.classList.remove("sidebar-open");
        });
    });

    if (btnIrAPedido) {
        btnIrAPedido.addEventListener("click", () => {
            const pedidoButton = document.querySelector('.sidebar-link[data-module="moduloPedido"]');
            pedidoButton?.click();
        });
    }

    if (btnIrAPedidoPanel) {
        btnIrAPedidoPanel.addEventListener("click", () => {
            const pedidoButton = document.querySelector('.sidebar-link[data-module="moduloPedido"]');
            pedidoButton?.click();
        });
    }
}

function inicializarAccionesPedido() {
    // Eventos principales del flujo de carrito y pago.
    const buscador = document.getElementById("buscadorProductos");
    const btnVaciar = document.getElementById("btnVaciarCarrito");
    const btnIrPago = document.getElementById("btnIrPago");
    const btnVolverCatalogo = document.getElementById("btnVolverCatalogo");
    const btnSimularPago = document.getElementById("btnSimularPago");
    const paymentCards = document.querySelectorAll(".payment-card[data-metodo-pago]");

    if (buscador) {
        buscador.addEventListener("input", (event) => {
            state.busqueda = event.target.value.trim().toLowerCase();
            renderProductos();
        });
    }

    if (btnVaciar) {
        btnVaciar.addEventListener("click", () => {
            state.carrito = [];
            renderCarrito();
        });
    }

    if (btnIrPago) {
        btnIrPago.addEventListener("click", () => {
            if (!state.carrito.length) {
                Swal.fire("Carrito vacio", "Agrega al menos un producto antes de pasar a pago.", "info");
                return;
            }

            cambiarPantallaPedido("pago");
        });
    }

    if (btnVolverCatalogo) {
        btnVolverCatalogo.addEventListener("click", () => cambiarPantallaPedido("catalogo"));
    }

    // Permite seleccionar visualmente el método de pago del checkout.
    paymentCards.forEach((card) => {
        card.addEventListener("click", () => {
            paymentCards.forEach((item) => item.classList.remove("active"));
            card.classList.add("active");
            state.metodoPagoSeleccionado = card.dataset.metodoPago || "Efectivo";
        });
    });

    if (btnSimularPago) {
        btnSimularPago.addEventListener("click", async () => {
            if (!state.carrito.length) {
                Swal.fire("Carrito vacío", "Agrega productos antes de confirmar una venta.", "info");
                return;
            }

            try {
                const response = await fetch("php/crud_pedidos.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json; charset=UTF-8"
                    },
                    body: JSON.stringify({
                        accion: "registrar_venta",
                        metodo_pago: state.metodoPagoSeleccionado,
                        cliente: "Consumidor final",
                        comprobante: "Boleta referencial",
                        items: state.carrito
                    })
                });
                const data = await response.json();

                if (!response.ok || !data.exito) {
                    throw new Error(data?.error || "No se pudo registrar la venta.");
                }

                state.carrito = [];
                renderCarrito();
                cambiarPantallaPedido("catalogo");
                await cargarProductos();
                await cargarHistorial();

                Swal.fire(
                    "Venta registrada",
                    "La venta se guardó correctamente en el historial.",
                    "success"
                );
            } catch (error) {
                Swal.fire("Error", error.message, "error");
            }
        });
    }
}

function mostrarModulo(moduloId) {
    const modules = document.querySelectorAll(".module");

    modules.forEach((module) => {
        module.classList.toggle("active", module.id === moduloId);
    });

    if (moduloId === "moduloPedido") {
        cambiarPantallaPedido("catalogo");
    }
}

function cambiarPantallaPedido(pantalla) {
    const pantallaCatalogo = document.getElementById("pantallaCatalogo");
    const pantallaPago = document.getElementById("pantallaPago");

    if (!pantallaCatalogo || !pantallaPago) {
        return;
    }

    const mostrarPago = pantalla === "pago";
    pantallaCatalogo.classList.toggle("active", !mostrarPago);
    pantallaPago.classList.toggle("active", mostrarPago);
    renderCheckout();
}

async function cargarProductos() {
    // Carga el catálogo desde PHP para renderizar el POS.
    const listaProductos = document.getElementById("listaProductos");

    try {
        if (listaProductos) {
            listaProductos.innerHTML = '<div class="empty-state">Cargando productos desde la base de datos...</div>';
        }

        const response = await fetch("php/crud_inventario.php?accion=listar");
        const data = await response.json();

        if (response.status === 401) {
            window.location.href = "index.php";
            return;
        }

        if (!response.ok || !Array.isArray(data)) {
            throw new Error(data?.error || "No se pudo cargar el catalogo.");
        }

        state.productos = data.map((producto) => ({
            id: Number(producto.id_producto),
            nombre: producto.nombre_producto,
            precio: Number(producto.precio),
            stock: Number(producto.stock),
            categoriaId: producto.id_categoria,
            categoriaNombre: producto.nombre_categoria
        }));

        state.categorias = Array.from(
            new Map(
                state.productos.map((producto) => [
                    producto.categoriaId,
                    { id: producto.categoriaId, nombre: producto.categoriaNombre }
                ])
            ).values()
        );

        renderCategorias();
        renderProductos();
        renderCarrito();
        renderInicio();
        renderGraficos();
        renderTablaInventario();
    } catch (error) {
        if (listaProductos) {
            listaProductos.innerHTML = '<div class="empty-state error-state">No se pudieron cargar los productos.</div>';
        }

        const resumen = document.getElementById("resumenProductos");
        if (resumen) {
            resumen.textContent = "Error al leer productos";
        }

        Swal.fire("Error", error.message || "No se pudieron cargar los productos.", "error");
    }
}

function renderCategorias() {
    // Dibuja los filtros de categorías disponibles.
    const contenedor = document.getElementById("listaCategorias");

    if (!contenedor) {
        return;
    }

    const categorias = [{ id: "TODOS", nombre: "Todos los productos" }, ...state.categorias];

    contenedor.innerHTML = categorias
        .map((categoria) => `
            <button
                type="button"
                class="category-pill ${state.categoriaActiva === categoria.id ? "active" : ""}"
                data-categoria="${categoria.id}">
                ${categoria.nombre}
            </button>
        `)
        .join("");

    contenedor.querySelectorAll(".category-pill").forEach((button) => {
        button.addEventListener("click", () => {
            state.categoriaActiva = button.dataset.categoria;
            renderCategorias();
            renderProductos();
        });
    });
}

function getProductosFiltrados() {
    return state.productos.filter((producto) => {
        const coincideCategoria =
            state.categoriaActiva === "TODOS" || producto.categoriaId === state.categoriaActiva;
        const coincideBusqueda =
            !state.busqueda ||
            producto.nombre.toLowerCase().includes(state.busqueda) ||
            producto.categoriaNombre.toLowerCase().includes(state.busqueda);

        return coincideCategoria && coincideBusqueda;
    });
}

function renderProductos() {
    // Muestra las tarjetas del catálogo según filtros activos.
    const contenedor = document.getElementById("listaProductos");
    const resumen = document.getElementById("resumenProductos");
    const productos = getProductosFiltrados();

    if (!contenedor) {
        return;
    }

    if (resumen) {
        resumen.textContent = `${productos.length} producto(s) visibles en el catalogo`;
    }

    if (!productos.length) {
        contenedor.innerHTML = `
            <div class="empty-state">
                No hay productos para esa categoria o busqueda.
            </div>
        `;
        return;
    }

    contenedor.innerHTML = productos
        .map((producto) => `
            <article class="product-card">
                <div class="product-top">
                    <span class="product-tag">${producto.categoriaNombre}</span>
                    <span class="product-stock">Stock ${producto.stock}</span>
                </div>
                <div class="product-icon">
                    <i class="fa-solid ${resolverIcono(producto.categoriaNombre)}"></i>
                </div>
                <h4>${producto.nombre}</h4>
                <p>Ideal para venta rapida en mostrador.</p>
                <div class="product-bottom">
                    <strong>${formatCurrency(producto.precio)}</strong>
                    <button class="product-action" type="button" data-id="${producto.id}">
                        Agregar
                    </button>
                </div>
            </article>
        `)
        .join("");

    contenedor.querySelectorAll(".product-action").forEach((button) => {
        button.addEventListener("click", () => agregarCarrito(Number(button.dataset.id)));
    });
}

function agregarCarrito(productoId) {
    // Agrega productos al carrito respetando el stock disponible.
    const producto = state.productos.find((item) => item.id === productoId);

    if (!producto) {
        return;
    }

    const existente = state.carrito.find((item) => item.id === productoId);

    if (existente) {
        if (existente.cantidad >= producto.stock) {
            Swal.fire("Stock insuficiente", `Solo hay ${producto.stock} unidad(es) disponibles de ${producto.nombre}.`, "warning");
            return;
        }

        existente.cantidad += 1;
    } else {
        if (producto.stock <= 0) {
            Swal.fire("Sin stock", `${producto.nombre} no tiene unidades disponibles.`, "warning");
            return;
        }

        state.carrito.push({
            id: producto.id,
            nombre: producto.nombre,
            precio: producto.precio,
            categoriaNombre: producto.categoriaNombre,
            cantidad: 1
        });
    }

    renderCarrito();
}

function disminuirCantidad(productoId) {
    const item = state.carrito.find((producto) => producto.id === productoId);

    if (!item) {
        return;
    }

    item.cantidad -= 1;

    if (item.cantidad <= 0) {
        state.carrito = state.carrito.filter((producto) => producto.id !== productoId);
    }

    renderCarrito();
}

function aumentarCantidad(productoId) {
    const item = state.carrito.find((producto) => producto.id === productoId);
    const producto = state.productos.find((producto) => producto.id === productoId);

    if (!item || !producto) {
        return;
    }

    if (item.cantidad >= producto.stock) {
        Swal.fire("Stock insuficiente", `Solo hay ${producto.stock} unidad(es) disponibles de ${producto.nombre}.`, "warning");
        return;
    }

    item.cantidad += 1;
    renderCarrito();
}

function inicializarSesion() {
    // Cierra la sesión del administrador desde el dashboard.
    const logoutButtons = [document.getElementById("btnLogout"), document.getElementById("btnLogoutAside")].filter(Boolean);

    logoutButtons.forEach((button) => {
        button.addEventListener("click", async () => {
            const result = await Swal.fire({
                title: "Cerrar sesión",
                text: "¿Deseas salir del panel administrativo?",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Sí, salir",
                cancelButtonText: "Cancelar",
                confirmButtonColor: "#8e5739"
            });

            if (!result.isConfirmed) {
                return;
            }

            try {
                await fetch("php/logout.php", {
                    method: "POST",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    }
                });
            } finally {
                window.location.href = "index.php";
            }
        });
    });
}

function inicializarInventario() {
    // Inicializa eventos del CRUD de productos en inventario.
    const btnNuevoProducto = document.getElementById("btnNuevoProducto");
    const btnCerrarModal = document.getElementById("btnCerrarModalProducto");
    const btnCancelarModal = document.getElementById("btnCancelarModalProducto");
    const formProducto = document.getElementById("formProducto");

    btnNuevoProducto?.addEventListener("click", () => abrirModalProducto());
    btnCerrarModal?.addEventListener("click", cerrarModalProducto);
    btnCancelarModal?.addEventListener("click", cerrarModalProducto);

    formProducto?.addEventListener("submit", guardarProducto);
}

async function cargarCategoriasInventario() {
    // Obtiene las categorías disponibles para el formulario de productos.
    const response = await fetch("php/crud_inventario.php?accion=categorias");
    const data = await response.json();

    if (!response.ok) {
        throw new Error(data?.error || "No se pudieron cargar las categorías.");
    }

    state.inventarioCategorias = data;
    const select = document.getElementById("productoCategoria");

    if (!select) {
        return;
    }

    select.innerHTML = state.inventarioCategorias
        .map((categoria) => `<option value="${categoria.id_categoria}">${categoria.nombre_categoria}</option>`)
        .join("");
}

async function cargarHistorial() {
    // Carga el historial real de ventas para la tabla administrativa.
    const tabla = document.getElementById("tablaHistorial");

    if (!tabla) {
        return;
    }

    try {
        const response = await fetch("php/crud_historial.php?accion=listar");
        const data = await response.json();

        if (!response.ok || !Array.isArray(data)) {
            throw new Error(data?.error || "No se pudo cargar el historial.");
        }

        state.historial = data;
        renderTablaHistorial();
    } catch (error) {
        tabla.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="empty-state compact">${error.message}</div>
                </td>
            </tr>
        `;
    }
}

function renderTablaHistorial() {
    // Dibuja la tabla del historial de ventas con su detalle resumido.
    const tabla = document.getElementById("tablaHistorial");

    if (!tabla) {
        return;
    }

    if (!state.historial.length) {
        tabla.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="empty-state compact">Aún no hay ventas registradas.</div>
                </td>
            </tr>
        `;
        return;
    }

    tabla.innerHTML = state.historial
        .map((venta) => {
            const detalle = (venta.detalle || [])
                .map((item) => `${item.CANTIDAD}x ${item.NOMBRE_PRODUCTO}`)
                .join(", ");

            return `
                <tr>
                    <td>${venta.ID_VENTA}</td>
                    <td>${formatDateTime(venta.FECHA_VENTA)}</td>
                    <td>${venta.METODO_PAGO}</td>
                    <td>${venta.CANTIDAD_ITEMS}</td>
                    <td>${formatCurrency(Number(venta.TOTAL))}</td>
                    <td>${detalle || "Sin detalle"}</td>
                </tr>
            `;
        })
        .join("");
}

function inicializarAjustes() {
    // Inicializa eventos de los formularios de configuración del local.
    document.getElementById("formDatosLocal")?.addEventListener("submit", guardarDatosLocal);
    document.getElementById("formAnioLocal")?.addEventListener("submit", guardarAnioLocal);
    document.getElementById("formPasswordAdmin")?.addEventListener("submit", cambiarPasswordAdmin);
}

async function cargarAjustes() {
    // Carga la configuración actual del local dentro del módulo Ajustes.
    const selectAnio = document.getElementById("ajusteAnioLocal");

    if (selectAnio) {
        selectAnio.innerHTML = Array.from({ length: 8 }, (_, index) => 2024 + index)
            .map((anio) => `<option value="${anio}">${anio}</option>`)
            .join("");
    }

    try {
        const response = await fetch("php/crud_ajustes.php?accion=obtener");
        const data = await response.json();

        if (!response.ok || !data) {
            throw new Error(data?.error || "No se pudieron cargar los ajustes.");
        }

        document.getElementById("ajusteNombreLocal").value = data.nombre_local || "";
        document.getElementById("ajusteCorreoLocal").value = data.correo_local || "";
        document.getElementById("ajusteDireccionLocal").value = data.direccion_local || "";
        document.getElementById("ajusteTelefonoLocal").value = data.telefono_local || "";
        if (selectAnio) {
            selectAnio.value = data.anio_local || "2026";
        }
    } catch (error) {
        console.error(error);
    }
}

async function guardarDatosLocal(event) {
    // Guarda los datos generales del local.
    event.preventDefault();

    try {
        const response = await fetch("php/crud_ajustes.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams({
                accion: "guardar_local",
                nombre_local: document.getElementById("ajusteNombreLocal").value.trim(),
                correo_local: document.getElementById("ajusteCorreoLocal").value.trim(),
                direccion_local: document.getElementById("ajusteDireccionLocal").value.trim(),
                telefono_local: document.getElementById("ajusteTelefonoLocal").value.trim()
            }).toString()
        });
        const data = await response.json();

        if (!response.ok || !data.exito) {
            throw new Error(data?.error || "No se pudieron guardar los datos del local.");
        }

        Swal.fire("Correcto", data.mensaje, "success");
    } catch (error) {
        Swal.fire("Error", error.message, "error");
    }
}

async function guardarAnioLocal(event) {
    // Guarda el año activo del local.
    event.preventDefault();

    try {
        const response = await fetch("php/crud_ajustes.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams({
                accion: "guardar_anio",
                anio_local: document.getElementById("ajusteAnioLocal").value
            }).toString()
        });
        const data = await response.json();

        if (!response.ok || !data.exito) {
            throw new Error(data?.error || "No se pudo guardar el año activo.");
        }

        Swal.fire("Correcto", data.mensaje, "success");
    } catch (error) {
        Swal.fire("Error", error.message, "error");
    }
}

async function cambiarPasswordAdmin(event) {
    // Cambia la contraseña del administrador desde Ajustes.
    event.preventDefault();

    try {
        const response = await fetch("php/crud_ajustes.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams({
                accion: "cambiar_password",
                password_actual: document.getElementById("passwordActualAdmin").value,
                password_nueva: document.getElementById("passwordNuevaAdmin").value,
                password_confirmar: document.getElementById("passwordConfirmarAdmin").value
            }).toString()
        });
        const data = await response.json();

        if (!response.ok || !data.exito) {
            throw new Error(data?.error || "No se pudo cambiar la contraseña.");
        }

        document.getElementById("formPasswordAdmin").reset();
        Swal.fire("Correcto", data.mensaje, "success");
    } catch (error) {
        Swal.fire("Error", error.message, "error");
    }
}

async function abrirModalProducto(producto = null) {
    // Abre el modal para crear o editar un producto del inventario.
    const modal = document.getElementById("modalProducto");
    const titulo = document.getElementById("modalProductoTitulo");
    const inputId = document.getElementById("productoId");
    const inputNombre = document.getElementById("productoNombre");
    const inputCategoria = document.getElementById("productoCategoria");
    const inputStock = document.getElementById("productoStock");
    const inputPrecio = document.getElementById("productoPrecio");

    if (!modal || !titulo || !inputId || !inputNombre || !inputCategoria || !inputStock || !inputPrecio) {
        return;
    }

    if (!state.inventarioCategorias.length) {
        try {
            await cargarCategoriasInventario();
        } catch (error) {
            Swal.fire("Error", error.message, "error");
            return;
        }
    }

    titulo.textContent = producto ? "Editar producto" : "Nuevo producto";
    inputId.value = producto?.id ?? "";
    inputNombre.value = producto?.nombre ?? "";
    inputCategoria.value = producto?.categoriaId ?? state.inventarioCategorias[0]?.id_categoria ?? "";
    inputStock.value = producto?.stock ?? 0;
    inputPrecio.value = producto?.precio ?? "";

    modal.classList.add("active");
}

function cerrarModalProducto() {
    // Cierra el modal del CRUD de productos.
    const modal = document.getElementById("modalProducto");
    const form = document.getElementById("formProducto");

    modal?.classList.remove("active");
    form?.reset();
}

function renderTablaInventario() {
    // Dibuja la tabla del inventario con acciones de editar y eliminar.
    const tabla = document.getElementById("tablaInventario");

    if (!tabla) {
        return;
    }

    if (!state.productos.length) {
        tabla.innerHTML = `
            <tr>
                <td colspan="6">
                    <div class="empty-state compact">No hay productos registrados.</div>
                </td>
            </tr>
        `;
        return;
    }

    tabla.innerHTML = state.productos
        .map((producto) => `
            <tr>
                <td>${producto.id}</td>
                <td>${producto.nombre}</td>
                <td>${producto.categoriaNombre}</td>
                <td>${producto.stock}</td>
                <td>${formatCurrency(producto.precio)}</td>
                <td>
                    <div class="table-actions">
                        <button class="ghost-action btn-editar-producto" type="button" data-id="${producto.id}">Editar</button>
                        <button class="ghost-action btn-eliminar-producto" type="button" data-id="${producto.id}">Eliminar</button>
                    </div>
                </td>
            </tr>
        `)
        .join("");

    tabla.querySelectorAll(".btn-editar-producto").forEach((button) => {
        button.addEventListener("click", () => {
            const producto = state.productos.find((item) => item.id === Number(button.dataset.id));
            abrirModalProducto(producto);
        });
    });

    tabla.querySelectorAll(".btn-eliminar-producto").forEach((button) => {
        button.addEventListener("click", () => eliminarProducto(Number(button.dataset.id)));
    });
}

async function guardarProducto(event) {
    // Guarda un producto nuevo o editado desde el modal del inventario.
    event.preventDefault();

    const id = document.getElementById("productoId").value.trim();
    const payload = new URLSearchParams({
        accion: id ? "editar" : "crear",
        id_producto: id,
        nombre_producto: document.getElementById("productoNombre").value.trim(),
        id_categoria: document.getElementById("productoCategoria").value,
        stock: document.getElementById("productoStock").value,
        precio: document.getElementById("productoPrecio").value
    });

    try {
        const response = await fetch("php/crud_inventario.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: payload.toString()
        });
        const data = await response.json();

        if (!response.ok || !data.exito) {
            throw new Error(data?.error || data?.mensaje || "No se pudo guardar el producto.");
        }

        cerrarModalProducto();
        await cargarProductos();
        Swal.fire("Correcto", data.mensaje, "success");
    } catch (error) {
        Swal.fire("Error", error.message, "error");
    }
}

async function eliminarProducto(productoId) {
    // Elimina un producto del inventario después de confirmación.
    const result = await Swal.fire({
        title: "Eliminar producto",
        text: "Esta acción quitará el producto del inventario.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Eliminar",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#b14d32"
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const response = await fetch("php/crud_inventario.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
            },
            body: new URLSearchParams({
                accion: "eliminar",
                id_producto: productoId
            }).toString()
        });
        const data = await response.json();

        if (!response.ok || !data.exito) {
            throw new Error(data?.error || data?.mensaje || "No se pudo eliminar el producto.");
        }

        await cargarProductos();
        Swal.fire("Eliminado", data.mensaje, "success");
    } catch (error) {
        Swal.fire("Error", error.message, "error");
    }
}

function renderCarrito() {
    // Renderiza el resumen del carrito y actualiza totales.
    const contenedor = document.getElementById("carrito");
    const btnIrPago = document.getElementById("btnIrPago");

    if (!contenedor) {
        return;
    }

    if (!state.carrito.length) {
        contenedor.innerHTML = `
            <div class="empty-state compact">
                Aun no agregas productos al carrito.
            </div>
        `;
    } else {
        contenedor.innerHTML = state.carrito
            .map((item) => `
                <article class="cart-item">
                    <div class="cart-copy">
                        <h4>${item.nombre}</h4>
                        <p>${item.categoriaNombre}</p>
                    </div>
                    <div class="cart-controls">
                        <button type="button" class="qty-btn" data-action="minus" data-id="${item.id}">-</button>
                        <span>${item.cantidad}</span>
                        <button type="button" class="qty-btn" data-action="plus" data-id="${item.id}">+</button>
                    </div>
                    <strong>${formatCurrency(item.precio * item.cantidad)}</strong>
                </article>
            `)
            .join("");

        contenedor.querySelectorAll(".qty-btn").forEach((button) => {
            button.addEventListener("click", () => {
                const productoId = Number(button.dataset.id);
                if (button.dataset.action === "minus") {
                    disminuirCantidad(productoId);
                    return;
                }

                aumentarCantidad(productoId);
            });
        });
    }

    actualizarTotales();
    renderCheckout();

    if (btnIrPago) {
        btnIrPago.disabled = !state.carrito.length;
    }
}

function actualizarTotales() {
    const subtotal = state.carrito.reduce((acc, item) => acc + item.precio * item.cantidad, 0);
    const igv = subtotal * IGV_RATE;
    const total = subtotal + igv;

    const subtotalEl = document.getElementById("subtotalPedido");
    const igvEl = document.getElementById("igvPedido");
    const totalEl = document.getElementById("totalPedido");

    if (subtotalEl) {
        subtotalEl.textContent = formatCurrency(subtotal);
    }

    if (igvEl) {
        igvEl.textContent = formatCurrency(igv);
    }

    if (totalEl) {
        totalEl.textContent = formatCurrency(total);
    }
}

function renderCheckout() {
    // Refleja en la pantalla de pago el contenido del carrito.
    const checkoutItems = document.getElementById("checkoutItems");
    const checkoutTotal = document.getElementById("checkoutTotal");
    const checkoutCantidad = document.getElementById("checkoutCantidad");
    const subtotal = state.carrito.reduce((acc, item) => acc + item.precio * item.cantidad, 0);
    const total = subtotal + subtotal * IGV_RATE;
    const cantidad = state.carrito.reduce((acc, item) => acc + item.cantidad, 0);

    if (checkoutTotal) {
        checkoutTotal.textContent = formatCurrency(total);
    }

    if (checkoutCantidad) {
        checkoutCantidad.textContent = `${cantidad} producto(s)`;
    }

    if (!checkoutItems) {
        return;
    }

    if (!state.carrito.length) {
        checkoutItems.innerHTML = '<div class="empty-state compact">No hay productos pendientes para cobrar.</div>';
        return;
    }

    checkoutItems.innerHTML = state.carrito
        .map((item) => `
            <div class="checkout-item">
                <span>${item.cantidad} x ${item.nombre}</span>
                <strong>${formatCurrency(item.precio * item.cantidad)}</strong>
            </div>
        `)
        .join("");
}

function renderInicio() {
    // Actualiza los indicadores del módulo de inicio.
    const cantidadProductos = document.getElementById("inicioCantidadProductos");
    const cantidadCategorias = document.getElementById("inicioCantidadCategorias");
    const panelCantidadProductos = document.getElementById("panelCantidadProductos");
    const panelCantidadCategorias = document.getElementById("panelCantidadCategorias");
    const panelPrecioPromedio = document.getElementById("panelPrecioPromedio");
    const previewCategorias = document.getElementById("previewCategorias");
    const previewProductosInicio = document.getElementById("previewProductosInicio");
    const precioPromedio =
        state.productos.reduce((acc, producto) => acc + producto.precio, 0) / (state.productos.length || 1);

    if (cantidadProductos) {
        cantidadProductos.textContent = state.productos.length;
    }

    if (cantidadCategorias) {
        cantidadCategorias.textContent = state.categorias.length;
    }

    if (panelCantidadProductos) {
        panelCantidadProductos.textContent = state.productos.length;
    }

    if (panelCantidadCategorias) {
        panelCantidadCategorias.textContent = state.categorias.length;
    }


    if (panelPrecioPromedio) {
        panelPrecioPromedio.textContent = formatCurrency(precioPromedio);
    }

    if (previewCategorias) {
        previewCategorias.innerHTML = state.categorias
            .map((categoria) => {
                const cantidad = state.productos.filter((producto) => producto.categoriaId === categoria.id).length;
                return `
                    <div class="preview-item">
                        <div>
                            <strong>${categoria.nombre}</strong>
                            <p>${cantidad} producto(s) en catalogo</p>
                        </div>
                        <span>${cantidad}</span>
                    </div>
                `;
            })
            .join("");
    }

    if (previewProductosInicio) {
        previewProductosInicio.innerHTML = state.productos
            .slice(0, 3)
            .map((producto) => `
                <div class="preview-item">
                    <div>
                        <strong>${producto.nombre}</strong>
                        <p>${producto.categoriaNombre}</p>
                    </div>
                    <span>${formatCurrency(producto.precio)}</span>
                </div>
            `)
            .join("");
    }
}

function renderGraficos() {
    // Renderiza los gráficos principales del dashboard administrativo.
    const canvasCategorias = document.getElementById("graficoCategorias");
    const canvasStock = document.getElementById("graficoStock");

    if (!canvasCategorias || !canvasStock || typeof Chart === "undefined") {
        return;
    }

    const categoriasResumen = state.categorias.map((categoria) => ({
        nombre: categoria.nombre,
        cantidad: state.productos.filter((producto) => producto.categoriaId === categoria.id).length
    }));

    const productosOrdenados = [...state.productos]
        .sort((a, b) => b.stock - a.stock)
        .slice(0, 6);

    if (state.charts.categorias) {
        state.charts.categorias.destroy();
    }

    if (state.charts.stock) {
        state.charts.stock.destroy();
    }

    state.charts.categorias = new Chart(canvasCategorias, {
        type: "doughnut",
        data: {
            labels: categoriasResumen.map((item) => item.nombre),
            datasets: [{
                data: categoriasResumen.map((item) => item.cantidad),
                backgroundColor: ["#8d4e32", "#d79b63", "#6e9f84", "#c66d4f", "#5f2f1e"],
                borderColor: "#fffaf5",
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: "bottom"
                }
            }
        }
    });

    state.charts.stock = new Chart(canvasStock, {
        type: "bar",
        data: {
            labels: productosOrdenados.map((producto) => producto.nombre),
            datasets: [{
                label: "Stock disponible",
                data: productosOrdenados.map((producto) => producto.stock),
                backgroundColor: ["#8d4e32", "#b56f4b", "#d79b63", "#7f9c6c", "#c66d4f", "#5f2f1e"],
                borderRadius: 12
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

function resolverIcono(categoriaNombre) {
    const nombre = categoriaNombre.toLowerCase();

    if (nombre.includes("bebida") || nombre.includes("cafe")) {
        return "fa-mug-hot";
    }

    if (nombre.includes("postre")) {
        return "fa-cookie-bite";
    }

    return "fa-bag-shopping";
}

function formatCurrency(value) {
    return new Intl.NumberFormat("es-PE", {
        style: "currency",
        currency: "PEN",
        minimumFractionDigits: 2
    }).format(value || 0);
}

function formatDateTime(value) {
    // Formatea fecha y hora para mostrar ventas en historial.
    return new Intl.DateTimeFormat("es-PE", {
        dateStyle: "short",
        timeStyle: "short"
    }).format(new Date(value));
}
