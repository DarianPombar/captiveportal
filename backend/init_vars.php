<?php
/**
* init_vars.php
* Este fichero es para inicializar toda la configuración en varibales para utilizarlas desde otros ficheros
*/

    global $config, $g, $cpzone, $cpzoneid;

    $cpzone = "wireless";

	$cpcfg = $config['captiveportal'][$cpzone];

	$cpzoneid = $cpcfg['zoneid'];

    $orig_host = $_SERVER['HTTP_HOST'];
    ///* NOTE: IE 8/9 is buggy and that is why this is needed */
    $orig_request = trim($_REQUEST['redirurl'], " /");

    $clientip = $_SERVER['REMOTE_ADDR'];

    if (!empty($cpcfg['redirurl'])) {
        $redirurl = $cpcfg['redirurl'];
    } else if (preg_match("/redirurl=(.*)/", $orig_request, $matches)) {
        $redirurl = urldecode($matches[1]);
    } else if ($_REQUEST['redirurl']) {
        $redirurl = $_REQUEST['redirurl'];
    }

    $macfilter = !isset($cpcfg['nomacfilter']);
    $passthrumac = isset($cpcfg['passthrumacadd']);

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