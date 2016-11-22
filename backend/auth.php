<?php
/**
 * Created by PhpStorm.
 * User: darian
 * Date: 10/11/16
 * Time: 16:09
 * Este archivo es para saber si un usuario y una contrasena son validas para poder acceder al pfsense
 */
require_once("auth.inc");

function authenticate($data){
    $response = [];
    if (authenticate_user($data->username, $data->password)) { //si el usuario se puede autentificar
        $response['success'] = true;
        $response['message'] = "Se autentifico correctamente.";
    } else {
        $response['success'] = false;
        $response['message'] = "Usuario o contrasena incorrectos.";
    }

    return $response; //devolver el array de la respuesta en formato json
}