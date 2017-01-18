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
  if(!isset($_GET['class']) || empty($_GET['class'])) {
    echo json_encode(array(
      "message" => "Parameter 'class'  is not provided."
    ));
    http_response_code(400);
    return;
  }
  if(!is_numeric($_GET['class'])) {
    echo json_encode(array(
      "message" => "Parameter 'class' needs to be numerical."
    ));
    http_response_code(400);
    return;
  }
  $queryString = isset($_GET['term']) && is_numeric($_GET['term']) ? "?term=" . $_GET['term'] : "";
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
  curl_setopt($ch, CURLOPT_URL, "https://" . $_GET['domain'] . ".managebac.com/student/classes/" . $_GET['class'] . "/tasks" . $queryString);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
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
  
  list($header, $body) = explode("\r\n\r\n", $response, 2);
  preg_match_all('/^Location:(.*)$/mi', $response, $matches2);
  if(count($matches2[1]) !== 0) {
    if(count(explode("/", trim($matches2[1][0]))) == 4) {
      // So it can match both /student and /home
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://" . $_GET['domain'] . ".managebac.com/student/classes/" . $_GET['class']);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_COOKIE, $MBSession['cookie_string']);
      $body = curl_exec($ch);
    }
  }
  
  $html = str_get_html($body);
  
  $chart = $html->find(".tasks-progress-chart", 0);
  if(!$chart) {
    echo json_encode(array(
      "class" => array(
        "id" => $_GET['class'],
        "name" => $html->find(".content-wrapper .content-block-header h3", 0)->innertext
      ),
      "tasks" => array()
    ));
    http_response_code(200);
    return;
  }
  
  $criteriaJSON = $chart->{"data-criterion-labels"};
  $criteriaJSON = html_entity_decode($criteriaJSON);
  $criteriaJSON = json_decode($criteriaJSON);
  
  $chartJSON = $chart->{"data-series"};
  $chartJSON = html_entity_decode($chartJSON);
  $chartJSON = json_decode($chartJSON);
  
  $tasks_arr = array();
  $criterion_arr = array(
    "a" => null,
    "b" => null,
    "c" => null,
    "d" => null
  );
  
  foreach($criteriaJSON as $criteria) {
    if(strpos($criteria, "A: ") !== false) {
      $criterion_arr["a"] = $criteria;
      continue;
    }
    if(strpos($criteria, "B: ") !== false) {
      $criterion_arr["b"] = $criteria;
      continue;
    }
    if(strpos($criteria, "C: ") !== false) {
      $criterion_arr["c"] = $criteria;
      continue;
    }
    if(strpos($criteria, "D: ") !== false) {
      $criterion_arr["d"] = $criteria;
      continue;
    }
  }
  
  foreach($chartJSON as $task) {
    $task_grades = $task->data;
    if($criterion_arr["a"] == null) {
      array_splice($task_grades, 0, 0, array(null));
    }
    if($criterion_arr["b"] == null) {
      array_splice($task_grades, 1, 0, array(null));
    }
    if($criterion_arr["c"] == null) {
      array_splice($task_grades, 2, 0, array(null));
    }
    if($criterion_arr["d"] == null) {
      array_splice($task_grades, 3, 0, array(null));
    }
    array_push($tasks_arr, array(
      "name" => $task->name,
      "grades" => $task_grades
    ));
  }
  
  $output = array(
    "class" => array(
      "id" => $_GET['class'],
      "name" => $html->find(".content-wrapper .content-block-header h3", 0)->innertext
    ),
    "criterion" => $criterion_arr,
    "tasks" => $tasks_arr
  );
  
  // Remove string_ after parse
  // JSON_NUMERIC_CHECK flag requires at least PHP 5.3.3
  echo str_replace("string_", "", json_encode($output, JSON_NUMERIC_CHECK));
  http_response_code(200);
  
});