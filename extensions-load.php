<?php
// ===============================================================================
// PriviMetrics - Extensions Loader
// ===============================================================================

/**
 * Extension Loading System for PriviMetrics
 * * Hook points available for extensions:
 * - before_dashboard_init, after_dashboard_init
 * - before_analytics_save, after_analytics_save
 * - before_render, after_render
 * - custom_dashboard_widget, custom_settings_tab
**/

// EMERGENCY DISABLE: Create a file named 'extensions_off.txt' (path: extensions/) with content 'true' 

class ExtensionLoader {
    private static $instance = null;
    private $extensions = [];
    private $hooks = [];
    private $extensionsDir;
    private $extensionsFile;
    private $killSwitchFile;
    
    private function __construct() {
        $this->extensionsDir = __DIR__ . '/extensions';
        $this->extensionsFile = __DIR__ . '/extensions.xml';
        $this->killSwitchFile = __DIR__ . '/extensions/extensions_off.txt';
        $this->loadExtensions();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadExtensions() {
        if (file_exists($this->killSwitchFile) && strtolower(trim(file_get_contents($this->killSwitchFile))) === 'true') {
            error_log("PriviMetrics: Extensions system is globally disabled via extensions_off.txt");
            return; 
        }

        if (!file_exists($this->extensionsFile)) return;
        
        try {
            $xml = simplexml_load_file($this->extensionsFile);
            if (!$xml) return;
            
            foreach ($xml->extension as $ext) {
                if ((string)$ext->enabled === 'true') {
                    $this->loadExtension((string)$ext->id);
                }
            }
        } catch (Exception $e) {
            error_log('Extension loading error: ' . $e->getMessage());
        }
    }
    
    private function loadExtension($extId) {
        $extDir = $this->extensionsDir . '/' . $extId;
        $mainFile = $extDir . '/main.php';
        if (!is_dir($extDir) || !file_exists($mainFile)) return;
        
        $manifestFile = $extDir . '/manifest.json';
        if (!file_exists($manifestFile)) return;
        
        $manifest = json_decode(file_get_contents($manifestFile), true);
        if (!$manifest || !isset($manifest['id']) || $manifest['id'] !== $extId) return;
        
        try {
            $this->executeExtension($mainFile, $extId);
            $this->extensions[$extId] = $manifest;
        } catch (Exception $e) {
            error_log("Failed to load extension {$extId}: " . $e->getMessage());
        }
    }
    
    private function executeExtension($file, $extId) {
        $ext = [
            'id' => $extId,
            'dir' => $this->extensionsDir . '/' . $extId,
            'registerHook' => function($hook, $callback) use ($extId) {
                $this->registerHook($hook, $callback, $extId);
            },
            'getData' => function($key) use ($extId) {
                return $this->getExtensionData($extId, $key);
            },
            'setData' => function($key, $value) use ($extId) {
                return $this->setExtensionData($extId, $key, $value);
            }
        ];
        
        require_once $file;
    }
    
    private function registerHook($hook, $callback, $extId) {
        if (!isset($this->hooks[$hook])) $this->hooks[$hook] = [];
        $this->hooks[$hook][] = ['callback' => $callback, 'extension' => $extId];
    }
    
    public function runHook($hook, $data = null) {
        if (!isset($this->hooks[$hook])) return $data;
        foreach ($this->hooks[$hook] as $hookData) {
            try {
                $result = call_user_func($hookData['callback'], $data);
                if ($result !== null) $data = $result;
            } catch (Exception $e) {
                error_log("Extension {$hookData['extension']} hook {$hook} error: " . $e->getMessage());
            }
        }
        return $data;
    }
    
    private function getExtensionData($extId, $key) {
        $dataFile = $this->extensionsDir . '/' . $extId . '/data.json';
        if (!file_exists($dataFile)) return null;
        $data = json_decode(file_get_contents($dataFile), true);
        return $data[$key] ?? null;
    }
    
    private function setExtensionData($extId, $key, $value) {
        $dataFile = $this->extensionsDir . '/' . $extId . '/data.json';
        $data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) ?? [] : [];
        $data[$key] = $value;
        return file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    public function getLoadedExtensions() {
        return $this->extensions;
    }
    
    public function isExtensionLoaded($extId) {
        return isset($this->extensions[$extId]);
    }
}

$GLOBALS['extensionLoader'] = ExtensionLoader::getInstance();

function ext_hook($hook, $data = null) {
    global $extensionLoader;
    if (!$extensionLoader) return $data;
    return $extensionLoader->runHook($hook, $data);
}

function ext_loaded($extId) {
    global $extensionLoader;
    return $extensionLoader ? $extensionLoader->isExtensionLoaded($extId) : false;
}

function ext_list() {
    global $extensionLoader;
    return $extensionLoader ? $extensionLoader->getLoadedExtensions() : [];
}