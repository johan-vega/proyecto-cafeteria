const state = {
    productos: [],
    categorias: [],
    categoriaActiva: "TODOS",
    busqueda: "",
    carrito: []
};

const IGV_RATE = 0.18;

document.addEventListener("DOMContentLoaded", () => {
    inicializarSidebar();
    inicializarNavegacion();
    inicializarAccionesPedido();
    mostrarModulo("moduloPedido");
    cargarProductos();
});

function inicializarSidebar() {
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
    const navItems = document.querySelectorAll(".sidebar-nav li");
    const navButtons = document.querySelectorAll(".sidebar-link[data-module]");
    const btnIrAPedido = document.getElementById("btnIrAPedido");

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
}

function inicializarAccionesPedido() {
    const buscador = document.getElementById("buscadorProductos");
    const btnVaciar = document.getElementById("btnVaciarCarrito");
    const btnIrPago = document.getElementById("btnIrPago");
    const btnVolverCatalogo = document.getElementById("btnVolverCatalogo");
    const btnSimularPago = document.getElementById("btnSimularPago");

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

    if (btnSimularPago) {
        btnSimularPago.addEventListener("click", () => {
            Swal.fire(
                "Pago visual confirmado",
                "La interfaz de pago se mostro correctamente. No se registro ningun cobro real.",
                "success"
            );
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

    if (moduloId === "moduloEstadisticas") { // Cargar gráficos al mostrar el módulo de estadísticas
    setTimeout(() => {
        cargarGrafico();
        cargarGraficoCategorias();
        cargarGraficoTop();
    }, 100);
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
    const listaProductos = document.getElementById("listaProductos");

    try {
        if (listaProductos) {
            listaProductos.innerHTML = '<div class="empty-state">Cargando productos desde la base de datos...</div>';
        }

        const response = await fetch("php/api_productos.php");
        const data = await response.json();

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
        renderInventario();  //Renderizar inventario al cargar productos
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
    const producto = state.productos.find((item) => item.id === productoId);

    if (!producto) {
        return;
    }

    const existente = state.carrito.find((item) => item.id === productoId);

    if (existente) {
        existente.cantidad += 1;
    } else {
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

    if (!item) {
        return;
    }

    item.cantidad += 1;
    renderCarrito();
}

function renderCarrito() {
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
    const cantidadProductos = document.getElementById("inicioCantidadProductos");
    const cantidadCategorias = document.getElementById("inicioCantidadCategorias");
    const ticketPromedio = document.getElementById("inicioTicketPromedio");
    const previewCategorias = document.getElementById("previewCategorias");
    const previewProductos = document.getElementById("previewProductos");
    const precioPromedio =
        state.productos.reduce((acc, producto) => acc + producto.precio, 0) / (state.productos.length || 1);

    if (cantidadProductos) {
        cantidadProductos.textContent = state.productos.length;
    }

    if (cantidadCategorias) {
        cantidadCategorias.textContent = state.categorias.length;
    }

    if (ticketPromedio) {
        ticketPromedio.textContent = formatCurrency(precioPromedio);
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

    if (previewProductos) {
        previewProductos.innerHTML = state.productos
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

/*inventario*/

function renderInventario() {
    const tabla = document.getElementById("tablaInventario");
    const total = document.getElementById("invTotal");
    const bajo = document.getElementById("invBajo");
    const agotado = document.getElementById("invAgotado");

    if (!tabla) return;

    let countBajo = 0;
    let countAgotado = 0;

    tabla.innerHTML = state.productos.map(p => {
        let estado = "OK";
        let color = "green";

        if (p.stock <= 5 && p.stock > 0) {
            estado = "BAJO";
            color = "orange";
            countBajo++;
        }

        if (p.stock === 0) {
            estado = "AGOTADO";
            color = "red";
            countAgotado++;
        }

        return `
            <tr>
                <td>${p.nombre}</td>
                <td>${p.stock}</td>
                <td style="color:${color}; font-weight:bold;">${estado}</td>
            </tr>
        `;
    }).join("");

    total.textContent = state.productos.length;
    bajo.textContent = countBajo;
    agotado.textContent = countAgotado;
}

//estadisticas

let grafico = null; // <-- IMPORTANTE
let graficoCategorias = null;
let graficoTop = null;

function cargarGrafico() {

    const canvas = document.getElementById("graficoVentas");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");

    // destruir gráfico anterior si existe
    if (grafico) {
        grafico.destroy();
    }

    grafico = new Chart(ctx, {
        type: "line",
        data: {
            labels: ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"],
            datasets: [{
                label: "Ventas (S/)",
                data: [50, 80, 65, 90, 120],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

function cargarGraficoCategorias() {
    const canvas = document.getElementById("graficoCategorias");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");

    new Chart(ctx, {
        type: "doughnut",
        data: {
            labels: ["Café", "Postres", "Bebidas"],
            datasets: [{
                data: [120, 90, 60]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

function cargarGraficoTop() {
    const canvas = document.getElementById("graficoTop");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");

    new Chart(ctx, {
        type: "bar",
        data: {
            labels: ["Latte", "Capuccino", "Brownie"],
            datasets: [{
                label: "Ventas",
                data: [50, 40, 30]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}
