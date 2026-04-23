<?php
// touch.php - Safely rewritten
$target = isset($context['args'][0]) ? $context['args'][0] : null;

if (!$target) {
    return ['output' => "\x1b[31mtouch: missing file operand\x1b[0m\n", 'exit_code' => 1];
}

$safePath = $context['resolve_path']($context['cwd'], $target);

if (!$safePath) {
    return ['output' => "\x1b[31mtouch: cannot touch '$target': Permission denied\x1b[0m\n", 'exit_code' => 1];
}

$success = false;
$errorMsg = "";

set_error_handler(function($errno, $errstr) use (&$errorMsg) {
    $errorMsg = $errstr;
    return true; 
});

$success = touch($safePath);
restore_error_handler();

if ($success) {
    return ['output' => "", 'exit_code' => 0];
}

return ['output' => "\x1b[31mtouch: cannot touch '$target': " . ($errorMsg ?: 'Permission denied') . "\x1b[0m\n", 'exit_code' => 1];