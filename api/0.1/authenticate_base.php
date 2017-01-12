<?php
/*

Not a method.

Function required to login to ManageBac and scrape private pages.

Created by FoxInFlame.
A Part of the unofficial ManageBacAPI.

*/

function getSession($username, $password, $domain = "intsch") {
  $authenticated = false; // Initial Value
  
  
  // ----------------------------------------------
  // First cURL to get cookies and the csrf token.
  // ----------------------------------------------
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://" . $domain . ".managebac.com/login");
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == '404') {
    echo json_encode(array(
      'error' => 'ManageBac doesn\'t exist on this domain!'
    ));
    http_response_code(404);
    die();
  }
  curl_close($ch);
  $doc = new DOMDocument();
  @$doc->loadHTML($response);
  $nodes = $doc->getElementsByTagName("meta");
  
  // Get csrf_token, required for logging in
  for($i = 0; $i < $nodes->length; $i++) {
    $meta = $nodes->item($i);
    if($meta->getAttribute("name") == "csrf-token") {
      $csrf_token = $meta->getAttribute("content");
    }
  }
  
  // Remember cookies.
  list($header, $body) = explode("\r\n\r\n", $response, 2);
  preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
  $cookies = array();
  $nextCurlcookies = "";
  foreach($matches[1] as $item) {
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
    if(array_key_exists("__cfduid", $cookie)) {
      $nextCurlcookies .= "__cfduid=" . $cookie["__cfduid"] . ";";
    } else if(array_key_exists("_managebac_session", $cookie)) {
      $nextCurlcookies .= "_managebac_session=" . $cookie["_managebac_session"] . "; request_method=POST;";
    }
  }
  
  
  // ----------------------------------------------
  // Second cURL to login to /sessions. The response is a 302 redirect to a horribly long URL with a payload parameter, or a 200 OK on failure.
  // ----------------------------------------------
  
  // Request to login using the csrf_token gained above and the cookies.
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://" . $domain . ".managebac.com/sessions");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_COOKIE, $nextCurlcookies);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
    'login' => $username,
    'password' => $password,
    'remember_me' => '0',
    'commit' => 'Sign-in',
    'utf' => '%E2%9C%93',
    'authenticity_token' => $csrf_token
  )));
  curl_setopt($ch, CURLINFO_HEADER_OUT, true);
  $response = curl_exec($ch);
  if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
    echo json_encode(array(
      'error' => 'Wrong Credentials.'
    ));
    http_response_code(401);
    die();
  }
  list($header, $body) = explode("\r\n\r\n", $response, 2);
  preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
  preg_match_all('/^Location:(.*)$/mi', $response, $matches2);
  $cookies = array();
  $nextCurlcookies = "";
  foreach($matches[1] as $item) {
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
    if(array_key_exists("__cfduid", $cookie)) {
      $nextCurlcookies .= "__cfduid=" . $cookie["__cfduid"] . ";";
    } else if(array_key_exists("_managebac_session", $cookie)) {
      $nextCurlcookies .= "_managebac_session=" . $cookie["_managebac_session"] . "; request_method=POST;";
    }
  }
  curl_close($ch);
  $payloadURL = trim($matches2[1][0]);
  
  // ----------------------------------------------
  // Third cURL to the long URL obtained in the second URL. The response is yet another 302 redirect to /launchpad/api/handle_forward_notification on success, or to /login on failure.
  // ----------------------------------------------
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $payloadURL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_COOKIE, $nextCurlcookies);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  $response = curl_exec($ch);
  list($header, $body) = explode("\r\n\r\n", $response, 2);
  preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
  preg_match_all('/^Location:(.*)$/mi', $response, $matches2);
  $cookies = array();
  $requestCurlcookies = "";
  foreach($matches[1] as $item) {
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
    if(array_key_exists("__cfduid", $cookie)) {
      $nextCurlcookies .= "__cfduid=" . $cookie["__cfduid"] . ";";
    } else if(array_key_exists("_managebac_session", $cookie)) {
      $nextCurlcookies .= "_managebac_session=" . $cookie["_managebac_session"] . "; request_method=POST;";
    }
  }
  curl_close($ch);
  $handleURL = trim($matches2[1][0]);
  
  // ----------------------------------------------
  // Fourth and final cURL to handle_forward_notification obtained in the third URL. The response is a 302 redirect to /student on success, or to /login on failure.
  // ----------------------------------------------
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $handleURL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_COOKIE, $nextCurlcookies);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  $response = curl_exec($ch);
  list($header, $body) = explode("\r\n\r\n", $response, 2);
  preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
  preg_match_all('/^Location:(.*)$/mi', $response, $matches2);
  $cookies = array();
  $requestCurlcookies = "";
  foreach($matches[1] as $item) {
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
    if(array_key_exists("__cfduid", $cookie)) {
      $requestCurlcookies .= "__cfduid=" . $cookie["__cfduid"] . ";";
    } else if(array_key_exists("user_id", $cookie)) {
      $requestCurlcookies .= "user_id=" . $cookie["user_id"] . ";";
    }
    if(array_key_exists("_managebac_session", $cookie)) {
      $requestCurlcookies .= "_managebac_session=" . $cookie["_managebac_session"] . "; request_method=POST;";
    }
  }
  
  if(strpos(trim($matches2[1][0]), "/student") !== false) {
    $authenticated = true;
  }
  
  if($authenticated) {
    return array(
      "cookie_string" => $requestCurlcookies,
      "csrf_token" => $csrf_token
    );
  } else {
    echo json_encode(array(
      "error" => "Wrong Credentials."
    ));
    http_response_code(401);
    die();
  }
}
?>
