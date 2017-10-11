#!/usr/bin/env php
<?php

set_time_limit(0);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/TextAnalyzer.php'

use Symfony\Component\Console\Application;

use TextAnalyzer\Commands\GramCommand;
use TextAnalyzer\Commands\LemmatizeCommand;

$application = new Application();

// ... register commands
$application->add(new GramCommand());
$application->add(new LemmatizeCommand());

$application->run();