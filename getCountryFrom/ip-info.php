<?php
function fetchLocationIPinfo($ip, $token) {
    $url = "https://ipinfo.io/{$ip}/json?token={$token}"; // @user-config // Your IPinfo API Token
    $response = @file_get_contents($url);
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['country'])) {
            return [
                'country' => $data['country'], // Note: IPinfo usually returns country code
                'code' => $data['country']
            ];
        }
    }
    return ['country' => 'Unknown', 'code' => 'XX'];
}
