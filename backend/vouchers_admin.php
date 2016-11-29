<?php
/**
 * Created by PhpStorm.
 * User: darian
 * Date: 10/11/16
 * Time: 18:00
 * Este fichero es para generar nuevos vouchers en el sistema
 */

/**
 * Devuelve los datos de todos los paquetes de vouchers
 * @return array
 */
function getVoucherPackagesData(){

    $response = [];

    require_once("init_vars.php");

    $a_roll = &$config['voucher'][$cpzone]['roll'];

    $responseData = [];
    foreach($a_roll as $rollent){
        $responseData[] = $rollent;
    }

    $response['success'] = true;
    $response['message'] = "Datos de los paquetes de vouchers";
    $response['data'] = $responseData;

    return $response;
}

/**
 * Genera un nuevo paquete de vouchers dependiendo de los valores que se le pasen en el objeto data
 * @param $data
 * @return array
 */
function generateNewVoucherPackage($data)
{

    $response = [];

    require_once("init_vars.php");

    if (!is_array($config['captiveportal'])) {
        $config['captiveportal'] = array();
    }
    $a_cp =& $config['captiveportal'];

    if (!is_array($config['voucher'])) {
        $config['voucher'] = array();
    }

    if (!is_array($config['voucher'][$cpzone]['roll'])) {
        $config['voucher'][$cpzone]['roll'] = array();
    }

    $a_roll = &$config['voucher'][$cpzone]['roll'];

    if (isset($_POST['id']) && is_numericint($_POST['id'])) { //id de uno que ya se haya creado, esto es para modificarlo
        $id = $_POST['id'];
    }

    if (isset($id) && $a_roll[$id]) {
        $pconfig['zone'] = $a_roll[$id]['zone'];
        $pconfig['number'] = $a_roll[$id]['number'];
        $pconfig['count'] = $a_roll[$id]['count'];
        $pconfig['minutes'] = $a_roll[$id]['minutes'];
        $pconfig['descr'] = $a_roll[$id]['descr'];
    }

    $maxnumber = (1 << $config['voucher'][$cpzone]['rollbits']) - 1;    // Highest Roll#
    $maxcount = (1 << $config['voucher'][$cpzone]['ticketbits']) - 1;     // Highest Ticket#

    if (isset($data->roll) and isset($data->minutes) and isset($data->count) and isset($data->desc)) { //si vienen por el post los 4 parametros de generar vouchers
        $roll = $data->roll;
        $minutes = $data->minutes;
        $count = $data->count;
        $desc = $data->desc;

        // Look for duplicate roll #
        foreach ($a_roll as $re) {
            if (isset($id) && $a_roll[$id] && $a_roll[$id] === $re) {
                continue;
            }
            if ($re['number'] == $roll) {
                $response['success'] = false;
                $response['message'] = "El roll ya existe";
                return $response;
            }
        }

        if (isset($id) && $a_roll[$id]) {
            $rollent = $a_roll[$id];
        }

        $rollent['zone'] = $cpzone;
        $rollent['number'] = $roll;
        $rollent['minutes'] = $minutes;
        $rollent['descr'] = $desc;

        /* New Roll or modified voucher count: create bitmask */
        $voucherlck = lock("voucher{$cpzone}");

        if ($count != $rollent['count']) {
            $rollent['count'] = $count;
            $len = ($rollent['count'] >> 3) + 1;     // count / 8 +1
            $rollent['used'] = base64_encode(str_repeat("\000", $len)); // 4 bitmask
            $rollent['active'] = array();
            voucher_write_used_db($rollent['number'], $rollent['used']);
            voucher_write_active_db($rollent['number'], array());    // create empty DB
            voucher_log(LOG_INFO, sprintf(gettext('All %1$s vouchers from Roll %2$s marked unused'), $rollent['count'], $rollent['number']));
        } else {
            // existing roll has been modified but without changing the count
            // read active and used DB from ramdisk and store it in XML config
            $rollent['used'] = base64_encode(voucher_read_used_db($rollent['number']));
            $activent = array();
            $db = array();
            $active_vouchers = voucher_read_active_db($rollent['number'], $rollent['minutes']);
            foreach ($active_vouchers as $voucher => $line) {
                list($timestamp, $minutes) = explode(",", $line);
                $activent['voucher'] = $voucher;
                $activent['timestamp'] = $timestamp;
                $activent['minutes'] = $minutes;
                $db[] = $activent;
            }
            $rollent['active'] = $db;
        }

        unlock($voucherlck);

        if (isset($id) && $a_roll[$id]) {
            $a_roll[$id] = $rollent;
        } else {
            $a_roll[] = $rollent;
        }

        write_config();

        //devolver los vouchers generados en un json
        $privkey = base64_decode($config['voucher'][$cpzone]['privatekey']);
        if (strstr($privkey, "BEGIN RSA PRIVATE KEY")) {
            $fd = fopen("{$g['varetc_path']}/voucher_{$cpzone}.private", "w");
            if (!$fd) {
                //                    $input_errors[] = gettext("Cannot write private key file") . ".\n";
                $response['success'] = true;
                $response['message'] = "Se han creado satisfactoriamente nuevos vouchers, pero no se han podido mostrar porque no se puede escribir el arhivo private key";
            } else {
                chmod("{$g['varetc_path']}/voucher_{$cpzone}.private", 0600);
                fwrite($fd, $privkey);
                fclose($fd);
                $a_voucher = &$config['voucher'][$cpzone]['roll'];
                $id = count($a_voucher) - 1;
                if (isset($id) && $a_voucher[$id]) {
                    $number = $a_voucher[$id]['number'];
                    $count = $a_voucher[$id]['count'];
//                        header("Content-Type: application/octet-stream");
//                        header("Content-Disposition: attachment; filename=vouchers_{$cpzone}_roll{$number}.csv");
                    if (file_exists("{$g['varetc_path']}/voucher_{$cpzone}.cfg")) {
                        //capturar en una variable string  la salida de la generacion de vouchers
                        $output = shell_exec("/usr/local/bin/voucher -c {$g['varetc_path']}/voucher_{$cpzone}.cfg -p {$g['varetc_path']}/voucher_{$cpzone}.private $number $count");
                        //crear un arreglo con todos los vouchers de la salida
                        $vouchers = array();
                        $voucher = "";
                        $takeCharacter = false;
                        for ($i = 0; $i < strlen($output); $i++) {
                            if (($output[$i] == '"') and ($takeCharacter == true)) {
                                $vouchers[] = $voucher;
                                $voucher = "";
                                $takeCharacter = false;
                                continue;
                            }
                            if (($output[$i] == '"') and ($takeCharacter == false)) {
                                $takeCharacter = true;
                                continue;
                            }
                            if ($takeCharacter == true) {
                                $voucher .= $output[$i];
                            }
                        }
                    }
                    @unlink("{$g['varetc_path']}/voucher_{$cpzone}.private");
                } else {
                    $response['success'] = true;
                    $response['message'] = "Se han creado satisfactoriamente nuevos vouchers, pero se necesita un id para imprimir los vouchers.";
                }
            }
        } else {
            //                $input_errors[] = gettext("Need private RSA key to print vouchers") . "\n";
            $response['success'] = true;
            $response['message'] = "Se han creado satisfactoriamente nuevos vouchers, pero se necesita una llave RSA privada para imprimir los vouchers.";
        }

        //conformando el json de respuesta
        $data = array();
        $data["vouchers"] = $vouchers;
        $response['success'] = true;
        $response['data'] = $data;
        $response['message'] = "Se han creado satisfactoriamente nuevos vouchers.";
    } else {
        $response['success'] = false;
        $response['message'] = "Faltan parametros.";
    }

    return $response; //devolver el array de la respuesta en formato json
}

/**
 * Genera nuevas llaves para la generacion de paquetes de vouchers
 * @return array
 */
function generateKeyPar(){

    $response = [];

    require_once("init_vars.php");

//    die(var_dump($config['voucher'][$cpzone]));

    exec("/usr/bin/openssl genrsa 64 > /tmp/key64.private");
    exec("/usr/bin/openssl rsa -pubout < /tmp/key64.private > /tmp/key64.public");
//    $privateKey = str_replace("\n", "\\n", file_get_contents("/tmp/key64.private"));
    $privateKey = file_get_contents("/tmp/key64.private");
//    die(var_dump($privateKey));
//    $publicKey = str_replace("\n", "\\n", file_get_contents("/tmp/key64.public"));
    $publicKey = file_get_contents("/tmp/key64.public");
    exec("rm /tmp/key64.private /tmp/key64.public");
    $config['voucher'][$cpzone]['publickey'] = base64_encode($publicKey);
    $config['voucher'][$cpzone]['privatekey'] = base64_encode($privateKey);
    write_config();
    voucher_configure_zone(true);
//    print json_encode(['public' => $publickey, 'private' => $privatekey]);
    $data = array();
    $data["public"] = $publicKey;
    $data["private"] = $privateKey;
    $response['success'] = true;
    $response['data'] = $data;
    $response['message'] = "Se han creado nuevas llaves satisfactoriamente.";
    return $response;
}

/**
 * Guarda las llaves dadas para la generacion de paquetes de vouchers
 * @param $data
 * @return array
 */
function saveKeyPar($data){

    $response = [];

    require_once("init_vars.php");

    if(empty($data->privateKey) and empty($data->publicKey)){
        $response['success'] = false;
        $response['message'] = "Error, Las llaves proporcionadas tienen problemas.";
    }else{
        $config['voucher'][$cpzone]['publickey'] = base64_encode($data->publicKey);
        $config['voucher'][$cpzone]['privatekey'] = base64_encode($data->privateKey);
        write_config();
        voucher_configure_zone(true);
        $response['success'] = true;
        $response['message'] = "Se han guardado las llaves nuevas satisfactoriamente.";
    }

    return $response;
}

/**
 * Devuelva las llaves actuales que se estan usando en la generacion de vouchers
 * @return array
 */
function getKeyPar(){

    $response = [];

    require_once("init_vars.php");

    $privateKey = base64_decode($config['voucher'][$cpzone]['privatekey']);
    $publicKey = base64_decode($config['voucher'][$cpzone]['publickey']);
    if(empty($privateKey) or empty($publicKey)) {
        $response['success'] = false;
        $response['message'] = "Error, no existen llaves para esta zona.";
    }else{
        $data = [];
        $data['privateKey'] = $privateKey;
        $data['publicKey'] = $publicKey;
        $response['success'] = true;
        $response['data'] = $data;
        $response['message'] = "Mostrando las llaves actuales.";
    }

    return $response;
}