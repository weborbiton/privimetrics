<?php
function fetchLocationIpstack($ip, $apiKey) {
    $url = "http://api.ipstack.com/{$ip}?access_key={$apiKey}"; // @user-config // Your ipstack API endpoint
    $response = @file_get_contents($url);
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['country_name'], $data['country_code'])) {
            return [
                'country' => $data['country_name'],
                'code' => $data['country_code']
            ];
        }
    }
    return ['country' => 'Unknown', 'code' => 'XX'];
}
