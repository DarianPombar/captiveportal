/**
 * @fileoverview scripts, este archivo es rige todo el codigo javascript del la app
 * @version 2.0
 * @author Darian E. Martinez Pombar <dmpombar90@nauta.cu>
 *
 * Historia
 * v2.0 - El script fue modificado para adaptarlo a mis necesidades
 * v1.0 - fue la primera version
 * ----
 * La primera version fue escrita por Yurisbel
 */


/** Variables globales */
var timerId = null;

/** Llama a la funcion init cuando la pagina web este lista */
$(document).ready(init);

/**
 * Pone los eventos onclick de los botones y chequea si ya fue autentificado
 */
function init() {

    checkNotificationSystemAccess(); //for system notification
    //set onclick events to buttons
    // $("#btnActivate").on("click", activate);
    $("#btnActivate").on("click", sendVoucherToServer);
    $("#btnCheckVoucher").on("click", verifyVoucher);
    // $("#btnBack").on("click", back);
    // $("#btnAuthenticate").on("click", sendVoucherToServer);
    $("#btnDisconnect").on("click", disconnect);
    // $("#btnGenerateNewVouchersAuthenticated").on('click', generateVouchersAutentication);
    // $("#btnGenerateNewVouchers").on('click', generateNewVouchers);
    // $("#generateVouchersModal").on("show.bs.modal", generateVouchersModalShow);
    // $("#generateVouchersModal").on("shown.bs.modal", generateVouchersModalShown);
    // $("#generateVouchersModal").on("hidden.bs.modal", generateVouchersModalHide);

    // $("#formGenerateVouchers").on("submit", formGenerateVouchers);
    $("#formGenerateVouchers").validationEngine("");

    //check if this client is authenticated

    initcheck();
    // checkIfIsAuthenticated();
}

function checkNotificationSystemAccess() {

    if("Notification" in window){
        if((Notification.permission === "default") || (Notification.permission === "denied")){
            toastr.info("Para nosotros es vital poder notificarle cuando su tiempo se esta agotando. <br>Por favor permita que nuestro sitio le muestre notificaciones. <br> Atentamente. Apolo's Bot.", "Importante");
            window.setTimeout(requestSystemNotificationPermission, 5000);
        }
    }

}


function requestSystemNotificationPermission(){
    Notification.requestPermission(checkNotificationAccessByUserAction)
}

function checkNotificationAccessByUserAction(status) {
    if (status == "denied") {
        toastr.warning("Usted decidio no recibir notificaciones. Necesita habilitarla manualmente.", "Lo sentimos");
    }
}


/**
 * Efecto de transicion de dos div
 * @param outgoing {Object} El div que se va en el efecto de transicion
 * @param incoming {Object} El div que entra en el efecto de trasicion
 */
function transition(outgoing, incoming) {
    outgoing.slideUp("fast", "linear", function () {
        incoming.slideDown("fast", "linear");
    }).attr("hidden");
}

/**
 * Evento onclick del boton activar internet
 */
function activate() {
    transition($("#connected"), $("#activateInternet"));
}

/**
 * Mostrar mensaje de confirmacion antes de cerrar la pagina
 * @param e {event} Evento del metodo
 * @returns {string}
 */
function confirmCloseWindow(e) {
    var e = e || window.event;
    // For IE and Firefox
    if (e) {
        e.returnValue = 'Esta seguro que desea cerrar esta ventana?';
    }
    // For Safari
    return 'Esta seguro que desea cerrar esta ventana?';
}

/**
 * Captura el valor de un parametro en la url
 * @param name {String} Nombre del parametro a capturar
 * @returns {string} Valor del parametro capturado
 */
function getParameterInURLByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(location.search);
    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

function getAbsolutePath(){
    var loc = window.location;
    var pathName = loc.pathname.substring(0, loc.pathname.lastIndexOf('/')+1);
    return loc.href.substring(0, loc.href.length - ((loc.pathname + loc.search+loc.hash).length - pathName.length));
}

function initcheck() {

    var request = {
        action: "initCheck"
    };

    $.ajax({
        url: 'backend/helper.php',
        data: {
            request: JSON.stringify(request)
        },
        type: 'POST',
        dataType: 'json',
        success: function (json) {
            if (json.success) {
                checkIfIsAuthenticated();
            } else {
                toastr.error(json.message,"Información");
            }
        },
        error: function (xhr, status) {
            toastr.info('Tuvimos un pequeño problema, por favor intentelo nuevamente.', "Mmmmm.. eso fue raro");
        }
    });
}

/**
 * Chequea con el servidor si ya estaba autentificado, el chequeo se hace mediante ajax y en caso de ser afirmativo
 * muestra la vista de saldo.
 */
function checkIfIsAuthenticated() {
    var request = {
        action: "checkIfIsLogged"
    };
    $.ajax({
        url: 'backend/helper.php',
        data: {
            request: JSON.stringify(request)
        },
        type: 'POST',
        dataType: 'json',
        success: function (json) {
            if (json.success) {
                localStorage.setItem("CAPTIVE_PORTAL_SESSION_ID", json.data.sessionId);
                // Displaying the complete object for the session.
                console.log(json);
                $("#loading").hide();
                $("#autenticated").show();
                // $("#activation_time").val(json.data.activationTime);
                // $("#time_credit").val(json.data.timeCredit+ ":00");
                // $("#expiry_time").val(json.data.expiryTime);
                $("#time_credit").text(json.data.timeCredit + ":00");
                timerId = window.setInterval(downTimer, 1000);
                window.onbeforeunload = confirmCloseWindow;
            } else {
                $("#loading").hide();
                $("#connected").show();
                window.onbeforeunload = null;
            }
        },
        error: function (xhr, status) {
            toastr.info('Tuvimos un pequeño problema, por favor intentelo nuevamente.', "Mmmmm.. eso fue raro");
        }
    });
}

/**
 * Evento onclick del boton Atras
 */
function back() {
    transition($("#activateInternet"), $("#connected"));
}

/**
 * Chequea si el voucher introducido es valido y lo envia al servidor mediante ajax y espera la respuesta, en caso de
 * que al voucher le quede tiempo cambia la pagina y te pone cuanto queda, sino da un mensaje de error.
 */
function sendVoucherToServer() {
    var voucher = $('#auth_voucher').val();
    $('#btnActivate').attr('disabled');
    if (voucher == '') {
        toastr.warning("Necesitamos su voucher para identificarle primero.", "Ya casi puede navegar");
    } else {
        // var zone = getParameterInURLByName('zone');
        var redirectUrl = getParameterInURLByName('redirurl');
        var request = {
            action: 'checkVoucherForTraffic',
            data: {
                voucher: voucher,
            }
        };
        $.ajax({
            url: 'backend/helper.php',
            data: {
                request: JSON.stringify(request),
                redirurl: redirectUrl //solo para esta version, depues borrar
                // zone: zone,
                // voucher: voucher
            },
            type: 'POST',
            dataType: 'json',
            success: function (json) {
                if (json.success) {
                    transition($("#connected"), $("#autenticated"));
                    // $("#activateInternet").html($("#autenticated").html());
                    // $("#activateInternet").hide();
                    // $("#autenticated").show();
                    // $("#sessionid").val(json.data.session_id);
                    // localStorage.setItem("CAPTIVE_PORTAL_ZONE", json.data.zone);
                    localStorage.setItem("CAPTIVE_PORTAL_SESSION_ID", json.data.sessionId);

                    // To-Do: Calculate the offset time between the system clock and the server time.
                    // var acurrateTime = funcion() {
                    //     let systemTime = new Date().now();
                    //     let serverTime = json.data.expiryTime;
                    //     let offset = function() {
                    //         return systemTime - serverTime;
                    //     };

                    //     console.log(offset);
                    // };

                    $("#auth_voucher").val("");
                    $("#activation_time").val(json.data.activationTime);
                    $("#time_credit").text(json.data.timeCredit + ":00");
                    $("#expiry_time").val(json.data.expiryTime);
                    timerId = window.setInterval(downTimer, 1000);
                    window.onbeforeunload = confirmCloseWindow;
                    if (json.data.redirUrl != null && json.data.redirUrl != '') {
                        window.open(json.data.redirUrl, "_blank");
                    }
                } else {
                    toastr.error(json.message, "Error de autentificación");
                }
            },
            error: function (xhr, status) {
                toastr.info('Tuvimos un pequeño problema, por favor intentelo nuevamente.', "Mmmmm.. eso fue raro");
            }
            // complete: function(xhr, status){
            // alert('Peticion realizada');
            // }
        });
        $('#btnActivate').removeAttr('disabled');        
    }
}


function verifyVoucher() {
    var voucher = $('#auth_voucher').val();
    $('#btnCheckVoucher').attr('disabled');
    if (voucher == '') {
        toastr.info("Introduzca su voucher","Antes de verificar");
    } else {
        var request = {
            action: 'verifyVoucher',
            data: {
                voucher: voucher
            }
        };
        $.ajax({
            url: 'backend/helper.php',
            data: {
                request: JSON.stringify(request)
            },
            type: 'post',
            dataType: 'json',
            success: function (json) {
                if (json.success) {
                    // $('#publickey').val(json.data.public.replace(/\\n/g, '\n'));
                    // $('#privatekey').val(json.data.private.replace(/\\n/g, '\n'));
                    toastr.success("Este voucher cuenta con " + json.data.timeCredit + " minutos. <br> Vamos a usarlos ahora!", "Tick tock!");
                } else {
                    toastr.error(json.message);
                }
            },
            error: function (xhr, status) {
                toastr.info('Tuvimos un pequeño problema, por favor intentelo nuevamente.', "Mmmmm.. eso fue raro");
            }
        });
        $('#btnCheckVoucher').removeAttr('disabled');
    }
}

/**
 * Desconecta del servidor mediante una peticion ajax y muestra la vista de bienvenido
 */
function disconnect() {
    // var zone = localStorage.getItem("CAPTIVE_PORTAL_ZONE");
    var sessionId = localStorage.getItem("CAPTIVE_PORTAL_SESSION_ID");
    if (sessionId == '') {
        toastr.error("Error");
    } else {
        var request = {
            action: 'disconnectClient',
            data: {
                sessionId: sessionId
            }
        };
        $.ajax({
            url: 'backend/helper.php',
            data: {
                request: JSON.stringify(request)
            },
            type: 'POST',
            dataType: 'json',
            success: function (json) {
                if (json.success) {
                    // $("#activateInternet").html($("#connected").html());
                    // $("#autenticated").hide();
                    // $("#connected").show();
                    window.clearInterval(timerId);
                    transition($("#autenticated"), $("#connected"));
                    window.onbeforeunload = null;
                    toastr.success(json.message);
                    // localStorage.removeItem("CAPTIVE_PORTAL_ZONE");
                    // localStorage.removeItem("CAPTIVE_PORTAL_SESSION_ID");
                } else {
                    toastr.error(json.message, "Error de desconexión");
                }
            },
            error: function (xhr, status) {
                toastr.info('Tuvimos un pequeño problema, por favor intentelo nuevamente.', "Mmmmm.. eso fue raro");
            }
            // complete: function(xhr, status){
            // alert('Peticion realizada');
            // }
        });
    }
}

function downTimer() {
    var timeCredit = $("#time_credit").html().split(":");

    var seconds = parseInt(timeCredit[1]);
    var minutes = parseInt(timeCredit[0]);

    if ((minutes == 0) && (seconds == 0)) {
        window.clearInterval(timerId);
        transition($("#autenticated"), $("#connected"));
        window.onbeforeunload = null;
        if(document.visibilityState == "visible"){
            toastr.success("Puede adquirir mas tiempo con el administrador", "Internet consumido");
        }else{
            if("Notification" in window){
                if(Notification.permission === "granted"){

                    var options = {
                        body: "Puede adquirir mas tiempo con el administrador"
                        // icon: getAbsolutePath()+"favicon.png" el icono no funciona, si lo pongo no lanza la notificacion
                    };
                    var systemNotification = new Notification("Internet consumido", options);
                    // var systemNotification = new Notification("Titulo","Se le ha acabado el tiempo de navegacion. Gracias por utilizar el servicio y esperamos que vuelva pronto.");
                }
            }
        }
    }else{
        seconds--;

        if (seconds == -1) {
            seconds = 59;
            minutes--;
        }

        if (seconds > 9) {
            $("#time_credit").text(minutes + ":" + seconds);
        } else {
            $("#time_credit").text(minutes + ":0" + seconds);
        }
        // Time description
        if (minutes > 1) {
            $("#time_class").text("minutos");
        } else if (minutes == 1) {
            $("#time_class").text("minuto");
        } else {
            $("#time_class").text("segundos");
        }
    }


}

/**
 * Autentifica mediante una peticion ajax con un usuario y una contrasena, en caso de ser positivo muestra la vista
 * de generar los vouchers
 */
function generateVouchersAutentication() {
    var user = $('#txtUser').val();
    var password = $('#txtPassword').val();
    if (user != '' && password != '') {
        var request = {
            action: 'authenticate',
            data: {
                username: user,
                password: password
            }
        };
        $.ajax({
            url: 'backend/helper.php',
            data: {
                request: JSON.stringify(request)
            },
            type: 'POST',
            dataType: 'json',
            success: function (json) {
                if (json.success) {
                    transition($('#generateVoucherAuthentication'), $('#generateVoucherAuthenticated'));
                    $('#btnGenerateNewVouchersAuthenticated').hide();
                    $('#btnGenerateNewVouchers').show();
                    $('#roll').focus();
                } else {
                    alert(json.message);
                    $('#txtUser').val("");
                    $('#txtPassword').val("");
                    $('#txtUser').focus();
                }
            },
            error: function (xhr, status) {
                alert('Disculpe, existio un problema');
            }
            // complete: function(xhr, status){
            // alert('Peticion realizada');
            // }
        });
    } else {
        alert('Introduce un usuario y una contrasena.');
        $('#txtUser').focus();
    }
}

/**
 * Envia al servidor los 4 parametros de generacion de vouchers para generar un paquete de vouchers mediante una peticion ajax
 */
function generateNewVouchers() {
    var roll = $('#roll').val();
    var minutes = $('#minutes').val();
    var count = $('#count').val();
    var desc = $('#desc').val();
    if (roll != '' && minutes != '' && count != '' && desc != '') {
        // var zone = getParameterInURLByName('zone');
        // if (zone == "") {
        //     zone = localStorage.getItem("CAPTIVE_PORTAL_ZONE");
        // }
        var request = {
            action: 'generateNewVoucherPackage',
            data: {
                roll: roll,
                minutes: minutes,
                count: count,
                desc: desc
            }
        };
        $.ajax({
            url: 'backend/helper.php',
            data: {
                request: JSON.stringify(request)
            },
            type: 'POST',
            dataType: 'json',
            success: function (json) {
                if (json.success) {
                    alert(json.message);
                    $('#roll').val("");
                    $('#minutes').val("");
                    $('#count').val("");
                    $('#desc').val("");
                } else {
                    alert(json.message);
                }
            },
            error: function (xhr, status) {
                alert('Disculpe, existio un problema');
            }
            // complete: function(xhr, status){
            // alert('Peticion realizada');
            // }
        });
    } else {
        alert('Debe llenar todos los campos.');
        $('#roll').focus();
    }
}

/**
 * Evento on click del boton Autentificar
 */
function generateVouchersModalShow() {
    $('#generateVoucherAuthenticated').hide();
    $('#btnGenerateNewVouchers').hide();
}

/**
 * Evento onshow del modal
 */
function generateVouchersModalShown() {
    $('#txtUser').focus();
}

/**
 * Evento onhide del modal
 */
function generateVouchersModalHide() {
    $('#generateVoucherAuthenticated').hide();
    $('#generateVoucherAuthentication').show();
    $('#btnGenerateNewVouchers').hide();
    $('#btnGenerateNewVouchersAuthenticated').show();
    $('#txtUser').val('');
    $('#txtPassword').val('');
    $('#roll').val('');
    $('#minutes').val('');
    $('#count').val('');
    $('#desc').val('');
}

/**
 * Evento onsubmit del formulario para generar vouchers
 * @param e {Event} Evento del metodo
 */
function formGenerateVouchers(e) {
    e.preventDefault();
    if ($('#btnGenerateNewVouchersAuthenticated').is(":visible")) {
        if ($("#formGenerateVouchers").validationEngine("validate")) {
            generateVouchersAutentication();
        }
    } else {
        if ($("#formGenerateVouchers").validationEngine("validate")) {
            generateNewVouchers();
        }
    }
}

/**
 * Envia al servidor una peticion ajax para obtener los datos de los paquetes de vouchers
 */
function getVoucherPackagesData() {
    var request = {
        action: 'getVoucherPackagesData'
    };
    $.ajax({
        url: 'backend/helper.php',
        data: {
            request: JSON.stringify(request)
        },
        type: 'post',
        dataType: 'json',
        success: function (json) {
            if (json.success) {
                alert(json.message);
            } else {
                alert(json.message);
            }
        },
        error: function (xhr, status) {
            alert('Disculpe, existio un problema');
        }
    });
}

/**
 * Envia al servidor una peticion ajax para generar nuevas llaves para generar vouchers
 */
function generateKeyPar() {
    var request = {
        action: 'generateKeyPar'
    };
    $.ajax({
        url: 'backend/helper.php',
        data: {
            request: JSON.stringify(request)
        },
        type: 'post',
        dataType: 'json',
        success: function (json) {
            if (json.success) {
                // $('#publickey').val(json.data.public.replace(/\\n/g, '\n'));
                // $('#privatekey').val(json.data.private.replace(/\\n/g, '\n'));
                alert(json.message);
            } else {
                alert(json.message);
            }
        },
        error: function (xhr, status) {
            alert('Disculpe, existio un problema');
        }
    });
}

/**
 * Envia al servidor una peticion ajax con las llaves nuevas para generar vouchers
 */
function saveKeyPar() {
    var request = {
        action: 'saveKeyPar',
        data: {
            privateKey: "llave privada",
            publicKey: "llave publica"
        }
    };
    $.ajax({
        url: 'backend/helper.php',
        data: {
            request: JSON.stringify(request)
        },
        type: 'post',
        dataType: 'json',
        success: function (json) {
            if (json.success) {
                // $('#publickey').val(json.data.public.replace(/\\n/g, '\n'));
                // $('#privatekey').val(json.data.private.replace(/\\n/g, '\n'));
                alert(json.message);
            } else {
                alert(json.message);
            }
        },
        error: function (xhr, status) {
            alert('Disculpe, existio un problema');
        }
    });
}

/**
 * Envia al servidor una peticion ajax para saber cuales son las llaves que se usan actualmente en la generacion de vouchers
 */
function getKeyPar() {
    var request = {
        action: 'getKeyPar'
    };
    $.ajax({
        url: 'backend/helper.php',
        data: {
            request: JSON.stringify(request)
        },
        type: 'post',
        dataType: 'json',
        success: function (json) {
            if (json.success) {
                // $('#publickey').val(json.data.public.replace(/\\n/g, '\n'));
                // $('#privatekey').val(json.data.private.replace(/\\n/g, '\n'));
                alert(json.message);
            } else {
                alert(json.message);
            }
        },
        error: function (xhr, status) {
            alert('Disculpe, existio un problema');
        }
    });
}

// Toastr position object data.
toastr.options = {
  "closeButton": false,
  "debug": false,
  "newestOnTop": false,
  "progressBar": false,
  "positionClass": "toast-bottom-right",
  "preventDuplicates": false,
  "onclick": null,
  "showDuration": "300",
  "hideDuration": "1000",
  "timeOut": "5000",
  "extendedTimeOut": "1000",
  "showEasing": "swing",
  "hideEasing": "linear",
  "showMethod": "fadeIn",
  "hideMethod": "fadeOut"
}