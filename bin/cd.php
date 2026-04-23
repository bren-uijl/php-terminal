<?php
// cd.php - Safely rewritten
$target = isset($context['args'][0]) ? $context['args'][0] : '/';
$cwd = $context['cwd'];

if ($target === '~') {
    $target = '/';
}

$safePath = $context['resolve_path']($cwd, $target);

if (!$safePath) {
    return [
        'output' => "\x1b[31mcd: $target: Path resolution failed\x1b[0m\n",
        'exit_code' => 1
    ];
}

if (!is_dir($safePath)) {
    return [
        'output' => "\x1b[31mcd: $target: No such file or directory\x1b[0m\n",
        'exit_code' => 1
    ];
}

// Transform the real server path to a usable path for the next session
$sysRoot = $context['root'];
$targetPath = realpath($safePath);

if ($targetPath === false) {
    return [
        'output' => "\x1b[31mcd: $target: Cannot resolve path\x1b[0m\n",
        'exit_code' => 1
    ];
}

// Check if the path lies within the sysRoot
if ($sysRoot && strpos($targetPath, $sysRoot) === 0) {
    // It is within the root, make it a virtual path (starting with /)
    $virtualPath = substr($targetPath, strlen($sysRoot));
    $virtualPath = str_replace('\\', '/', $virtualPath);
    if ($virtualPath === '') {
        $virtualPath = '/';
    } elseif ($virtualPath[0] !== '/') {
        $virtualPath = '/' . $virtualPath;
    }
} else {
    // It is outside the root, use the full path with drive letter
    $virtualPath = str_replace('\\', '/', $targetPath);
}

return [
    'output' => '',
    'new_cwd' => $virtualPath,
    'exit_code' => 0
];