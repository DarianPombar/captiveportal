
<?php
/**
* 
* helper.php
*
*@autor Darian Enrique Martínez Pombar
*@version 1.0
* Este archivo es el archivo principal del backend, todas las peticiones ajax se realizan aquí 
* y depende de la acción este determina la fución a llamar 
*/

require_once("captiveportal.inc");
require_once("functions.inc");
require_once("auth.inc");

if(isAjax()){ // si es una petición web de tipo ajax
	$response = [];
	if(isset($_POST['request'])){
		$request = json_decode($_POST['request']);

		/** incluir todos los ficheros con todas las funciones disponibles */
		require_once("access.php");
		require_once("auth.php");
		require_once("vouchers_admin.php");
        switch ($request->action) {
            case "initCheck":
                $response = initCheck();
                break;
            case "checkIfIsLogged":
                $response = checkIfIsLogged();
                break;
            case "checkVoucherForTraffic":
                $response = checkVoucherForTraffic($request->data);
                break;
            case "disconnectClient":
                $response = disconnectClient($request->data);
                break;
            case "authenticate":
                $response = authenticate($request->data);
                break;
            case "verifyVoucher":
                $response = verifyVoucher($request->data);
                break;
            case "getVoucherPackagesData":
                $response = getVoucherPackagesData();
                break;
            case "generateNewVoucherPackage":
                $response = generateNewVoucherPackage($request->data);
                break;
            case "generateKeyPar":
                $response = generateKeyPar();
                break;
            case "saveKeyPar":
                $response = saveKeyPar($request->data);
                break;
            case "getKeyPar":
                $response = getKeyPar();
                break;
        }
	}else{
		$response['success'] = false;
		$response['message'] = "Falta el parámetro request";
	}

	echo json_encode($response); //codificar la respuesta en formato json y mostrarla
	
}else{ // no es una peticion web de tipo ajax
	echo "El acceso a las funciones de esta pagina estan disponibles solo por peticiones ajax.";
}