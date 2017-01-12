<?php
// [+] ============================================== [+]
// [+] ---------------------------------------------- [+]
// [+] -------------------HEADERS-------------------- [+]
// [+] ---------------------------------------------- [+]
// [+] ============================================== [+]
ini_set("display_errors", true);
ini_set("display_startup_errors", true);
error_reporting(E_ALL);

header("access-control-allow-origin: *");
header('Content-Type: application/json');
require(dirname(__FILE__) ."/../SimpleHtmlDOM.php");


call_user_func(function() {

  // [+] ============================================== [+]
  // [+] ---------------------------------------------- [+]
  // [+] --------------------LOGIN--------------------- [+]
  // [+] ---------------------------------------------- [+]
  // [+] ============================================== [+]
  
  require(dirname(__FILE__) . "/authenticate_base.php");
  
  if(!isset($_GET['domain']) || empty($_GET['domain']) || strpos($_GET['domain'], ".") !== false || strpos($_GET['domain'], "/") !== false) {
    echo json_encode(array(
      "error" => "Parameter 'domain' is not provided."
    ));
    http_response_code(400);
    return;
  }
  if(!isset($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) || empty($_SERVER['PHP_AUTH_PW'])) {
    header("WWW-Authenticate: Basic realm=\"" . $_GET['domain'] . ".managebac.com\"");
    echo json_encode(array(
      "error" => "Authorisation Required."
    ));
    http_response_code(401);
    return;
  } else {
    $MBSession = getSession($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_GET['domain']);
  }

  // [+] ============================================== [+]
  // [+] ---------------------------------------------- [+]
  // [+] --------------GETTING THE VALUES-------------- [+]
  // [+] ---------------------------------------------- [+]
  // [+] ============================================== [+]
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://" . $_GET['domain'] . ".managebac.com/student");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_COOKIE, $MBSession['cookie_string']);
  $response = curl_exec($ch);
  
  echo $response;
  
});
