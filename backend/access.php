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
//        $totalTimeOfVoucher = $cpsession['session_timeout'] / 60;
//        $consumedVoucherTime = $totalTimeOfVoucher - $timecredit;
//        $responsedata['activationTime'] = date("H:i - Y/m/d", strtotime('-' . strval($consumedVoucherTime) . ' minutes'));
        $responsedata['activationTime'] = date("H:i - Y/m/d");
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
            $sessionid = myPortalAllow($clientip, $clientmac, $voucher, null, $attr);
            if (is_string($sessionid)) { // YES: user is good for $timecredit minutes.
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
            } else if($sessionid == 0){
                captiveportal_logportalauth($voucher, $clientmac, $clientip, "FAILURE", "voucher expired");
                $response['success'] = false;
                $response['message'] = "Voucher expirado.";
            } else if($sessionid == -1){
                $response['success'] = false;
//                $response['message'] = "Username: {$username} is already authenticated using another MAC address.";
                $response['message'] = "El voucher ".$voucher." esta siendo usado desde otra computadora";
            } else if($sessionid == -2){
                $response['success'] = false;
//                $response['message'] = "System reached maximum login capacity";
                $response['message'] = "El sistema ha alcanzado la capacidad maxima de autenticacion";
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

/**
 * Es una funcion copiada de ellos, con la unica diferencia que esta no redirecciona
 * @param $clientip
 * @param $clientmac
 * @param $username
 * @param null $password
 * @param null $attributes
 * @param null $pipeno
 * @param null $radiusctx
 * @return bool|string
 */
function myPortalAllow($clientip, $clientmac, $username, $password = null, $attributes = null, $pipeno = null, $radiusctx = null) {
    global $redirurl, $g, $config, $type, $passthrumac, $_POST, $cpzone, $cpzoneid;

    // Ensure we create an array if we are missing attributes
    if (!is_array($attributes)) {
        $attributes = array();
    }

    unset($sessionid);

    /* Do not allow concurrent login execution. */
    $cpdblck = lock("captiveportaldb{$cpzone}", LOCK_EX);

    if ($attributes['voucher']) {
        $remaining_time = $attributes['session_timeout'];
        // Set RADIUS-Attribute to Voucher to prevent ReAuth-Reqeuest for Vouchers Bug: #2155
        $radiusctx="voucher";
    }

    $writecfg = false;
    /* Find an existing session */
    if ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) && $passthrumac) {
        if (isset($config['captiveportal'][$cpzone]['passthrumacadd'])) {
            $mac = captiveportal_passthrumac_findbyname($username);
            if (!empty($mac)) {
                if ($_POST['replacemacpassthru']) {
                    foreach ($config['captiveportal'][$cpzone]['passthrumac'] as $idx => $macent) {
                        if ($macent['mac'] == $mac['mac']) {
                            $macrules = "";
                            $ruleno = captiveportal_get_ipfw_passthru_ruleno($mac['mac']);
                            $pipeno = captiveportal_get_dn_passthru_ruleno($mac['mac']);
                            if ($ruleno) {
                                captiveportal_free_ipfw_ruleno($ruleno);
                                $macrules .= "delete {$ruleno}\n";
                                ++$ruleno;
                                $macrules .= "delete {$ruleno}\n";
                            }
                            if ($pipeno) {
                                captiveportal_free_dn_ruleno($pipeno);
                                $macrules .= "pipe delete {$pipeno}\n";
                                ++$pipeno;
                                $macrules .= "pipe delete {$pipeno}\n";
                            }
                            unset($config['captiveportal'][$cpzone]['passthrumac'][$idx]);
                            $mac['action'] = 'pass';
                            $mac['mac'] = $clientmac;
                            $config['captiveportal'][$cpzone]['passthrumac'][] = $mac;
                            $macrules .= captiveportal_passthrumac_configure_entry($mac);
                            file_put_contents("{$g['tmp_path']}/macentry_{$cpzone}.rules.tmp", $macrules);
                            mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/macentry_{$cpzone}.rules.tmp");
                            $writecfg = true;
                            $sessionid = true;
                            break;
                        }
                    }
                } else {
//                    portal_reply_page($redirurl, "error", "Username: {$username} is already authenticated using another MAC address.",
//                        $clientmac, $clientip, $username, $password);
                    unlock($cpdblck);
                    return -1;
                }
            }
        }
    }

    /* read in client database */
    $query = "WHERE ip = '{$clientip}'";
    $tmpusername = SQLite3::escapeString(strtolower($username));
    if (isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) {
        $query .= " OR (username != 'unauthenticated' AND lower(username) = '{$tmpusername}')";
    }
    $cpdb = captiveportal_read_db($query);

    /* Snapshot the timestamp */
    $allow_time = time();
    $radiusservers = captiveportal_get_radius_servers();
    $unsetindexes = array();
    if (is_null($radiusctx)) {
        $radiusctx = 'first';
    }

    foreach ($cpdb as $cpentry) {
        if (empty($cpentry[11])) {
            $cpentry[11] = 'first';
        }
        /* on the same ip */
        if ($cpentry[2] == $clientip) {
            if (isset($config['captiveportal'][$cpzone]['nomacfilter']) || $cpentry[3] == $clientmac) {
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - REUSING OLD SESSION");
            } else {
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - REUSING IP {$cpentry[2]} WITH DIFFERENT MAC ADDRESS {$cpentry[3]}");
            }
            $sessionid = $cpentry[5];
            break;
        } elseif (($attributes['voucher']) && ($username != 'unauthenticated') && ($cpentry[4] == $username)) {
            // user logged in with an active voucher. Check for how long and calculate
            // how much time we can give him (voucher credit - used time)
            $remaining_time = $cpentry[0] + $cpentry[7] - $allow_time;
            if ($remaining_time < 0) { // just in case.
                $remaining_time = 0;
            }

            /* This user was already logged in so we disconnect the old one */
            captiveportal_disconnect($cpentry, $radiusservers[$cpentry[11]], 13);
            captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - TERMINATING OLD SESSION");
            $unsetindexes[] = $cpentry[5];
            break;
        } elseif ((isset($config['captiveportal'][$cpzone]['noconcurrentlogins'])) && ($username != 'unauthenticated')) {
            /* on the same username */
            if (strcasecmp($cpentry[4], $username) == 0) {
                /* This user was already logged in so we disconnect the old one */
                captiveportal_disconnect($cpentry, $radiusservers[$cpentry[11]], 13);
                captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "CONCURRENT LOGIN - TERMINATING OLD SESSION");
                $unsetindexes[] = $cpentry[5];
                break;
            }
        }
    }
    unset($cpdb);

    if (!empty($unsetindexes)) {
        captiveportal_remove_entries($unsetindexes);
    }

    if ($attributes['voucher'] && $remaining_time <= 0) {
        return 0;       // voucher already used and no time left
    }

    if (!isset($sessionid)) {
        /* generate unique session ID */
        $tod = gettimeofday();
        $sessionid = substr(md5(mt_rand() . $tod['sec'] . $tod['usec'] . $clientip . $clientmac), 0, 16);

        if ($passthrumac) {
            $mac = array();
            $mac['action'] = 'pass';
            $mac['mac'] = $clientmac;
            $mac['ip'] = $clientip; /* Used only for logging */
            if (isset($config['captiveportal'][$cpzone]['passthrumacaddusername'])) {
                $mac['username'] = $username;
                if ($attributes['voucher']) {
                    $mac['logintype'] = "voucher";
                }
            }
            if ($username == "unauthenticated") {
                $mac['descr'] = "Auto-added";
            } else {
                $mac['descr'] = "Auto-added for user {$username}";
            }
            if (!empty($bw_up)) {
                $mac['bw_up'] = $bw_up;
            }
            if (!empty($bw_down)) {
                $mac['bw_down'] = $bw_down;
            }
            if (!is_array($config['captiveportal'][$cpzone]['passthrumac'])) {
                $config['captiveportal'][$cpzone]['passthrumac'] = array();
            }
            $config['captiveportal'][$cpzone]['passthrumac'][] = $mac;
            unlock($cpdblck);
            $macrules = captiveportal_passthrumac_configure_entry($mac);
            file_put_contents("{$g['tmp_path']}/macentry_{$cpzone}.rules.tmp", $macrules);
            mwexec("/sbin/ipfw -x {$cpzoneid} -q {$g['tmp_path']}/macentry_{$cpzone}.rules.tmp");
            $writecfg = true;
        } else {
            /* See if a pipeno is passed, if not start sessions because this means there isn't one atm */
            if (is_null($pipeno)) {
                $pipeno = captiveportal_get_next_dn_ruleno();
            }

            /* if the pool is empty, return appropriate message and exit */
            if (is_null($pipeno)) {
//                portal_reply_page($redirurl, "error", "System reached maximum login capacity");
                log_error("Zone: {$cpzone} - WARNING!  Captive portal has reached maximum login capacity");
                unlock($cpdblck);
                return -2;
            }

            if (isset($config['captiveportal'][$cpzone]['peruserbw'])) {
                $dwfaultbw_up = !empty($config['captiveportal'][$cpzone]['bwdefaultup']) ? $config['captiveportal'][$cpzone]['bwdefaultup'] : 0;
                $dwfaultbw_down = !empty($config['captiveportal'][$cpzone]['bwdefaultdn']) ? $config['captiveportal'][$cpzone]['bwdefaultdn'] : 0;
            } else {
                $dwfaultbw_up = $dwfaultbw_down = 0;
            }
            $bw_up = !empty($attributes['bw_up']) ? round(intval($attributes['bw_up'])/1000, 2) : $dwfaultbw_up;
            $bw_down = !empty($attributes['bw_down']) ? round(intval($attributes['bw_down'])/1000, 2) : $dwfaultbw_down;

            $bw_up_pipeno = $pipeno;
            $bw_down_pipeno = $pipeno + 1;
            //$bw_up /= 1000; // Scale to Kbit/s
            $_gb = @pfSense_pipe_action("pipe {$bw_up_pipeno} config bw {$bw_up}Kbit/s queue 100 buckets 16");
            $_gb = @pfSense_pipe_action("pipe {$bw_down_pipeno} config bw {$bw_down}Kbit/s queue 100 buckets 16");

            $clientsn = (is_ipaddrv6($clientip)) ? 128 : 32;
            if (!isset($config['captiveportal'][$cpzone]['nomacfilter'])) {
                $_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XADD, 1, $clientip, $clientsn, $clientmac, $bw_up_pipeno);
            } else {
                $_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XADD, 1, $clientip, $clientsn, NULL, $bw_up_pipeno);
            }

            if (!isset($config['captiveportal'][$cpzone]['nomacfilter'])) {
                $_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XADD, 2, $clientip, $clientsn, $clientmac, $bw_down_pipeno);
            } else {
                $_gb = @pfSense_ipfw_Tableaction($cpzoneid, IP_FW_TABLE_XADD, 2, $clientip, $clientsn, NULL, $bw_down_pipeno);
            }

            if ($attributes['voucher']) {
                $attributes['session_timeout'] = $remaining_time;
            }

            /* handle empty attributes */
            $session_timeout = (!empty($attributes['session_timeout'])) ? $attributes['session_timeout'] : 'NULL';
            $idle_timeout = (!empty($attributes['idle_timeout'])) ? $attributes['idle_timeout'] : 'NULL';
            $session_terminate_time = (!empty($attributes['session_terminate_time'])) ? $attributes['session_terminate_time'] : 'NULL';
            $interim_interval = (!empty($attributes['interim_interval'])) ? $attributes['interim_interval'] : 'NULL';

            /* escape username */
            $safe_username = SQLite3::escapeString($username);

            /* encode password in Base64 just in case it contains commas */
            $bpassword = base64_encode($password);
            $insertquery = "INSERT INTO captiveportal (allow_time, pipeno, ip, mac, username, sessionid, bpassword, session_timeout, idle_timeout, session_terminate_time, interim_interval, radiusctx) ";
            $insertquery .= "VALUES ({$allow_time}, {$pipeno}, '{$clientip}', '{$clientmac}', '{$safe_username}', '{$sessionid}', '{$bpassword}', ";
            $insertquery .= "{$session_timeout}, {$idle_timeout}, {$session_terminate_time}, {$interim_interval}, '{$radiusctx}')";

            /* store information to database */
            captiveportal_write_db($insertquery);
            unlock($cpdblck);
            unset($insertquery, $bpassword);

            if (isset($config['captiveportal'][$cpzone]['radacct_enable']) && !empty($radiusservers[$radiusctx])) {
                $acct_val = RADIUS_ACCOUNTING_START($pipeno, $username, $sessionid, $radiusservers[$radiusctx], $clientip, $clientmac);
                if ($acct_val == 1) {
                    captiveportal_logportalauth($username, $clientmac, $clientip, $type, "RADIUS ACCOUNTING FAILED");
                }
            }
        }
    } else {
        /* NOTE: #3062-11 If the pipeno has been allocated free it to not DoS the CP and maintain proper operation as in radius() case */
        if (!is_null($pipeno)) {
            captiveportal_free_dn_ruleno($pipeno);
        }

        unlock($cpdblck);
    }

    if ($writecfg == true) {
        write_config();
    }

    return $sessionid;
}