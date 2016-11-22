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

    return $response;
}


/**
* Chequear si ya esta logeado
*
*/
function checkIfIsLogged(){

    $response = [];

    require_once("init_vars.php");

    $cpsession = captiveportal_isip_logged($clientip);

    if(!empty($cpsession)){
        $responsedata = array();
        $voucher = $cpsession['username'];
        $timecredit = voucher_auth($voucher);
        $responsedata['timeCredit'] = $timecredit;
        $totalTimeOfVoucher = $cpsession['session_timeout'] / 60;
        $consumedVoucherTime = $totalTimeOfVoucher - $timecredit;
        $responsedata['activationTime'] = date("H:i - Y/m/d", strtotime('-' . strval($consumedVoucherTime) . ' minutes'));
        $responsedata['expiryTime'] = date('H:i - Y/m/d', strtotime('+' . strval($timecredit) . ' minutes'));
        $responsedata['clientIp'] = $cpsession['ip'];
        $responsedata['clientMac'] = $cpsession['mac'];
        $responsedata['sessionId'] = $cpsession['sessionid'];
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

    if(isset($data->voucher)) {
        $voucher = trim($data->voucher);

        $timecredit = voucher_auth($voucher);

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
                $responsedata['timeCredit'] = $timecredit;
                $responsedata['activationTime'] = date("H:i - Y/m/d");
                $responsedata['expiryTime'] = date('H:i - Y/m/d', strtotime('+' . strval($timecredit) . ' minutes'));
                $responsedata['clientIp'] = $clientip;
                $responsedata['clientMac'] = $clientmac;
                $responsedata['redirUrl'] = $redirurl;
                $responsedata['sessionId'] = $sessionid;
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

    $response = [];

    require_once("init_vars.php");

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

