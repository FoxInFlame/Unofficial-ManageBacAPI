<?php
// [+] ============================================== [+]
// [+] ---------------------------------------------- [+]
// [+] -------------------HEADERS-------------------- [+]
// [+] ---------------------------------------------- [+]
// [+] ============================================== [+]
ini_set("display_errors", true);
ini_set("display_startup_errors", true);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require(dirname(__FILE__) ."/../SimpleHtmlDOM.php");


call_user_func(function() {

  // [+] ============================================== [+]
  // [+] ---------------------------------------------- [+]
  // [+] --------------------LOGIN--------------------- [+]
  // [+] ---------------------------------------------- [+]
  // [+] ============================================== [+]
  
  require(dirname(__FILE__) . "/../authenticate_base.php");
  
  if(!isset($_GET['domain']) || empty($_GET['domain']) || strpos($_GET['domain'], ".") !== false || strpos($_GET['domain'], "/") !== false) {
    echo json_encode(array(
      "message" => "Parameter 'domain' is not provided."
    ));
    http_response_code(400);
    return;
  }
  if(!isset($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) || empty($_SERVER['PHP_AUTH_PW'])) {
    header("WWW-Authenticate: Basic realm=\"" . $_GET['domain'] . ".managebac.com\"");
    echo json_encode(array(
      "message" => "Authorisation Required."
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
  curl_setopt($ch, CURLOPT_COOKIE, $MBSession['cookie_string']);
  $response = curl_exec($ch);
  
  if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == 404) {
    echo json_encode(array(
      "message" => "ManageBac is offline or the school doesn't exist."
    ));
    http_response_code(404);
    return;
  }
  curl_close($ch);
  
  $html = str_get_html($response);
  
  $navmenu = $html->find("#menu ul.nav-menu", 0);
  $classes_arr = array();
  foreach($navmenu->children() as $item) {
    if(!$item->find("a", 0)) continue; // It's not a link...
    if(strpos($item->find("a", 0)->plaintext, "Classes") !== false) {
      foreach($item->children(1)->children() as $class) {
        if(!$class->find("a span", 0)) continue; // It's the "join more..." tab
        $url = $class->find("a", 0)->href;
        $name = $class->find("a span", 0)->innertext;
        array_push($classes_arr, array(
          "id" => explode("/", $url)[3],
          "url" => $url,
          "name" => trim($name)
        ));
      }
      break;
    }
  }
  
  $output = array(
    "items" => $classes_arr
  );
  
  // Remove string_ after parse
  // JSON_NUMERIC_CHECK flag requires at least PHP 5.3.3
  echo str_replace("string_", "", json_encode($output, JSON_NUMERIC_CHECK));
  http_response_code(200);
  
});