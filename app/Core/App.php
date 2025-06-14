<?php

namespace App\Core;

use App\Core\Router;
use App\Core\Request;
use Exception;

class App
{
    protected $router;
    
    /**
     * Create a new application instance
     */
    public function __construct()
    {
        $this->router = new Router();
    }
      /**
     * Initialize the application
     * 
     * @return void
     */    public function init() //Zouden jullie echt kunnen uitleggen wat dit doet en waarom jullie dit zo hebben gedaan?
    {
        // Load environment variables first
        $this->loadEnvironment();
        
        // Configure session security settings BEFORE starting session
        $this->configureSessionSecurity();
        
        // Start session
        if (!session_id()) {
            session_start();
        }
        
        // Load configuration
        $this->loadConfig();
        
        // Set error handling
        $this->setupErrorHandling();
        
        // Load routes
        $this->router->load(__DIR__ . '/../../config/routes.php');
    }
    
    /**
     * Run the application
     * 
     * @return void
     */
    public function run()
    {
        // Get the request URI and method
        $uri = Request::uri();
        $method = Request::method();
        
        // Dispatch the request
        $this->router->dispatch($uri, $method);
    }    /**
     * Load environment variables from .env file
     * 
     * @return void
     */
    protected function loadEnvironment() //Deze code heb je op meerdere plekken verder in je code gedupliceerd.
    {
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue; // Skip comments
                }
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if (preg_match('/^["\'].*["\']$/', $value)) {
                        $value = substr($value, 1, -1);
                    }
                    
                    $_ENV[$key] = $value;
                    putenv($key . '=' . $value);
                }
            }
        }
    }
    
    /**
     * Configure session security settings before starting session
     * 
     * @return void
     */    protected function configureSessionSecurity()
    {
        // Configure session security settings (must be done before session_start)
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        // Change from Strict to Lax to prevent session issues in some browsers
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_lifetime', 0); // Session cookies only
        ini_set('session.gc_maxlifetime', 3600); // 1 hour
        ini_set('session.sid_length', 48); // Longer session IDs
        ini_set('session.sid_bits_per_character', 6); // More entropy
        
        // Set session name
        session_name('MAPIT_SESSION');
    }
    
    /**
     * Load application configuration
     * 
     * @return void
     */
    protected function loadConfig()
    {
        $config = require __DIR__ . '/../../config/app.php';
        
        // Set configuration values
        if (isset($config['timezone'])) {
            date_default_timezone_set($config['timezone']);
        }
        
        // Set display errors
        if (isset($config['display_errors'])) {
            ini_set('display_errors', $config['display_errors']);
        }
    }
    
    /**
     * Set up error handling
     * 
     * @return void
     */
    protected function setupErrorHandling() //Same here, kan je dit uitleggen? Waarom is dit nodig?
    {
        // Set error handler
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            $this->logError($errstr, 'ERROR', [
                'file' => $errfile,
                'line' => $errline,
                'type' => $errno
            ]);
            
            if (error_reporting() === 0) {
                return false;
            }
            
            // Handle error based on environment
            $config = require __DIR__ . '/../../config/app.php';
            if ($config['env'] === 'production') {
                // In production, log the error and display a friendly message
                if ($errno !== E_NOTICE && $errno !== E_WARNING) {
                    http_response_code(500);
                    echo 'An error occurred. Please try again later.';
                    exit;
                }
            } else {
                // In development, display detailed error information
                http_response_code(500);
                echo "<h1>Error</h1>";
                echo "<p><strong>{$errstr}</strong></p>";
                echo "<p>File: {$errfile}</p>";
                echo "<p>Line: {$errline}</p>";
                exit;
            }
            
            return true;
        });
        
        // Set exception handler
        set_exception_handler(function($exception) {
            $this->logError($exception->getMessage(), 'EXCEPTION', [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
            
            // Handle exception based on environment
            $config = require __DIR__ . '/../../config/app.php';
            if ($config['env'] === 'production') {
                // In production, log the exception and display a friendly message
                http_response_code(500);
                echo 'An error occurred. Please try again later.';
            } else {
                // In development, display detailed exception information
                http_response_code(500);
                echo "<h1>Exception</h1>";
                echo "<p><strong>{$exception->getMessage()}</strong></p>";
                echo "<p>File: {$exception->getFile()}</p>";
                echo "<p>Line: {$exception->getLine()}</p>";
                echo "<h2>Stack Trace</h2>";
                echo "<pre>{$exception->getTraceAsString()}</pre>";
            }
            
            exit;
        });
    }
    
    /**
     * Log an error to the database
     * 
     * @param string $message
     * @param string $level
     * @param array $data
     * @return void
     */
    protected function logError($message, $level = 'ERROR', array $data = []) //Same here, kan je uitleggen waarom dit nodig is? En hoe het werkt?
    {
        // Use our Logger class first to ensure the error is captured
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::error($message, $data);
        }
        
        // Skip database logging if we're already handling a database error
        if (strpos($message, 'Database Connection Error') !== false ||
            strpos($message, 'SQL Preparation Error') !== false) {
            return;
        }
        
        try {
            $db = Database::getInstance();
            
            $db->query("
                INSERT INTO logs (level, message, data, component, url) 
                VALUES (:level, :message, :data, :component, :url)
            ");
              $db->bind(':level', $level);
            $db->bind(':message', $message);
            $db->bind(':data', json_encode($data));            // Truncate component to fit database column (assuming 50 chars max)
            $component = $data['file'] ?? null;
            if ($component && strlen($component) > 50) {
                $component = substr($component ?? '', 0, 47) . '...';
            }
            $db->bind(':component', $component);
            $db->bind(':url', $_SERVER['REQUEST_URI'] ?? null);
            
            $db->execute();
        } catch (Exception $e) {
            // Failed to log to database, write to error log instead
            error_log("Failed to log error to database: {$e->getMessage()}");
            error_log("Original error: {$message}");
        }
    }
}
