<?php
function fetch_location($ip) {
    $apiKey = 'CHANGE_TO_YOUR_IP2LOCATION_API_KEY'; // @user-config // Your IP2Location.io API key 
    $url = "https://api.ip2location.io/?key={$apiKey}&ip={$ip}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($data && isset($data['country_code'])) {
        return ['country' => $data['country_name'], 'code' => $data['country_code']];
    }
    return ['country' => 'Unknown', 'code' => 'XX'];
}