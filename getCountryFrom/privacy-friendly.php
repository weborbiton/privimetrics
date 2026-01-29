<!-- GeoFexCollector by WebOrbiton - Enhanced Privacy-First -->
<!--
    Version: 2.0.0
    Date: 2025.01.17 (YY-MM-DD)
    Developer: WebOrbiton Team
    C: 2025 - 2026 WebOrbion Team
-->
<?php

function fetch_location($ip) {
    $timezone = $_GET['tz'] ?? $_COOKIE['user_timezone'] ?? null;
    $navLang = $_GET['lang'] ?? null;
    $langHeader = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    
    $code = 'XX';
    $method = 'Unknown';

    $timezoneMap = [
        'Europe/Warsaw'=>'PL', 'Europe/Berlin'=>'DE', 'Europe/Paris'=>'FR', 'Europe/London'=>'GB',
        'Europe/Rome'=>'IT', 'Europe/Madrid'=>'ES', 'Europe/Kiev'=>'UA', 'Europe/Kyiv'=>'UA',
        'Europe/Bucharest'=>'RO', 'Europe/Budapest'=>'HU', 'Europe/Prague'=>'CZ', 'Europe/Vienna'=>'AT',
        'Europe/Stockholm'=>'SE', 'Europe/Oslo'=>'NO', 'Europe/Helsinki'=>'FI', 'Europe/Copenhagen'=>'DK',
        'Europe/Dublin'=>'IE', 'Europe/Brussels'=>'BE', 'Europe/Amsterdam'=>'NL', 'Europe/Zurich'=>'CH',
        'Europe/Athens'=>'GR', 'Europe/Lisbon'=>'PT', 'Europe/Tallinn'=>'EE', 'Europe/Riga'=>'LV',
        'Europe/Vilnius'=>'LT', 'Europe/Sofia'=>'BG', 'Europe/Bratislava'=>'SK', 'Europe/Ljubljana'=>'SI',
        'Europe/Zagreb'=>'HR', 'Europe/Belgrade'=>'RS', 'Europe/Sarajevo'=>'BA', 'Europe/Skopje'=>'MK',
        'Europe/Tirane'=>'AL', 'Europe/Luxembourg'=>'LU', 'Europe/Monaco'=>'MC', 'Europe/Malta'=>'MT',
        'Europe/Chisinau'=>'MD', 'Europe/Istanbul'=>'TR', 'Europe/Moscow'=>'RU', 'Europe/Minsk'=>'BY',
        'Europe/Kaliningrad'=>'RU', 'Europe/Samara'=>'RU', 'Europe/Volgograd'=>'RU',
        'Europe/Podgorica'=>'ME', 'Europe/San_Marino'=>'SM', 'Europe/Vatican'=>'VA',
        'Europe/Gibraltar'=>'GI', 'Europe/Andorra'=>'AD', 'Europe/Mariehamn'=>'AX',
        
        'Asia/Shanghai'=>'CN', 'Asia/Chongqing'=>'CN', 'Asia/Urumqi'=>'CN', 'Asia/Hong_Kong'=>'HK',
        'Asia/Macau'=>'MO', 'Asia/Tokyo'=>'JP', 'Asia/Seoul'=>'KR', 'Asia/Pyongyang'=>'KP',
        'Asia/Singapore'=>'SG', 'Asia/Bangkok'=>'TH', 'Asia/Jakarta'=>'ID', 'Asia/Makassar'=>'ID',
        'Asia/Jayapura'=>'ID', 'Asia/Kolkata'=>'IN', 'Asia/Mumbai'=>'IN', 'Asia/Calcutta'=>'IN',
        'Asia/Dubai'=>'AE', 'Asia/Riyadh'=>'SA', 'Asia/Tehran'=>'IR', 'Asia/Jerusalem'=>'IL',
        'Asia/Tel_Aviv'=>'IL', 'Asia/Taipei'=>'TW', 'Asia/Ho_Chi_Minh'=>'VN', 'Asia/Saigon'=>'VN',
        'Asia/Hanoi'=>'VN', 'Asia/Kuala_Lumpur'=>'MY', 'Asia/Manila'=>'PH', 'Asia/Karachi'=>'PK',
        'Asia/Tashkent'=>'UZ', 'Asia/Almaty'=>'KZ', 'Asia/Astana'=>'KZ', 'Asia/Amman'=>'JO',
        'Asia/Beirut'=>'LB', 'Asia/Kuwait'=>'KW', 'Asia/Qatar'=>'QA', 'Asia/Doha'=>'QA',
        'Asia/Muscat'=>'OM', 'Asia/Bahrain'=>'BH', 'Asia/Baghdad'=>'IQ', 'Asia/Damascus'=>'SY',
        'Asia/Dhaka'=>'BD', 'Asia/Colombo'=>'LK', 'Asia/Kathmandu'=>'NP', 'Asia/Yangon'=>'MM',
        'Asia/Rangoon'=>'MM', 'Asia/Phnom_Penh'=>'KH', 'Asia/Vientiane'=>'LA', 'Asia/Brunei'=>'BN',
        'Asia/Dili'=>'TL', 'Asia/Ulaanbaatar'=>'MN', 'Asia/Tbilisi'=>'GE', 'Asia/Yerevan'=>'AM',
        'Asia/Baku'=>'AZ', 'Asia/Bishkek'=>'KG', 'Asia/Dushanbe'=>'TJ', 'Asia/Ashgabat'=>'TM',
        'Asia/Kabul'=>'AF', 'Asia/Thimphu'=>'BT',
        
        'America/New_York'=>'US', 'America/Detroit'=>'US', 'America/Chicago'=>'US',
        'America/Denver'=>'US', 'America/Phoenix'=>'US', 'America/Los_Angeles'=>'US',
        'America/Anchorage'=>'US', 'America/Honolulu'=>'US', 'America/Boise'=>'US',
        'America/Indiana/Indianapolis'=>'US', 'America/Kentucky/Louisville'=>'US',
        'America/Toronto'=>'CA', 'America/Vancouver'=>'CA', 'America/Montreal'=>'CA',
        'America/Edmonton'=>'CA', 'America/Winnipeg'=>'CA', 'America/Halifax'=>'CA',
        'America/St_Johns'=>'CA', 'America/Calgary'=>'CA', 'America/Regina'=>'CA',
        'America/Mexico_City'=>'MX', 'America/Monterrey'=>'MX', 'America/Tijuana'=>'MX',
        'America/Cancun'=>'MX', 'America/Merida'=>'MX',
        'America/Sao_Paulo'=>'BR', 'America/Rio_Branco'=>'BR', 'America/Manaus'=>'BR',
        'America/Belem'=>'BR', 'America/Fortaleza'=>'BR', 'America/Recife'=>'BR',
        'America/Argentina/Buenos_Aires'=>'AR', 'America/Buenos_Aires'=>'AR',
        'America/Cordoba'=>'AR', 'America/Mendoza'=>'AR',
        'America/Santiago'=>'CL', 'America/Bogota'=>'CO', 'America/Lima'=>'PE',
        'America/Caracas'=>'VE', 'America/Asuncion'=>'PY', 'America/Montevideo'=>'UY',
        'America/La_Paz'=>'BO', 'America/Panama'=>'PA', 'America/Costa_Rica'=>'CR',
        'America/Guatemala'=>'GT', 'America/Havana'=>'CU', 'America/Jamaica'=>'JM',
        'America/Port-au-Prince'=>'HT', 'America/Santo_Domingo'=>'DO', 'America/Puerto_Rico'=>'PR',
        'America/Guayaquil'=>'EC', 'America/Tegucigalpa'=>'HN', 'America/Managua'=>'NI',
        'America/San_Salvador'=>'SV', 'America/Belize'=>'BZ',
        
        'Africa/Lagos'=>'NG', 'Africa/Cairo'=>'EG', 'Africa/Johannesburg'=>'ZA',
        'Africa/Cape_Town'=>'ZA', 'Africa/Nairobi'=>'KE', 'Africa/Casablanca'=>'MA',
        'Africa/Algiers'=>'DZ', 'Africa/Tunis'=>'TN', 'Africa/Accra'=>'GH',
        'Africa/Addis_Ababa'=>'ET', 'Africa/Dakar'=>'SN', 'Africa/Luanda'=>'AO',
        'Africa/Kinshasa'=>'CD', 'Africa/Lubumbashi'=>'CD', 'Africa/Khartoum'=>'SD',
        'Africa/Tripoli'=>'LY', 'Africa/Abidjan'=>'CI', 'Africa/Maputo'=>'MZ',
        'Africa/Dar_es_Salaam'=>'TZ', 'Africa/Kampala'=>'UG', 'Africa/Harare'=>'ZW',
        'Africa/Lusaka'=>'ZM', 'Africa/Windhoek'=>'NA', 'Africa/Gaborone'=>'BW',
        
        'Australia/Sydney'=>'AU', 'Australia/Melbourne'=>'AU', 'Australia/Perth'=>'AU',
        'Australia/Adelaide'=>'AU', 'Australia/Brisbane'=>'AU', 'Australia/Hobart'=>'AU',
        'Australia/Darwin'=>'AU', 'Australia/Canberra'=>'AU', 'Australia/ACT'=>'AU',
        'Pacific/Auckland'=>'NZ', 'Pacific/Wellington'=>'NZ', 'Pacific/Chatham'=>'NZ',
        'Pacific/Fiji'=>'FJ', 'Pacific/Guam'=>'GU', 'Pacific/Port_Moresby'=>'PG',
        'Pacific/Tahiti'=>'PF', 'Pacific/Noumea'=>'NC', 'Pacific/Samoa'=>'WS',
        'Pacific/Honolulu'=>'US', 'Pacific/Palau'=>'PW', 'Pacific/Majuro'=>'MH',
    ];

    if ($timezone && isset($timezoneMap[$timezone])) {
        $code = $timezoneMap[$timezone];
        $method = 'Timezone-JS';
    }
    
    elseif ($navLang && preg_match('/^([a-z]{2})-([A-Z]{2})$/i', $navLang, $m)) {
        $code = strtoupper($m[2]);
        $method = 'Navigator-Lang';
    }
    
    elseif (preg_match('/([a-z]{2})-([A-Z]{2})/i', $langHeader, $matches)) {
        $code = strtoupper($matches[2]);
        $method = 'Accept-Lang-Region';
    }
    
    else {
        $code = detectFromFullAcceptLanguage($langHeader);
        $method = $code !== 'XX' ? 'Accept-Lang-Multi' : 'Fallback';
    }
    
    $countries = getCountryList();
    if (!isset($countries[$code])) {
        $code = 'XX';
    }
    
    $countryName = $countries[$code] ?? 'Unknown';

    return [
        'country' => $countryName,
        'code' => $code,
        'timezone' => $timezone ?? 'Unknown',
        'method' => 'Privacy-First (' . $method . ')'
    ];
}

function detectFromFullAcceptLanguage($header) {
    if (empty($header)) return 'XX';
    
    $langToCountry = [
        'pl' => 'PL', 'cs' => 'CZ', 'sk' => 'SK', 'hu' => 'HU', 'ro' => 'RO',
        'bg' => 'BG', 'uk' => 'UA', 'be' => 'BY', 'lt' => 'LT', 'lv' => 'LV',
        'et' => 'EE', 'fi' => 'FI', 'sv' => 'SE', 'no' => 'NO', 'nb' => 'NO',
        'nn' => 'NO', 'da' => 'DK', 'is' => 'IS', 'el' => 'GR', 'tr' => 'TR',
        'he' => 'IL', 'iw' => 'IL', 'ja' => 'JP', 'ko' => 'KR', 'th' => 'TH',
        'vi' => 'VN', 'id' => 'ID', 'ms' => 'MY', 'tl' => 'PH', 'fil' => 'PH',
        'hi' => 'IN', 'bn' => 'BD', 'ta' => 'IN', 'te' => 'IN', 'mr' => 'IN',
        'gu' => 'IN', 'kn' => 'IN', 'ml' => 'IN', 'pa' => 'IN', 'ur' => 'PK',
        'fa' => 'IR', 'ar' => 'SA', 'ka' => 'GE', 'hy' => 'AM', 'az' => 'AZ',
        'kk' => 'KZ', 'uz' => 'UZ', 'tg' => 'TJ', 'km' => 'KH', 'lo' => 'LA',
        'my' => 'MM', 'ne' => 'NP', 'si' => 'LK', 'mn' => 'MN',
        'sl' => 'SI', 'hr' => 'HR', 'sr' => 'RS', 'bs' => 'BA', 'mk' => 'MK',
        'sq' => 'AL', 'mt' => 'MT', 'ga' => 'IE', 'cy' => 'GB', 'gd' => 'GB',
        'eu' => 'ES', 'ca' => 'ES', 'gl' => 'ES',
        'sw' => 'KE', 'am' => 'ET', 'zu' => 'ZA', 'af' => 'ZA', 'xh' => 'ZA',
        
        'zh' => 'CN', 'ru' => 'RU', 'pt' => 'BR', 'de' => 'DE', 'fr' => 'FR',
        'it' => 'IT', 'es' => 'ES', 'nl' => 'NL', 'en' => 'US',
    ];
    
    $langs = [];
    $parts = explode(',', $header);
    
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;
        
        $q = 1.0;
        if (preg_match('/;q=([0-9.]+)/', $part, $qMatch)) {
            $q = (float)$qMatch[1];
            $part = preg_replace('/;q=[0-9.]+/', '', $part);
        }
        
        $langs[] = ['lang' => trim($part), 'q' => $q];
    }
    
    usort($langs, fn($a, $b) => $b['q'] <=> $a['q']);
    
    foreach ($langs as $item) {
        $lang = strtolower($item['lang']);
        
        if (preg_match('/^([a-z]{2})-([a-z]{2})$/i', $lang, $m)) {
            return strtoupper($m[2]);
        }
        
        $shortLang = substr($lang, 0, 2);
        
        if ($shortLang === 'en' && count($langs) > 1 && $item['q'] < 1.0) {
            continue;
        }
        
        if (isset($langToCountry[$shortLang])) {
            return $langToCountry[$shortLang];
        }
    }
    
    return 'XX';
}

function getCountryList() {
    return [
        'AF'=>'Afghanistan','AX'=>'Aland Islands','AL'=>'Albania','DZ'=>'Algeria','AS'=>'American Samoa',
        'AD'=>'Andorra','AO'=>'Angola','AI'=>'Anguilla','AQ'=>'Antarctica','AG'=>'Antigua and Barbuda',
        'AR'=>'Argentina','AM'=>'Armenia','AW'=>'Aruba','AU'=>'Australia','AT'=>'Austria','AZ'=>'Azerbaijan',
        'BS'=>'Bahamas','BH'=>'Bahrain','BD'=>'Bangladesh','BB'=>'Barbados','BY'=>'Belarus','BE'=>'Belgium',
        'BZ'=>'Belize','BJ'=>'Benin','BM'=>'Bermuda','BT'=>'Bhutan','BO'=>'Bolivia','BA'=>'Bosnia and Herzegovina',
        'BW'=>'Botswana','BV'=>'Bouvet Island','BR'=>'Brazil','IO'=>'British Indian Ocean Territory','BN'=>'Brunei Darussalam',
        'BG'=>'Bulgaria','BF'=>'Burkina Faso','BI'=>'Burundi','KH'=>'Cambodia','CM'=>'Cameroon','CA'=>'Canada',
        'CV'=>'Cape Verde','KY'=>'Cayman Islands','CF'=>'Central African Republic','TD'=>'Chad','CL'=>'Chile',
        'CN'=>'China','CX'=>'Christmas Island','CC'=>'Cocos (Keeling) Islands','CO'=>'Colombia','KM'=>'Comoros',
        'CG'=>'Congo','CD'=>'Congo, Democratic Republic','CK'=>'Cook Islands','CR'=>'Costa Rica','CI'=>'Côte d\'Ivoire',
        'HR'=>'Croatia','CU'=>'Cuba','CY'=>'Cyprus','CZ'=>'Czech Republic','DK'=>'Denmark','DJ'=>'Djibouti',
        'DM'=>'Dominica','DO'=>'Dominican Republic','EC'=>'Ecuador','EG'=>'Egypt','SV'=>'El Salvador','GQ'=>'Equatorial Guinea',
        'ER'=>'Eritrea','EE'=>'Estonia','ET'=>'Ethiopia','FK'=>'Falkland Islands','FO'=>'Faroe Islands','FJ'=>'Fiji',
        'FI'=>'Finland','FR'=>'France','GF'=>'French Guiana','PF'=>'French Polynesia','TF'=>'French Southern Territories',
        'GA'=>'Gabon','GM'=>'Gambia','GE'=>'Georgia','DE'=>'Germany','GH'=>'Ghana','GI'=>'Gibraltar','GR'=>'Greece',
        'GL'=>'Greenland','GD'=>'Grenada','GP'=>'Guadeloupe','GU'=>'Guam','GT'=>'Guatemala','GG'=>'Guernsey',
        'GN'=>'Guinea','GW'=>'Guinea-Bissau','GY'=>'Guyana','HT'=>'Haiti','HM'=>'Heard Island and McDonald Islands',
        'VA'=>'Holy See','HN'=>'Honduras','HK'=>'Hong Kong','HU'=>'Hungary','IS'=>'Iceland','IN'=>'India','ID'=>'Indonesia',
        'IR'=>'Iran','IQ'=>'Iraq','IE'=>'Ireland','IM'=>'Isle of Man','IL'=>'Israel','IT'=>'Italy','JM'=>'Jamaica',
        'JP'=>'Japan','JE'=>'Jersey','JO'=>'Jordan','KZ'=>'Kazakhstan','KE'=>'Kenya','KI'=>'Kiribati','KP'=>'North Korea',
        'KR'=>'South Korea','KW'=>'Kuwait','KG'=>'Kyrgyzstan','LA'=>'Laos','LV'=>'Latvia','LB'=>'Lebanon','LS'=>'Lesotho',
        'LR'=>'Liberia','LY'=>'Libya','LI'=>'Liechtenstein','LT'=>'Lithuania','LU'=>'Luxembourg','MO'=>'Macao',
        'MK'=>'North Macedonia','MG'=>'Madagascar','MW'=>'Malawi','MY'=>'Malaysia','MV'=>'Maldives','ML'=>'Mali',
        'MT'=>'Malta','MH'=>'Marshall Islands','MQ'=>'Martinique','MR'=>'Mauritania','MU'=>'Mauritius','YT'=>'Mayotte',
        'MX'=>'Mexico','FM'=>'Micronesia','MD'=>'Moldova','MC'=>'Monaco','MN'=>'Mongolia','ME'=>'Montenegro','MS'=>'Montserrat',
        'MA'=>'Morocco','MZ'=>'Mozambique','MM'=>'Myanmar','NA'=>'Namibia','NR'=>'Nauru','NP'=>'Nepal','NL'=>'Netherlands',
        'NC'=>'New Caledonia','NZ'=>'New Zealand','NI'=>'Nicaragua','NE'=>'Niger','NG'=>'Nigeria','NU'=>'Niue','NF'=>'Norfolk Island',
        'MP'=>'Northern Mariana Islands','NO'=>'Norway','OM'=>'Oman','PK'=>'Pakistan','PW'=>'Palau','PS'=>'Palestinian Territory',
        'PA'=>'Panama','PG'=>'Papua New Guinea','PY'=>'Paraguay','PE'=>'Peru','PH'=>'Philippines','PN'=>'Pitcairn','PL'=>'Poland',
        'PT'=>'Portugal','PR'=>'Puerto Rico','QA'=>'Qatar','RE'=>'Réunion','RO'=>'Romania','RU'=>'Russia','RW'=>'Rwanda',
        'BL'=>'Saint Barthélemy','SH'=>'Saint Helena','KN'=>'Saint Kitts and Nevis','LC'=>'Saint Lucia','MF'=>'Saint Martin',
        'PM'=>'Saint Pierre and Miquelon','VC'=>'Saint Vincent and the Grenadines','WS'=>'Samoa','SM'=>'San Marino',
        'ST'=>'Sao Tome and Principe','SA'=>'Saudi Arabia','SN'=>'Senegal','RS'=>'Serbia','SC'=>'Seychelles','SL'=>'Sierra Leone',
        'SG'=>'Singapore','SK'=>'Slovakia','SI'=>'Slovenia','SB'=>'Solomon Islands','SO'=>'Somalia','ZA'=>'South Africa',
        'GS'=>'South Georgia','ES'=>'Spain','LK'=>'Sri Lanka','SD'=>'Sudan','SR'=>'Suriname','SJ'=>'Svalbard and Jan Mayen',
        'SZ'=>'Eswatini','SE'=>'Sweden','CH'=>'Switzerland','SY'=>'Syria','TW'=>'Taiwan','TJ'=>'Tajikistan','TZ'=>'Tanzania',
        'TH'=>'Thailand','TL'=>'Timor-Leste','TG'=>'Togo','TK'=>'Tokelau','TO'=>'Tonga','TT'=>'Trinidad and Tobago','TN'=>'Tunisia',
        'TR'=>'Turkey','TM'=>'Turkmenistan','TC'=>'Turks and Caicos Islands','TV'=>'Tuvalu','UG'=>'Uganda','UA'=>'Ukraine',
        'AE'=>'United Arab Emirates','GB'=>'United Kingdom','US'=>'United States','UM'=>'United States Minor Outlying Islands',
        'UY'=>'Uruguay','UZ'=>'Uzbekistan','VU'=>'Vanuatu','VE'=>'Venezuela','VN'=>'Vietnam','VG'=>'British Virgin Islands',
        'VI'=>'U.S. Virgin Islands','WF'=>'Wallis and Futuna','EH'=>'Western Sahara','YE'=>'Yemen','ZM'=>'Zambia','ZW'=>'Zimbabwe',
        'XX'=>'Unknown'
    ];
}
