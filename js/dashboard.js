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
