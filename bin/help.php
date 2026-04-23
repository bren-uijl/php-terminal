<?php
// help.php

$output = "PHP Web Terminal - Available commands:\n\n";

$output .= "\x1b[1;36mNavigation & View\x1b[0m\n";
$output .= "  \x1b[1;32mls\x1b[0m [-l]          - List files in current directory\n";
$output .= "  \x1b[1;32mcd\x1b[0m <dir>         - Change directory\n";
$output .= "  \x1b[1;32mpwd\x1b[0m              - Print working directory\n";
$output .= "  \x1b[1;32mclear\x1b[0m            - Clear the terminal screen\n\n";

$output .= "\x1b[1;36mFile Management\x1b[0m\n";
$output .= "  \x1b[1;32mcat\x1b[0m <file>       - Read file content\n";
$output .= "  \x1b[1;32mtouch\x1b[0m <file>     - Create a new empty file\n";
$output .= "  \x1b[1;32mmkdir\x1b[0m <dir>      - Create a new directory\n";
$output .= "  \x1b[1;32mrm\x1b[0m [-r] <path>   - Remove file or directory\n\n";

$output .= "\x1b[1;36mSystem\x1b[0m\n";
$output .= "  \x1b[1;32mecho\x1b[0m <text>      - Print text to the terminal\n";
$output .= "  \x1b[1;32mwhoami\x1b[0m           - Print current user\n";
$output .= "  \x1b[1;32mdate\x1b[0m             - Print current date and time\n";
$output .= "  \x1b[1;32madduser\x1b[0m <usr> <pw>- Create a new user account\n";
$output .= "  \x1b[1;32maddpath\x1b[0m <dir>      - Add a directory to system PATH\n";
$output .= "  \x1b[1;32mhelp\x1b[0m             - Show this help message\n";

return [
    'output' => $output,
    'exit_code' => 0
];