<?php
// ls.php - Safely rewritten
$target = isset($context['args'][0]) ? $context['args'][0] : '';
$cwd = $context['cwd'];

// Safe resolution via the central helper
$safePath = $context['resolve_path']($cwd, $target);

if (!$safePath || !is_dir($safePath)) {
    return [
        'output' => "\x1b[31mls: cannot access '" . ($target ?: $cwd) . "': No such file or directory\x1b[0m\n",
        'exit_code' => 1
    ];
}

$output = "";
$files = @scandir($safePath);
if ($files === false) {
    return [
        'output' => "\x1b[31mls: cannot access '" . ($target ?: $cwd) . "': Unable to read directory\x1b[0m\n",
        'exit_code' => 1
    ];
}
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $isDir = is_dir($safePath . DIRECTORY_SEPARATOR . $file);
    
    // Color directories blue, files green
    if ($isDir) {
        $output .= "\x1b[1;34m" . $file . "/\x1b[0m\n";
    } else {
        $output .= "\x1b[1;32m" . $file . "\x1b[0m\n";
    }
}

return [
    'output' => $output,
    'exit_code' => 0
];