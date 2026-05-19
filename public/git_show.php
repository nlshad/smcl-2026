<?php
header('Content-Type: text/plain');
echo "=== Git Show 2802a0e ===\n";
echo shell_exec('git show 2802a0e 2>&1');
?>
