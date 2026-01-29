<?php
return [
    'xml' => [
        '1req' => ['requests' => 1, 'window' => 1, 'text' => '1 req/s'],
        '2req' => ['requests' => 2, 'window' => 1, 'text' => '2 req/s (default)'],
        '3req' => ['requests' => 3, 'window' => 1, 'text' => '3 req/s (not recommended)'],
        '5req' => ['requests' => 5, 'window' => 1, 'text' => '5 req/s (risky)'],
    ],
    'mysql' => [
        '1req'  => ['requests' => 1, 'window' => 1, 'text' => '1 req/s'],
        '2req'  => ['requests' => 2, 'window' => 1, 'text' => '2 req/s'],
        '5req'  => ['requests' => 5, 'window' => 1, 'text' => '5 req/s (default)'],
        '10req' => ['requests' => 10, 'window' => 1, 'text' => '10 req/s (moderate load)'],
        '15req' => ['requests' => 15, 'window' => 1, 'text' => '15 req/s (high load)'],
        '20req' => ['requests' => 20, 'window' => 1, 'text' => '20 req/s (very high load)'],
        '30req' => ['requests' => 30, 'window' => 1, 'text' => '30 req/s (heavy load, for strong servers)'],
        '50req' => ['requests' => 50, 'window' => 1, 'text' => '50 req/s (extreme, may crash MySQL)'],
    ],
];