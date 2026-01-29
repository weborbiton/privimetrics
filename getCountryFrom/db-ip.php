<?php
function fetchLocationDBIP($ip) {
    $url = "https://api.db-ip.com/v2/free/{$ip}"; // Free tier endpoint
    $response = @file_get_contents($url);
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['countryName'], $data['countryCode'])) {
            return [
                'country' => $data['countryName'],
                'code' => $data['countryCode']
            ];
        }
    }
    return ['country' => 'Unknown', 'code' => 'XX'];
}
