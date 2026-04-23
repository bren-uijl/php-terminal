<?php
// cat.php - Safely rewritten
$target = isset($context['args'][0]) ? $context['args'][0] : null;

if (!$target) {
    return ['output' => "\x1b[31mcat: missing file operand\x1b[0m\n", 'exit_code' => 1];
}

$safePath = $context['resolve_path']($context['cwd'], $target);

if (!$safePath) {
    return ['output' => "\x1b[31mcat: $target: Path resolution failed\x1b[0m\n", 'exit_code' => 1];
}

if (!is_file($safePath)) {
    return ['output' => "\x1b[31mcat: $target: No such file or directory\x1b[0m\n", 'exit_code' => 1];
}

$content = @file_get_contents($safePath);
if ($content === false) {
    return ['output' => "\x1b[31mcat: $target: Unable to read file\x1b[0m\n", 'exit_code' => 1];
}

return ['output' => $content . "\n", 'exit_code' => 0];