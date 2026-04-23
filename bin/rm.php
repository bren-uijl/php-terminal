<?php
// rm.php - Safely rewritten
$target = null;
$recursive = false;

// Simple argument parser for '-r'
foreach ($context['args'] as $arg) {
    if ($arg === '-r' || $arg === '-rf') {
        $recursive = true;
    } else {
        $target = $arg;
    }
}

if (!$target) {
    return ['output' => "\x1b[31mrm: missing operand\x1b[0m\n", 'exit_code' => 1];
}

$safePath = $context['resolve_path']($context['cwd'], $target);

// Refuse to delete the root project
$sysRoot = rtrim($context['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$safePathStrict = rtrim($safePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

if (!$safePath || $safePathStrict === $sysRoot) {
     return ['output' => "\x1b[31mrm: cannot remove '$target': Permission denied or invalid path\x1b[0m\n", 'exit_code' => 1];
}

if (!file_exists($safePath)) {
    return ['output' => "\x1b[31mrm: cannot remove '$target': No such file or directory\x1b[0m\n", 'exit_code' => 1];
}

if (is_dir($safePath)) {
    if (!$recursive) {
        return ['output' => "\x1b[31mrm: cannot remove '$target': Is a directory\x1b[0m\n", 'exit_code' => 1];
    }

    // Safely recursive delete
    $rrmdir = function($dir) use (&$rrmdir) {
        if (is_dir($dir)) {
            $objects = @scandir($dir);
            if ($objects !== false) {
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        $objPath = $dir . DIRECTORY_SEPARATOR . $object;
                        if (is_dir($objPath) && !is_link($objPath))
                            $rrmdir($objPath);
                        else
                            @unlink($objPath);
                    }
                }
            }
            @rmdir($dir);
        }
    };
    $rrmdir($safePath);
} else {
    @unlink($safePath);
}

return ['output' => "", 'exit_code' => 0];