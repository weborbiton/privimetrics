<?php
// ===============================================================================
// PriviMetrics - Storage Abstraction Layer
// ===============================================================================
require_once 'functions.php';

class StorageManager {
    private $config;
    private $pdo = null;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Get database connection (lazy initialization)
     */
    private function getConnection() {
        if ($this->pdo === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=utf8mb4',
                    $this->config['mysql_host'],
                    $this->config['mysql_database']
                );
                
                $this->pdo = new PDO(
                    $dsn,
                    $this->config['mysql_username'],
                    $this->config['mysql_password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                error_log('MySQL connection failed: ' . $e->getMessage() . ' | Config: ' . json_encode([
                    'host' => $this->config['mysql_host'],
                    'database' => $this->config['mysql_database'],
                    'username' => $this->config['mysql_username']
                ]));
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }
        return $this->pdo;
    }
    
    /**
     * Save tracking data
     */
    public function saveTracking($storageType, $siteData, $trackingData) {
        if ($storageType === 'mysql') {
            return $this->saveToMySQL($siteData, $trackingData);
        } else {
            return $this->saveToXML($siteData, $trackingData);
        }
    }
    
    /**
     * Load analytics data
     */
    public function loadAnalytics($storageType, $siteData, $dateRange) {
        if ($storageType === 'mysql') {
            return $this->loadFromMySQL($siteData, $dateRange);
        } else {
            return $this->loadFromXML($siteData, $dateRange);
        }
    }
    
    /**
     * Save to XML (existing implementation)
     */
    private function saveToXML($siteData, $trackingData) {
        $trackingData = ext_hook('before_analytics_save', $trackingData);
        $baseDir = rtrim($this->config['data_dir'], '/');
        $siteDir = $baseDir . '/' . $siteData['id'];
        if (!is_dir($siteDir)) mkdir($siteDir, 0755, true);

        $file = $siteDir . '/' . gmdate('Y-m-d') . '.xml';
        $lockFile = $file . '.lock';

        $fp = fopen($lockFile, 'c');
        if (!$fp) return false;

        if (flock($fp, LOCK_EX)) {
            $xml = loadXMLFile($file, 'analytics');

            $existingVisit = null;
            foreach ($xml->visit as $visit) {
                if ((string)$visit->user_hash === $trackingData['user_hash']) {
                    $existingVisit = $visit;
                    break;
                }
            }

            $now = new DateTime('now', new DateTimeZone('UTC'));
            $entryData = [
                'timestamp' => $now->getTimestamp(),
                'date' => $now->format('Y-m-d'),
                'hour' => $now->format('H'),
                'page_url' => $trackingData['page_url'],
                'title' => $trackingData['page_title']
            ];

            if ($existingVisit) {
                if (!isset($existingVisit->visits)) $existingVisit->addChild('visits');
                $entry = $existingVisit->visits->addChild('entry');

                foreach ($entryData as $k => $v) {
                    $entry->addChild($k, htmlspecialchars((string)$v, ENT_XML1, 'UTF-8'));
                }
                if (!empty($trackingData['search_query'])) {
                    $entry->addChild('search', htmlspecialchars($trackingData['search_query'], ENT_XML1, 'UTF-8'));
                }
                if (!empty($trackingData['referrer'])) {
                    $entry->addChild('referrer', htmlspecialchars($trackingData['referrer'], ENT_XML1, 'UTF-8'));
                }
            } else {
                $v = $xml->addChild('visit');
                $v->addChild('id', generateID());
                $v->addChild('site_id', $siteData['id']);
                $v->addChild('ip', $trackingData['ip']);
                $v->addChild('country', $trackingData['country']);
                $v->addChild('country_code', $trackingData['country_code']);
                $v->addChild('user_agent', $trackingData['user_agent']);
                $v->addChild('user_hash', $trackingData['user_hash']);

                $visitsNode = $v->addChild('visits');
                $entry = $visitsNode->addChild('entry');

                foreach ($entryData as $k => $val) {
                    $entry->addChild($k, htmlspecialchars((string)$val, ENT_XML1, 'UTF-8'));
                }
                if (!empty($trackingData['search_query'])) {
                    $entry->addChild('search', htmlspecialchars($trackingData['search_query'], ENT_XML1, 'UTF-8'));
                }
                if (!empty($trackingData['referrer'])) {
                    $entry->addChild('referrer', htmlspecialchars($trackingData['referrer'], ENT_XML1, 'UTF-8'));
                }
            }

            file_put_contents($file, $xml->asXML());
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        ext_hook('after_analytics_save', $trackingData);
        return true;
    }
    
    /**
     * Save to MySQL - Fixed to use correct single-table schema
     */
    private function saveToMySQL($siteData, $trackingData) {
        try {
            $trackingData = ext_hook('before_analytics_save', $trackingData);

            $pdo = $this->getConnection();
            
            $now = new DateTime('now', new DateTimeZone('UTC'));
            
            // Insert analytics entry
            $stmt = $pdo->prepare('
                INSERT INTO analytics (
                    id, site_id, ip, country, country_code, user_agent, user_hash,
                    timestamp, date, hour, page_url, page_title, referrer, search_query
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $id = uniqid(bin2hex(random_bytes(4)), true);
            
            $stmt->execute([
                $id,
                $siteData['id'],
                $trackingData['ip'],
                $trackingData['country'],
                $trackingData['country_code'],
                $trackingData['user_agent'],
                $trackingData['user_hash'],
                $now->getTimestamp(),
                $now->format('Y-m-d'),
                (int)$now->format('H'),
                $trackingData['page_url'],
                $trackingData['page_title'],
                $trackingData['referrer'] ?: null,
                $trackingData['search_query'] ?: null
            ]);

            ext_hook('after_analytics_save', $trackingData);
            
            return true;
        } catch (Exception $e) {
            error_log('MySQL save failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Load from XML (existing implementation)
     */
    private function loadFromXML($siteData, $dateRange) {
        $analyticsData = [];
        $siteDir = rtrim($this->config['data_dir'], '/') . '/' . $siteData['id'];

        if (!is_dir($siteDir)) {
            mkdir($siteDir, 0755, true);
            return $analyticsData;
        }

        $files = glob($siteDir . '/*.xml');
        foreach ($files as $file) {
            $fileDateStr = basename($file, '.xml'); 
            $fileTimestamp = strtotime($fileDateStr . ' UTC');

            if ($fileTimestamp >= $dateRange['start'] && $fileTimestamp <= $dateRange['end']) {
                $dayData = loadXMLFile($file, 'analytics');
                if ($dayData && isset($dayData->visit)) {
                    foreach ($dayData->visit as $visit) {
                        if (isset($visit->visits->entry)) {
                            foreach ($visit->visits->entry as $entry) {
                                $analyticsData[] = [
                                    'page_url' => (string)$entry->page_url,
                                    'page_title' => (string)($entry->title ?? ''),
                                    'referrer' => (string)($entry->referrer ?? ''),
                                    'search_query' => (string)($entry->search ?? ''),
                                    'country' => (string)($visit->country ?? 'Unknown'),
                                    'country_code' => (string)($visit->country_code ?? ''),
                                    'hour' => (int)$entry->hour,
                                    'date' => (string)$entry->date
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        return $analyticsData;
    }
    
    /**
     * Load from MySQL - Fixed to use correct single-table schema
     */
    private function loadFromMySQL($siteData, $dateRange) {
        try {
            $pdo = $this->getConnection();
            
            $startDate = date('Y-m-d H:i:s', $dateRange['start']);
            $endDate = date('Y-m-d H:i:s', $dateRange['end']);
            
            $stmt = $pdo->prepare('
                SELECT 
                    page_url,
                    page_title,
                    referrer,
                    search_query,
                    country,
                    country_code,
                    hour,
                    date
                FROM analytics
                WHERE site_id = ? 
                AND FROM_UNIXTIME(timestamp) BETWEEN ? AND ?
                ORDER BY timestamp DESC
            ');
            
            $stmt->execute([$siteData['id'], $startDate, $endDate]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log('MySQL load failed: ' . $e->getMessage());
            return [];
        }
    }
}
