<?php
// echo.php
// $context['args'] contains the arguments

$text = implode(' ', $context['args']);

return [
    'output' => $text . "\n",
    'exit_code' => 0
];