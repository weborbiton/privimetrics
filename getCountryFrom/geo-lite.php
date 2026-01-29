<?php
require 'vendor/autoload.php'; // @user-config

use MaxMind\Db\Reader;

function fetch_location($ip) {
    $dbFile = '/path/to/GeoLite2-Country.mmdb'; // @user-config // Path to your GeoLite2 Country database file
    try {
        $reader = new Reader($dbFile);
        $record = $reader->get($ip);
        $reader->close();

        if ($record && isset($record['country']['iso_code'], $record['country']['names']['en'])) {
            return [
                'country' => $record['country']['names']['en'],
                'code' => $record['country']['iso_code']
            ];
        }
    } catch (\Exception $e) {
    }

    return ['country' => 'Unknown', 'code' => 'XX'];
}

// Example usage
$location = fetch_location('8.8.8.8');
print_r($location);
