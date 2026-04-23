<?php
// client.php - Local Windows CLI Client for the Remote PHP Terminal

if (php_sapi_name() !== 'cli') {
    die("Error: This client must be started via the command-line (CLI).\n");
}

$configFile = __DIR__ . '/client_credentials.json';
$config = ['host' => '', 'username' => '', 'password' => ''];

if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
}

if (empty($config['host'])) {
    echo "Welcome to the PHP Remote Terminal Client!\n";
    echo "Host URL (e.g. http://yourserver.com/api.php or http://localhost:8000/api.php):\n> ";
    $config['host'] = trim(fgets(STDIN));
}

// Check server status (Setup Mode or Auth Required)
function checkServerStatus() {
    global $config;
    $options = ['http' => ['method' => 'POST', 'header' => "Content-type: application/json\r\n", 'content' => '{}', 'ignore_errors' => true]];
    if (!empty($config['username'])) {
        $options['http']['header'] .= "Authorization: Basic " . base64_encode($config['username'].':'.$config['password']) . "\r\n";
    }
    
    $context = stream_context_create($options);
    $result = @file_get_contents($config['host'], false, $context);
    
    if ($result === false) return false;
    return json_decode($result, true);
}

$status = checkServerStatus();

// SERVER IN FIRST STARTUP MODE!
if (isset($status['require_setup']) && $status['require_setup'] === true) {
    echo "\x1b[33m[!] SERVER REQUIRES INITIAL SETUP\x1b[0m\n";
    echo "Choose an Admin Username:\n> ";
    $setupUser = trim(fgets(STDIN));
    echo "Choose an Admin Password:\n> ";
    $setupPass = trim(fgets(STDIN));
    
    $setupPayload = json_encode(['setup_user' => $setupUser, 'setup_pass' => $setupPass]);
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => $setupPayload,
            'ignore_errors' => true
        ]
    ];
    $result = @file_get_contents($config['host'], false, stream_context_create($options));
    $resDecoded = json_decode($result, true);
    if (isset($resDecoded['exit_code']) && $resDecoded['exit_code'] === 0) {
        echo $resDecoded['output'];
        $config['username'] = $setupUser;
        $config['password'] = $setupPass;
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    } else {
        die("\x1b[31mSetup failed.\x1b[0m\n");
    }
} 
// REGULAR LOGIN REQUIRED
else if (empty($config['username']) || (isset($status['error']) && strpos($status['error'], 'Authentication failed') !== false)) {
    echo "\n\x1b[32m[LOGIN REQUIRED]\x1b[0m\n";
    echo "Username: ";
    $config['username'] = trim(fgets(STDIN));
    echo "Password: ";
    $config['password'] = trim(fgets(STDIN));
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    echo "\x1b[32mCredentials saved locally!\x1b[0m\n\n";
}

$cwd = '/';
$user = $config['username']; 
$hostName = parse_url($config['host'], PHP_URL_HOST);

echo "\x1b[2J\x1b[0;0H"; // Clear screen
echo "\x1b[1;36mConnected to Remote Server: {$config['host']}\x1b[0m\n";
echo "Type \x1b[33m'exit'\x1b[0m or \x1b[33m'quit'\x1b[0m to disconnect.\n\n";

while (true) {
    $prompt = "\x1b[1;32m{$user}\x1b[0m@\x1b[1;32m{$hostName}\x1b[0m \x1b[1;34m{$cwd}\x1b[0m> ";
    echo $prompt;
    
    $input = trim(fgets(STDIN));
    if ($input === 'exit' || $input === 'quit') break;
    if ($input === '') continue;

    $payload = json_encode(['command_line' => $input, 'cwd' => $cwd]);
    $authHeader = "Authorization: Basic " . base64_encode($config['username'].':'.$config['password']);
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n" . $authHeader . "\r\n",
            'method'  => 'POST',
            'content' => $payload,
            'ignore_errors' => true
        ]
    ];
    
    $context  = stream_context_create($options);
    $result = @file_get_contents($config['host'], false, $context);
    
    if ($result === false) {
        echo "\x1b[31m[!] Network Error:\x1b[0m Unable to connect.\n";
        continue;
    }

    $response = json_decode($result, true);
    
    if (isset($response['error'])) {
         echo "\x1b[31mError:\x1b[0m " . $response['error'] . "\n";
         if (strpos($response['error'], 'Authentication failed') !== false) {
             echo "Check your credentials in client_credentials.json!\n";
             break;
         }
    } else {
         if (!empty($response['output'])) {
             echo $response['output'];
             if (substr($response['output'], -1) !== "\n" && substr($response['output'], -4) !== "\x1b[0H") {
                 echo "\n";
             }
         }
         if (isset($response['new_cwd'])) {
             $cwd = $response['new_cwd'];
         }
    }
}