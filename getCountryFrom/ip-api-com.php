<?php
function fetch_location($ip) {
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode"; // Free tier allows 45 requests per minute
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if ($data && ($data['status'] ?? '') === 'success') {
        return ['country' => $data['country'], 'code' => $data['countryCode']];
    }
    return ['country' => 'Unknown', 'code' => 'XX'];
}