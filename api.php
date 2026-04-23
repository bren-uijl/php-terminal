<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

// Load configuration
$configFile = __DIR__ . '/config.json';
if (!file_exists($configFile)) {
    echo json_encode(['error' => 'config.json missing']);
    exit;
}

$configContent = file_get_contents($configFile);
$config = json_decode($configContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'config.json contains invalid JSON: ' . json_last_error_msg()]);
    exit;
}

if (!is_array($config['paths'])) {
    http_response_code(500);
    echo json_encode(['error' => 'config.json is incomplete (paths missing)']);
    exit;
}

// Security: Multi-User Auth & Setup Mode
$usersFile = __DIR__ . '/users.json';
$isSetupMode = !file_exists($usersFile) || filesize($usersFile) < 2;

// Parse incoming requests and auth headers
$inputData = json_decode(file_get_contents('php://input'), true);

$username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
$password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

// Fallback for server environments (like FastCGI) that strip PHP_AUTH
if (isset($_SERVER['HTTP_AUTHORIZATION']) && strpos($_SERVER['HTTP_AUTHORIZATION'], 'Basic ') === 0) {
    $decoded = base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6));
    if (strpos($decoded, ':') !== false) {
        list($username, $password) = explode(':', $decoded, 2);
    }
}

// 1. FIRST STARTUP LOGIC
if ($isSetupMode) {
    if (isset($inputData['setup_user']) && isset($inputData['setup_pass'])) {
        $users = [$inputData['setup_user'] => password_hash($inputData['setup_pass'], PASSWORD_DEFAULT)];
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
        echo json_encode(['output' => "\x1b[32mSetup complete! User created successfully.\x1b[0m\n", 'exit_code' => 0]);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['require_setup' => true, 'error' => 'Server requires initial setup.']);
        exit;
    }
}

// 2. NORMAL AUTHENTICATION
$users = json_decode(file_get_contents($usersFile), true);

if (!isset($users[$username]) || !password_verify($password, $users[$username])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication failed. Invalid username or password.']);
    exit;
}

if (!$inputData || !isset($inputData['command_line'])) {
    echo json_encode(['error' => 'No command_line provided']);
    exit;
}

$commandLine = trim($inputData['command_line']);
$cwd = isset($inputData['cwd']) ? $inputData['cwd'] : '/';

// Simple parser that ignores double spaces and respects strings in quotes (").
$pattern = '/(?:[^\s"]+|"[^"]*")+/';
preg_match_all($pattern, $commandLine, $matches);
$parts = $matches[0];

// Remove surrounding quotes from resulting arguments
$parts = array_map(function($arg) {
    if (strpos($arg, '"') === 0 && substr($arg, -1) === '"') {
        return substr($arg, 1, -1);
    }
    return $arg;
}, $parts);

$cmd = array_shift($parts);
$args = $parts;

// Search for the command in the PATH directories
$cmdFile = null;
foreach ($config['paths'] as $pathDir) {
    $potentialPath = __DIR__ . '/' . $pathDir . '/' . basename($cmd) . '.php';
    if (file_exists($potentialPath)) {
        $cmdFile = $potentialPath;
        break;
    }
}

if (!$cmdFile) {
    echo json_encode(['error' => "Command not found: $cmd"]);
    exit;
}

// Secure Path Resolver Helper - Extremely important!
// Ensures scripts absolutely cannot step outside the allowed boundaries.
$sysRoot = realpath(__DIR__);
if ($sysRoot === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to resolve system root directory']);
    exit;
}
$resolvePath = function($virtualCwd, $target = '') use ($sysRoot) {
    $target = (string)$target;
    $virtualCwd = (string)$virtualCwd;
    
    // Determine the raw path
    $isAbsoluteVirtual = (strpos($target, '/') === 0);
    $cwdIsAbsoluteSystem = (preg_match('/^[a-zA-Z]:/', $virtualCwd) || strpos($virtualCwd, '/') === 0 && !isset($sysRoot[0])); // Windows drive or unix root

    if ($target === '' || $target === '.') {
        if (preg_match('/^[a-zA-Z]:/', $virtualCwd)) {
            $rawPath = $virtualCwd;
        } else {
            $rawPath = $sysRoot . '/' . ltrim($virtualCwd, '/');
        }
    } elseif ($isAbsoluteVirtual) {
        $rawPath = $sysRoot . '/' . ltrim($target, '/'); // Absolute virtual relative to project root
    } else {
        // Relative path
        if (preg_match('/^[a-zA-Z]:/', $virtualCwd)) {
            $rawPath = rtrim($virtualCwd, '/\\') . '/' . $target;
        } else {
            $rawPath = $sysRoot . '/' . ltrim($virtualCwd, '/') . '/' . $target;
        }
    }
    
    // Normalize separator
    $rawPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $rawPath);
    
    $realPath = realpath($rawPath);
    
    if ($realPath === false) {
        // If file doesn't exist yet (for touch / mkdir), check if its PARENT directory is valid
        $parent = realpath(dirname($rawPath));
        if ($parent === false) return false;
        
        return rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($rawPath);
    }
    
    // For existing files / directories: return the real path
    return $realPath;
};

// Prepare context for the command scripts
$context = [
    'cwd' => $cwd,
    'args' => $args,
    'config' => $config,
    'root' => $sysRoot,
    'resolve_path' => $resolvePath,
    'user' => $username // Pass the current active user to scripts
];

// Start output buffering (in case the script accidentally uses echo instead of returning)
ob_start();

try {
    // Execute the command script. We expect the file to return an array.
    $result = require $cmdFile;
    $bufferedOutput = ob_get_clean();

    if (!is_array($result)) {
        $result = [
            'output' => $bufferedOutput . (is_string($result) ? $result : ''),
            'exit_code' => 0
        ];
    } else {
        if ($bufferedOutput) {
             $result['output'] = $bufferedOutput . ($result['output'] ?? '');
        }
    }
    
    // Add default success flag if exit_code is not set
    if (!isset($result['exit_code'])) {
        $result['exit_code'] = 0;
    }

        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            echo json_encode(['error' => 'JSON encoding failed: ' . json_last_error_msg(), 'exit_code' => 1]);
        } else {
            echo $json;
        }
    } catch (Throwable $e) {
        ob_end_clean();
        error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        echo json_encode([
            'error' => 'Internal Command Error: ' . $e->getMessage(),
            'exit_code' => 1
        ]);
    }
