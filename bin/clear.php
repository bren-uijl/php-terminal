<?php
// clear.php
// Send the ANSI clear code to the terminal interface.
// \x1b[2J -> Clears entire screen
// \x1b[0;0H -> Moves cursor to top left
// We must provide this properly without \n otherwise the prompt clips weirdly.

return [
    'output' => "\x1b[2J\x1b[0;0H",
    'exit_code' => 0
];