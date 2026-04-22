$(document).ready(function () {
    // Elementos principales del formulario de acceso.
    const loginForm = $("#loginForm");
    const passwordInput = $("#txtPassword");
    const btnLogin = $("#btnLogin");

    // Alterna visibilidad de la contraseña en el login.
    $("#togglePassword").on("click", function () {
        const showPassword = passwordInput.attr("type") === "password";
        passwordInput.attr("type", showPassword ? "text" : "password");
        $(this).toggleClass("fa-eye fa-eye-slash");
    });

    // Envío AJAX del login del administrador.
    loginForm.on("submit", function (event) {
        event.preventDefault();

        $.ajax({
            url: "php/login.php",
            type: "POST",
            dataType: "json",
            cache: false,
            data: {
                user: $("#txtUsuario").val().trim(),
                pass: passwordInput.val()
            },
            beforeSend: function () {
                btnLogin.prop("disabled", true);
                Swal.fire({
                    title: "Validando acceso",
                    text: "Estamos comprobando las credenciales del administrador.",
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
            },
            success: function (respuesta) {
                if (!respuesta.exito) {
                    Swal.fire({
                        icon: "error",
                        title: "Acceso denegado",
                        text: respuesta.mensaje || "El usuario o la contraseña son incorrectos.",
                        confirmButtonColor: "#b14d32"
                    });
                    return;
                }

                Swal.fire({
                    icon: "success",
                    title: "Bienvenido",
                    text: "Redirigiendo al panel administrativo.",
                    showConfirmButton: false,
                    timer: 1400
                }).then(() => {
                    window.location.href = respuesta.redirect || "dashboard.php";
                });
            },
            error: function (xhr) {
                const mensaje = xhr.responseJSON?.mensaje || "No se pudo conectar con el servidor.";

                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: mensaje,
                    confirmButtonColor: "#8e5739"
                });
            },
            complete: function () {
                btnLogin.prop("disabled", false);
            }
        });
    });
});
