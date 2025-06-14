<?php

namespace App\Core;

class Controller
{
    /**
     * Load a model
     * 
     * @param string $model
     * @return object
     */
    public function model($model) //Doet hetzelfde als de autoloader, maar dan voor models. Waarom zou je dit doen? Waarom niet gewoon de model direct includen? Zie commentaar in de autoloader class.
    {
        $modelClass = "App\\Models\\{$model}";
        return new $modelClass();
    }
    
    /**
     * Load a view
     * 
     * @param string $view
     * @param array $data
     * @return void
     */    public function view($view, $data = [])
    { //Vreselijke code, waarom zou je dit doen? Waarom niet gewoon de view direct includen?
        // Door deze manier krijg je slechte error reporting over welke views je gebruikt, kan je niet snel er naar doorklikken,
        // en het maakt het moeilijker om de views te vinden. Ook zijn je checks voor wat een view nodig heeft weg, en moet je de hele view lezen om te weten welke string-keyed values je moet meegeven. Als er ooit een in een view een variable wordt hernoemd breekt alles.
        // Zie voorbeeld in fase 3, roep elke view direct aan en geef de data direct mee als parameters.


        // Add common data that all views might need
        $data = array_merge($this->getCommonViewData(), $data);
        
        // Extract data to make variables available in view
        extract($data);
        
        // Define the path to the view file
        $viewFile = __DIR__ . "/../Views/{$view}.php";
        
        if (file_exists($viewFile)) {
            // Start output buffering
            ob_start();
            
            // Include the view
            require $viewFile;
            
            // Get the contents of the buffer and clear it
            $content = ob_get_clean();
            
            // Determine layout - check if $layout variable was set in the view file
            // If not, use layout from data, if not, default to 'main'
            $layoutName = isset($layout) ? $layout : (isset($data['layout']) ? $data['layout'] : 'main');
            
            // Use the determined layout
            $layoutFile = __DIR__ . "/../Views/layouts/{$layoutName}.php";
            
            if (file_exists($layoutFile)) {
                // Make content available to layout along with other data
                $data['content'] = $content;
                extract($data);
                require $layoutFile;
            } else {
                echo $content;
            }
        } else {
            echo "View {$view} not found";
        }
    }
    
    /**
     * Redirect to a specific page
     * 
     * @param string $url
     * @return void
     */
    public function redirect($url) //Waarom?
    {
        header('Location: ' . $url);
        exit();
    }
    
    /**
     * Return JSON response
     * 
     * @param mixed $data
     * @param int $statusCode
     * @return void
     */
    public function json($data, $statusCode = 200) //Mag, check of je dit gebruikt.
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit();
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public function isLoggedIn() //Dit en de rollen logica moet in de model map, maar er een eigen class van, desnoods volledig static, want dit heeft te maken met data validatie enzo.
    {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Check if user has specific role
     * 
     * @param string|array $roles
     * @return bool
     */
    public function hasRole($roles)  //zie vorige comment
    {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        
        if (is_array($roles)) {
            return in_array($_SESSION['user_role'], $roles);
        }
        
        return $_SESSION['user_role'] == $roles;
    }    /**
     * Require authentication to access a page
     * 
     * @return void
     */
    public function requireLogin() //zie vorige comment
    {
        // Session is already configured and started in App.php
        // Just ensure we have a session (should already be active)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Validate session integrity
        self::validateSessionIntegrity();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('/login');
            exit();
        }
          // Additional security check - verify user still exists
        $userModel = $this->model('User');
        $user = $userModel->find($_SESSION['user_id']);
        
        if (!$user) {
            session_destroy();
            session_start();
            $this->redirect('/login');
            exit();
        }
    }
    
    /**
     * Require specific role to access a page
     * 
     * @param string|array $roles
     * @param string $redirect
     * @return void
     */
    public function requireRole($roles, $redirect = '/login') //Acceptabel omdat je veelbestaande doorverwijslogica als method herbruikt.
    {
        if (!$this->hasRole($roles)) {
            $this->redirect($redirect);
        }
    }    /**
     * Get common data that should be available to all views
     * 
     * @return array
     */
    protected function getCommonViewData() // Wat doet dit? Waarom? Waarom staat dit niet in je .env?
    {
        $commonData = [];
        
        // Add Google Maps API key
        try {
            $config = require __DIR__ . '/../../config/app.php';
            $commonData['googleMapsApiKey'] = $config['google_maps']['api_key'] ?? '';
        } catch (\Exception $e) {
            $commonData['googleMapsApiKey'] = '';
        }
        
        // Ensure we always have a string value, never null
        $commonData['googleMapsApiKey'] = (string)($commonData['googleMapsApiKey'] ?? '');
        
        return $commonData;
    }

    /**
     * Get list of countries
     * 
     * @param bool $asArray Whether to return as array with code/name keys (true) or associative array (false)
     * @return array
     */
    protected function getCountries($asArray = false) //Waarvoor nodig? Vervang hardcoded landenlijst met een csv file die makkelijk aangepast kan worden als er bv een land bij komt of weggaat. Maar geen hoge prio feedback.
    {
        $countries = [
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'FR' => 'France',
            'DE' => 'Germany',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'PT' => 'Portugal',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'IE' => 'Ireland',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'JP' => 'Japan',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'MX' => 'Mexico',
            // Add more countries as needed   //Beetje lui dit jongens.
        ];

        if ($asArray) {
            $result = [];
            foreach ($countries as $code => $name) {
                $result[] = ['code' => $code, 'name' => $name];
            }
            return $result;
        }

        return $countries;
    }

    /**
     * Validate CSRF token
     * 
     * @param string $redirect
     * @return void
     */    public function validateCSRF($redirect = null) //Kan je uitleggen wat CSRF is en waarom je deze code nodig hebt? Waarom zou je dit doen?
    {
        $token = $_POST['csrf_token'] ?? '';
        
        if (!View::verifyCSRF($token)) {
            $_SESSION['error'] = 'Invalid security token. Please try again.';
            
            if ($redirect) {
                $this->redirect($redirect);
            } else {
                // Get referring page or default to home
                $referer = $_SERVER['HTTP_REFERER'] ?? '/';
                $this->redirect($referer);
            }
            exit();
        }
    }

    /**
     * Validate input against XSS and injection attacks
     * 
     * @param mixed $data
     * @return mixed
     */
    protected function sanitizeInput($data) //waar gebruik je dit? Kan wel, zet in utility, heeft niks met controllers te maken.
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitizeInput($value);
            }
            return $data;
        }
        
        // Remove null bytes and normalize newlines
        $data = str_replace(chr(0), '', $data);
        $data = str_replace(["\r\n", "\r"], "\n", $data);
        
        // Trim whitespace
        $data = trim($data);
        
        return $data;
    }
    
    /**
     * Validate and sanitize numeric input
     * 
     * @param mixed $value
     * @param float|null $min
     * @param float|null $max
     * @return float|false
     */
    protected function validateNumeric($value, $min = null, $max = null) //zie vorige comment
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $numValue = (float)$value;
        
        if ($min !== null && $numValue < $min) {
            return false;
        }
        
        if ($max !== null && $numValue > $max) {
            return false;
        }
        
        return $numValue;
    }
    
    /**
     * Rate limiting check
     * 
     * @param string $action
     * @param int $maxAttempts
     * @param int $timeWindow
     * @return bool
     */
    protected function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) //Leg uit, onnodig.
    {
        $key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        if (!isset($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }
        
        $now = time();
        
        // Clean old entries
        if (isset($_SESSION['rate_limits'][$key])) {
            $_SESSION['rate_limits'][$key] = array_filter(
                $_SESSION['rate_limits'][$key], 
                function($timestamp) use ($now, $timeWindow) {
                    return ($now - $timestamp) < $timeWindow;
                }
            );
        } else {
            $_SESSION['rate_limits'][$key] = [];
        }
        
        // Check if rate limit exceeded
        if (count($_SESSION['rate_limits'][$key]) >= $maxAttempts) {
            return false;
        }
        
        // Add current attempt
        $_SESSION['rate_limits'][$key][] = $now;
        
        return true;
    }    /**
     * Validate session integrity and detect hijacking
     * 
     * @return void
     */
    protected static function validateSessionIntegrity() //Zie comment bij CSRF
    {
        // Check if user agent changed (possible session hijacking)
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (isset($_SESSION['user_agent'])) {
            if ($_SESSION['user_agent'] !== $currentUserAgent) {
                // Possible session hijacking - destroy session
                session_destroy();
                session_start();
                return;
            }
        } else {
            $_SESSION['user_agent'] = $currentUserAgent;
        }
        
        // Check for session timeout
        if (isset($_SESSION['last_activity'])) {
            $timeout = 1800; // 30 minutes
            if (time() - $_SESSION['last_activity'] > $timeout) {
                session_destroy();
                session_start();
                return;
            }
        }
        
        $_SESSION['last_activity'] = time();
    }
}
