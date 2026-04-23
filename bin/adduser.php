<?php
// adduser.php
$newUser = isset($context['args'][0]) ? $context['args'][0] : null;
$newPass = isset($context['args'][1]) ? $context['args'][1] : null;

if (!$newUser || !$newPass) {
    return [
        'output' => "\x1b[31mUsage: adduser <username> <password>\x1b[0m\n", 
        'exit_code' => 1
    ];
}

$usersFile = rtrim($context['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'users.json';

if (!file_exists($usersFile)) {
    return ['output' => "\x1b[31mError: users.json not found.\x1b[0m\n", 'exit_code' => 1];
}

$users = json_decode(file_get_contents($usersFile), true) ?: [];

if (isset($users[$newUser])) {
    return [
        'output' => "\x1b[31mError: User '$newUser' already exists.\x1b[0m\n", 
        'exit_code' => 1
    ];
}

// Hash password and add
$users[$newUser] = password_hash($newPass, PASSWORD_DEFAULT);
file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

return [
    'output' => "\x1b[32mUser '$newUser' created successfully.\x1b[0m\n", 
    'exit_code' => 0
];