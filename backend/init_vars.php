<?php
/**
 * init_vars.php
 * Este fichero es para inicializar toda la configuración en varibales para utilizarlas desde otros ficheros
 */

global $config, $g, $cpzone, $cpzoneid, $passthrumac;

$clientip = $_SERVER['REMOTE_ADDR'];

if (!$clientip) { //si no existe una direccion ip del cliente
    log_error("Zone: {$cpzone} - Captive portal could not determine client's IP address.");
    $response['success'] = false;
    $response['message'] = "No puede determinar la direccion ip del cliente.";
    return $response;
}


$captivePortals = $config['captiveportal'];
if (count($captivePortals) == 0) {
    $response['success'] = false;
    $response['message'] = "No existe ningun portal cautivo en la configuracion.";
    return $response;
}

//$cpzone = "wireless";
$cpzone = "";

foreach ($captivePortals as $key1 => $captivePortal) {
    $cpInterface = $captivePortal['interface'];
    $interfaces = $config['interfaces'];
    $finder = false;
    foreach ($interfaces as $key2 => $interface) {
        if($cpInterface == $key2){
            $subnet = get_interface_ip($key2)."/".get_interface_subnet($key2);
            if(ip_in_subnet($clientip, $subnet)){
                $cpzone = $key1;
                $finder = true;
                break;
            }
        }
    }
    if($finder){
        break;
    }
}

if (empty($cpzone) || empty($config['captiveportal'][$cpzone])) { //chequear que exista la zona
    $response['success'] = false;
    $response['message'] = "La zona no existe en la configuracion.";
    return $response;
}

$cpcfg = $config['captiveportal'][$cpzone];

if (empty($cpcfg)) { //si la configuración de la zona no existe
    log_error("Submission to captiveportal with unknown parameter zone: " . htmlspecialchars($cpzone));
    $response['success'] = false;
    $response['message'] = "Submission to captiveportal with unknown parameter zone: " . htmlspecialchars($cpzone);
    return $response;
}

$cpzoneid = $cpcfg['zoneid'];

$orig_host = $_SERVER['HTTP_HOST'];
///* NOTE: IE 8/9 is buggy and that is why this is needed */
$orig_request = trim($_REQUEST['redirurl'], " /");

if (!empty($cpcfg['redirurl'])) {
    $redirurl = $cpcfg['redirurl'];
} else if (preg_match("/redirurl=(.*)/", $orig_request, $matches)) {
    $redirurl = urldecode($matches[1]);
} else if ($_REQUEST['redirurl']) {
    $redirurl = $_REQUEST['redirurl'];
}

$macfilter = !isset($cpcfg['nomacfilter']);
$passthrumac = isset($cpcfg['passthrumacadd']);

//die(var_dump($passthrumac));

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

///* find out if we need RADIUS + RADIUSMAC or not */
if (file_exists("{$g['vardb_path']}/captiveportal_radius_{$cpzone}.db")) {
    $radius_enable = TRUE;
    //var_dump(isset($cpcfg['radmac_enable']));
    if (isset($cpcfg['radmac_enable'])) {
        $radmac_enable = TRUE;
    }
}

///* find radius context */
$radiusctx = 'first';
if ($_POST['auth_user2']) {
    $radiusctx = 'second';
}