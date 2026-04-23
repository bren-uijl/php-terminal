<?php
// bin/php.php – Run a PHP file inside the sandbox
// Usage: php <path-to-script>
// The first argument is the virtual path to a .php file inside the project root.

$target = $context['args'][0] ?? '';
if ($target === '') {
    return [
        'output' => "\x1b[31mphp: missing file argument\x1b[0m\n",
        'exit_code' => 1
    ];
}

// Resolve the path safely (must stay within the virtual root)
$realPath = $context['resolve_path']($context['cwd'], $target);
if ($realPath === false || substr($realPath, -4) !== '.php' || !is_file($realPath)) {
    return [
        'output' => "\x1b[31mphp: cannot access '{$target}'\x1b[0m\n",
        'exit_code' => 1
    ];
}

// Capture any output produced by the script
ob_start();
$return = include $realPath; // executes the script in the same PHP process
$captured = ob_get_clean();

// Normalise the result format (all commands return an array)
if (!is_array($return)) {
    $return = [
        'output' => $captured,
        'exit_code' => 0
    ];
} else {
    if ($captured) {
        $return['output'] = $captured . ($return['output'] ?? '');
    }
}

return $return;
