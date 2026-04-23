<?php
// mkdir.php - Safely rewritten
$target = isset($context['args'][0]) ? $context['args'][0] : null;

if (!$target) {
    return ['output' => "\x1b[31mmkdir: missing operand\x1b[0m\n", 'exit_code' => 1];
}

$safePath = $context['resolve_path']($context['cwd'], $target);

if (!$safePath) {
    return ['output' => "\x1b[31mmkdir: cannot create directory '$target': Permission denied\x1b[0m\n", 'exit_code' => 1];
}

if (file_exists($safePath)) {
    return ['output' => "\x1b[31mmkdir: cannot create directory '$target': File exists\x1b[0m\n", 'exit_code' => 1];
}

$success = false;
$errorMsg = "";

// Temporarily enable error handling via a handler to catch warnings instead of suppressing with @
set_error_handler(function($errno, $errstr) use (&$errorMsg) {
    $errorMsg = $errstr;
    return true; // Stop standard PHP warning
});

$success = mkdir($safePath, 0755, true); // Use 0755 instead of 0777 for safety
restore_error_handler();

if ($success) {
    return ['output' => "", 'exit_code' => 0];
}

return ['output' => "\x1b[31mmkdir: cannot create directory '$target': " . ($errorMsg ?: 'Permission denied') . "\x1b[0m\n", 'exit_code' => 1];