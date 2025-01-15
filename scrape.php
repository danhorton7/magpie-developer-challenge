<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Scrape;

$scrape = new Scrape();
$scrape->run();
