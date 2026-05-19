<?php
header('Content-Type: text/plain');
echo "=== Git Status ===\n";
echo shell_exec('git status 2>&1');
echo "\n=== Git Log (Last 10) ===\n";
echo shell_exec('git log -n 10 --oneline 2>&1');
echo "\n=== Untracked files ===\n";
echo shell_exec('git ls-files --others --exclude-standard 2>&1');
?>
