<?php
function checkLatestVersion12h() {
    $enableVersionCheck = true; // @user-config // Set false to disable
    // If you have disabled this feature, disable notifications from assets/dashboard-template.php ($UpdateNotify)
    
    if (!$enableVersionCheck) {
        return '0.0.1';
    }
    
    $latestFile = __DIR__ . '/latest-version.txt';
    $ttl = 12 * 60 * 60;
    $currentTime = time();

    $latestVersion = '0.0.1';
    $lastCheck = 0;

    if (file_exists($latestFile)) {
        $content = file_get_contents($latestFile);
        if (preg_match('/([0-9.]+)\|([0-9]+)/', trim($content), $matches)) {
            $latestVersion = $matches[1];
            $lastCheck = (int)$matches[2];
        }
    }

    if (($currentTime - $lastCheck) >= $ttl) {
        $url = 'https://files.wbsrv.icu/privimetrics/PriviMetrics.zip?v=' . $currentTime;
        $tmpFile = __DIR__ . '/privimetrics_tmp.zip';
        file_put_contents($tmpFile, file_get_contents($url));
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) === true) {
            if (($index = $zip->locateName('version.txt')) !== false) {
                $versionContent = $zip->getFromIndex($index);
                if (preg_match('/Version:\s*([0-9.]+)/', $versionContent, $matches)) {
                    $latestVersion = $matches[1];
                }
            }
            $zip->close();
        }
        unlink($tmpFile);
        file_put_contents($latestFile, $latestVersion . '|' . $currentTime);
    }

    return $latestVersion;
}