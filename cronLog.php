<?php
$handle = fopen('cron/cron.log', 'r');
while ($line = fgets($handle)) {
    echo nl2br($line);
}
fclose($handle);
?>