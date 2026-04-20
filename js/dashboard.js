document.addEventListener("DOMContentLoaded", function () {
    const body = document.body;
    const sidebar = document.getElementById("sidebar");
    const menuToggle = document.getElementById("menuToggle");
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");
    const mobileBreakpoint = 960;

    if (!sidebar || !menuToggle || !sidebarBackdrop) {
        return;
    }

    const closeSidebar = function () {
        body.classList.remove("sidebar-open");
        menuToggle.setAttribute("aria-expanded", "false");
    };

    const openSidebar = function () {
        body.classList.add("sidebar-open");
        menuToggle.setAttribute("aria-expanded", "true");
    };

    menuToggle.addEventListener("click", function () {
        if (body.classList.contains("sidebar-open")) {
            closeSidebar();
            return;
        }

        openSidebar();
    });

    sidebarBackdrop.addEventListener("click", closeSidebar);

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeSidebar();
        }
    });

    window.addEventListener("resize", function () {
        if (window.innerWidth > mobileBreakpoint) {
            closeSidebar();
        }
    });
});


/*--pedido--*/
let carrito = [];

const links = document.querySelectorAll(".sidebar-link");
const moduloInicio = document.getElementById("moduloInicio");
const moduloPedido = document.getElementById("moduloPedido");

// 👉 IR A NUEVO PEDIDO
if (links[1]) {
    links[1].addEventListener("click", function () {
        if(moduloInicio) moduloInicio.style.display = "none";
        if(moduloPedido) moduloPedido.style.display = "block";
        cargarProductos();
    });
}

// --VOLVER A INICIO
if (links[0]) {
    links[0].addEventListener("click", function () {
        if(moduloPedido) moduloPedido.style.display = "none";
        if(moduloInicio) moduloInicio.style.display = "block";
    });
}


//--- CARGAR PRODUCTOS 
function cargarProductos() {
    fetch("php/api_productos.php")
        .then(res => res.json())
        .then(data => {

            let html = "";

            data.forEach(p => {
                html += `
                    <div class="col-md-4 mb-3">
                        <div class="card p-2">
                            <h5>${p.NOMBRE_PRODUCTO}</h5>
                            <p>S/ ${p.PRECIO}</p>
                            <button class="btn btn-primary btn-sm btnAgregar"
                                data-id="${p.ID_PRODUCTO}"
                                data-nombre="${p.NOMBRE_PRODUCTO}"
                                data-precio="${p.PRECIO}">
                                Agregar
                            </button>
                        </div>
                    </div>
                `;
            });

            const cont = document.getElementById("listaProductos");
            if (cont) cont.innerHTML = html;

            // eventos botones agregar
            document.querySelectorAll(".btnAgregar").forEach(btn => {
                btn.addEventListener("click", function () {
                    agregarCarrito(
                        this.dataset.id,
                        this.dataset.nombre,
                        parseFloat(this.dataset.precio)
                    );
                });
            });

        });
}


// -- CARRITO 
function agregarCarrito(id, nombre, precio) {
    carrito.push({ id, nombre, precio });
    renderCarrito();
}

function renderCarrito() {
    let html = "";
    let total = 0;

    carrito.forEach((item, index) => {
        total += item.precio;

        html += `
            <li class="list-group-item d-flex justify-content-between">
                ${item.nombre}
                <span>
                    S/ ${item.precio}
                    <button class="btn btn-danger btn-sm btnEliminar" data-index="${index}">X</button>
                </span>
            </li>
        `;
    });

    const lista = document.getElementById("carrito");
    const totalEl = document.getElementById("total");

    if (lista) lista.innerHTML = html;
    if (totalEl) totalEl.textContent = total;

// eventos eliminar
    document.querySelectorAll(".btnEliminar").forEach(btn => {
        btn.addEventListener("click", function () {
            eliminarItem(this.dataset.index);
        });
    });
}

function eliminarItem(index) {
    carrito.splice(index, 1);
    renderCarrito();
}


// --GUARDAR PEDIDO --
const btnGuardar = document.getElementById("btnGuardarPedido");

if (btnGuardar) {
    btnGuardar.addEventListener("click", function () {

        if (carrito.length === 0) {
            alert("Carrito vacío");
            return;
        }

        fetch("php/api_pedido.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ carrito })
        })
        .then(res => res.json())
        .then(() => {
            alert("Pedido guardado");
            carrito = [];
            renderCarrito();
        });

    });
}


