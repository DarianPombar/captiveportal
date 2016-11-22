<?php
/**
 * Created by PhpStorm.
 * User: darian
 * Date: 28/10/16
 * Time: 16:23
 * Este fichero es para chequear si esta autentificar, autentificar y desconectar a un cliente
 */

function initCheck(){

    $response = [];
    $response['success'] = true;
    $response['message'] = "Puede navegar sin ningun problema";

    require_once("init_vars.php"); //incluir todas la variables para posteriormente trabajar con ellas

    if (empty($cpcfg)) { //si la configuración de la zona no existe
        log_error("Submission to captiveportal with unknown parameter zone: " . htmlspecialchars($cpzone));
        $response['success'] = false;
        $response['message'] = "Submission to captiveportal with unknown parameter zone: " . htmlspecialchars($cpzone);
        return $response;
    }

    if (!$clientip) { //si no existe una direccion ip del cliente
        log_error("Zone: {$cpzone} - Captive portal could not determine client's IP address.");
        $response['success'] = false;
        $response['message'] = "No puede determinar la direccion ip del cliente.";
        return $response;
    }

    ///* find MAC address for client */
    if ($macfilter || $passthrumac) {
        $tmpres = pfSense_ip_to_mac($clientip);
        if (!is_array($tmpres)) {
            /* unable to find MAC address - shouldn't happen! - bail out */
            captiveportal_logportalauth("unauthenticated", "noclientmac", $clientip, "ERROR");
            log_error("Zone: {$cpzone} - Captive portal could not determine client's MAC address.  Disable MAC address filtering in captive portal if you do not need this functionality.");
            $response['success'] = false;
            $response['message'] = "El portal cautivo no pudo determinar la direccion mac del cliente.";
            return $response;
        }
        $clientmac = $tmpres['macaddr'];
        unset($tmpres);
    }

    /** Chequear si la mac del cliente esta bloqueada */
    if ($macfilter && $clientmac && captiveportal_blocked_mac($clientmac)) {
        captiveportal_logportalauth($clientmac, $clientmac, $clientip, "Blocked MAC address");
        if (!empty($cpcfg['blockedmacsurl'])) {
            $response['success'] = false;
            $response['message'] = "Esta direccion mac ha sido bloqueada, por favor contacte con el administrador.";
            return $response;
        } else {
            $response['success'] = false;
            $response['message'] = "Esta direccion mac ha sido bloqueada, por favor contacte con el administrador.";
            return $response;
        }
    }

    return $response;
}


/**
* Chequear si ya esta logeado
*
*/
function checkIfIsLogged(){

    $response = [];

    require_once("init_vars.php");

    if (!$clientip) { //si no existe una direccion ip del cliente
        log_error("Zone: {$cpzone} - Captive portal could not determine client's IP address.");
        $response['success'] = false;
        $response['message'] = "No puede determinar la direccion ip del cliente.";
        return $response;
    }

    $cpsession = captiveportal_isip_logged($clientip);

    if(!empty($cpsession)){
        $responsedata = array();
        $timecredit = $cpsession['session_timeout'] / 60;
        $responsedata['time_credit'] = $timecredit;
        $responsedata['activation_time'] = date("H:i:s - Y/m/d");
        $responsedata['expiry_time'] = date('H:i:s - Y/m/d', strtotime('+' . strval($timecredit) . ' minutes'));
        $responsedata['client_mac'] = $cpsession['mac'];
        $response['success'] = true;
        $response['data'] = $responsedata;
        $response['message'] = "Esta autenticado.";
    }else{
        $response['success'] = false;
        $response['message'] = "No esta autenticado.";
    }

    return $response;
}


/**
* Chequea el voucher y si es valido permite el trafico
*
*/
function checkVoucherForTraffic($data){

    $response = [];

    require_once("init_vars.php");

//    die(var_dump($cpzone));

    $cpzone = "wireless";

//    die(var_dump($g));

    if (empty($cpcfg)) { //si la zona no existe
        log_error("Submission to captiveportal with unknown parameter zone: " . htmlspecialchars($cpzone));
        $response['success'] = false;
        $response['message'] = "Submission to captiveportal with unknown parameter zone: " . htmlspecialchars($cpzone);
        return $response;
    }

    if (!$clientip) { //si no existe una direccion ip del cliente
        log_error("Zone: {$cpzone} - Captive portal could not determine client's IP address.");
        $response['success'] = false;
        $response['message'] = "No puede determinar la direccion ip del cliente.";
        return $response;
    }

    ///* find MAC address for client */
    if ($macfilter || $passthrumac) {
        $tmpres = pfSense_ip_to_mac($clientip);
        if (!is_array($tmpres)) {
            /* unable to find MAC address - shouldn't happen! - bail out */
            captiveportal_logportalauth("unauthenticated", "noclientmac", $clientip, "ERROR");
            log_error("Zone: {$cpzone} - Captive portal could not determine client's MAC address.  Disable MAC address filtering in captive portal if you do not need this functionality.");
            $response['success'] = false;
            $response['message'] = "El portal cautivo no pudo determinar la direccion mac del cliente.";
            return $response;
        }
        $clientmac = $tmpres['macaddr'];
        unset($tmpres);
    }

    if(isset($data->voucher)) {
        $voucher = trim($data->voucher);

        $timecredit = voucher_auth($voucher);
//        die(var_dump($timecredit));

        if ($timecredit > 0) {
            $a_vouchers = preg_split("/[\t\n\r ]+/s", $voucher);
            $voucher = $a_vouchers[0];
            $attr = array(
                'voucher' => 1,
                'session_timeout' => $timecredit * 60,
                'session_terminate_time' => 0);
            $sessionid = portal_allow($clientip, $clientmac, $voucher, null, $attr);
            if ($sessionid) { // YES: user is good for $timecredit minutes.
                captiveportal_logportalauth($voucher, $clientmac, $clientip, "Voucher login good for $timecredit min.");
                $responsedata = array();
                // $responseData['zone'] = $cpzone;
                $responsedata['time_credit'] = $timecredit;
                $responsedata['activation_time'] = date("H:i:s - Y/m/d");
                $responsedata['expiry_time'] = date('H:i:s - Y/m/d', strtotime('+' . strval($timecredit) . ' minutes'));
                $responsedata['client_mac'] = $clientmac;
                $responsedata['redirurl'] = $redirurl;
                $responsedata['session_id'] = $sessionid;
                $response['success'] = true;
                $response['data'] = $responsedata;
                $response['message'] = "Autentificacion satisfactoria.";
            } else {
                $response['success'] = false;
                $response['message'] = "Voucher actualmente en uso desde otro ordenador.";
            }
        } else if ($timecredit == -1) {
            captiveportal_logportalauth($voucher, $clientmac, $clientip, "FAILURE", "voucher expired");
            $response['success'] = false;
            $response['message'] = "Voucher expirado.";
        } else {
            captiveportal_logportalauth($voucher, $clientmac, $clientip, "FAILURE");
            $response['success'] = false;
            $response['message'] = "Voucher invalido.";
            $response['voucher'] = $voucher;
        }
    }else{
        $response['success'] = false;
        $response['message'] = "Falta el parametro voucher.";
    }

    return $response;
}


/**
* Desconecta al cliente que esta por esa session
*
*/
function disconnectClient($data){
    
    $sessionId = SQLite3::escapeString($data->sessionId);
    if($sessionId != "") {
        captiveportal_disconnect_client($sessionId);
        $response['success'] = true;
        $response['message'] = "Se ha desconectado satisfactoriamente.";
    }else{
        $response['success'] = false;
        $response['message'] = "Problemas con el parametro sessionId.";
    }
     
    return $response;
}

