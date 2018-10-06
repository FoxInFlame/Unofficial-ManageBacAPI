<?php
/*

Not a method.

Function required to login to ManageBac and scrape private pages.

Created by FoxInFlame.
A Part of the unofficial ManageBacAPI.

*/

function getSession($username, $password, $domain = 'intsch') {

  $authenticated = false; // Initial Value
  
  // ----------------------------------------------
  // First cURL to get the csrf token.
  // ----------------------------------------------
  
  // Send a GET request to /login
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://' . $domain . '.managebac.com/login');
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  // Keep response (which includes header) in $response
  $response = curl_exec($ch);

  // Return 404 if MB doesn't exist
  if(curl_getinfo($ch, CURLINFO_HTTP_CODE) === '404') {

    // Headers should be sent before content
    http_response_code(404);
    echo json_encode([
      'message' => 'ManageBac doesn\'t exist on this domain!'
    ]);

    // Stop further execution
    die();
    
  }

  // Close connection to save memory (response is already stored in variable)
  curl_close($ch);

  // Split the response into header and body.
  list($header, $body) = explode("\r\n\r\n", $response, 2);

  // Find the csrf_token from the <meta> tag
  $doc = new DOMDocument();
  @$doc->loadHTML($body);
  $nodes = $doc->getElementsByTagName('meta');
  
  // Loop through the meta tags in search for csrf_token
  foreach($nodes as $node) {
    if($node->getAttribute('name') === 'csrf-token') {
      $csrf_token = $node->getAttribute('content');
      break;
    }
  }

  // Check if csrf_token was properly found
  if(!isset($csrf_token)) {
    
    http_response_code(500);
    echo json_encode([
      'message' => 'ManageBac did not respond properly, maybe they changed their code?'
    ]);

    die();

  }

  // Parse the cookies that were requested to be set (from the header).
  preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $cookies_to_set);

  // Cookies to use in the next POST request
  $cookies_next = [];

  // Since preg_match_all makes $matches an array, select the second one (first is the entire match not the value)
  // Each item is the cookie setting
  foreach($cookies_to_set[1] as $item) {

    // Since each item is in the format of "name=value", we can use parse_str
    // which parses a string like it's a URL query string
    parse_str($item, $cookie_array); // $cookie_array is now ["name" => "value"]

    // Keep only cfduid and managebac session cookies
    if(isset($cookie_array['__cfduid']) || isset($cookie_array['_managebac_session'])) {
      
      // Merge and keep the cookie
      $cookies_next = array_merge($cookies_next, $cookie_array);

    }

  }

  // Seems like it could be wise to send this on the next request
  // Probably not required but no harm in sending additional headers
  $cookies_next['request_method'] = 'POST';
  

  // ----------------------------------------------
  // Second cURL to login to /sessions. The response is a 302 redirect to /student, or a 200 OK on failure.
  // ----------------------------------------------
  
  // Send a POST request to /sessions
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://' . $domain . '.managebac.com/sessions');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return body
  curl_setopt($ch, CURLOPT_HEADER, true); // Return header

  // Combine the array into a string like the one we received: name=value;name=value;name=value
  $cookies_to_send = http_build_query($cookies_next, null, ';'); 
  curl_setopt($ch, CURLOPT_COOKIE, $cookies_to_send);

  // Ideally, we would want to send a HEAD request since we won't be using the body
  // but some servers respond differently when HEAD is sent instead of POST, so we
  // should stick with POST to be on the safe side.
  curl_setopt($ch, CURLOPT_POST, 1); // Set as POST request
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'login' => $username,
    'password' => $password,
    'remember_me' => '0',
    'commit' => 'Sign-in',
    'utf' => '%E2%9C%93',
    'authenticity_token' => $csrf_token
  ]));

  // Keep response (which includes header) in $response
  $response = curl_exec($ch);

  // Return 401 if wrong credentials
  if(curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {

    http_response_code(401);
    echo json_encode([
      'message' => 'Wrong Credentials.'
    ]);

    die();

  }

  // Close connection to save memory.
  curl_close($ch);

  // Split the response into header and body so that.
  $header = explode("\r\n\r\n", $response)[0];

  // Match the cookies that were requested to be set.
  preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $cookies_received_match);

  // Match the redirect location that was received.
  preg_match_all('/^Location:(.*)$/mi', $header, $redirect_location_match);

  // The final array cookies that will eventually be returned to the application
  // together with the csrf_token.
  $final_cookies = [];

  // Same as previous request.
  foreach($cookies_received_match[1] as $item) {

    parse_str($item, $cookie_array); // $cookie is now ["name" => "value"]

    if(isset($cookie_array['__cfduid']) || isset($cookie_array['_managebac_session'])) {
      
      $final_cookies = array_merge($final_cookies, $cookie_array);

    }

  }

  // You know, same as above. No proper reason. 
  $final_cookies['request_method'] = 'POST';

  // See image linked below to get a grasp of how the match is returned
  // to understand why we need to use [1][0].
  // https://i.imgur.com/lnJb3PW.png
  if(strpos(trim($redirect_location_match[1][0]), "/student") !== false) {
    $authenticated = true;
  }

  // If for some reason ManageBac returned something else (highly unlikely because we die() if
  // credentials are wrong anyway) ...
  if(!$authenticated) {

    http_response_code(500);
    echo json_encode([
      'message' => 'ManageBac did not respond properly, maybe they changed their code?'
    ]);

    die();

  }

  // Same as before, combine the array into a string so that it will be easier to use when
  // sending additional custom requests (just gotta plop it into CURLOPT_COOKIES), no need
  // to build query everytime
  $cookies_to_return = http_build_query($final_cookies, null, ';');

  // Done.
  return [
    'cookie_string' => $cookies_to_return,
    'csrf_token' => $csrf_token
  ];

}
?>