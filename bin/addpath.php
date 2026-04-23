<?php
// addpath.php
$newPath = isset($context['args'][0]) ? $context['args'][0] : null;

if (!$newPath) {
    return [
        'output' => "\x1b[31mUsage: addpath <directory_path>\x1b[0m\n", 
        'exit_code' => 1
    ];
}

$configFile = rtrim($context['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config.json';
$config = $context['config'];

if (!in_array($newPath, $config['paths'])) {
    $config['paths'][] = $newPath;
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    return [
        'output' => "\x1b[32mAdded '$newPath' to system PATH configuration.\x1b[0m\n", 
        'exit_code' => 0
    ];
}

return [
    'output' => "\x1b[33mPath '$newPath' is already in PATH.\x1b[0m\n", 
    'exit_code' => 0
];