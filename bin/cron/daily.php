<?php
// bin/cron/daily.php — run via Windows Task Scheduler
require __DIR__ . '/../../public/index.php';
// TODO: fetch trending topics and insert suggestions into DB
echo "Daily cron ran at " . date('c') . PHP_EOL;
