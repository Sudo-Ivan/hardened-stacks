#!/usr/bin/env php
<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use Flarum\Foundation\Paths;
use Flarum\Install\Console\FileDataProvider;
use Flarum\Install\Installation;
use Flarum\Install\Step;
use Flarum\Install\StepFailed;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

$flarumHome = getenv('FLARUM_HOME') ?: '/app';
chdir($flarumHome);

require $flarumHome.'/vendor/autoload.php';

$installFile = $argv[1] ?? '';
if ($installFile === '' || !is_readable($installFile)) {
    fwrite(STDERR, "install-flarum.php: install file path required\n");
    exit(1);
}

$paths = new Paths([
    'base' => $flarumHome,
    'public' => $flarumHome.'/public',
    'storage' => $flarumHome.'/storage',
]);

$input = new ArrayInput(
    ['--file' => $installFile],
    new InputDefinition([
        new InputOption('file', 'f', InputOption::VALUE_REQUIRED),
    ])
);

try {
    $provider = new FileDataProvider($input);
    $installation = new Installation($paths);
    $pipeline = $provider->configure($installation)->build();

    $pipeline
        ->on('start', function (Step $step): void {
            fwrite(STDOUT, $step->getMessage()."...\n");
        })
        ->on('end', function (): void {
            fwrite(STDOUT, " done\n");
        })
        ->on('fail', function (): void {
            fwrite(STDOUT, " failed\n");
            fwrite(STDOUT, "Rolling back...\n");
        })
        ->on('rollback', function (Step $step): void {
            fwrite(STDOUT, $step->getMessage()." (rollback)\n");
        })
        ->run();
} catch (StepFailed $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    $previous = $e->getPrevious();
    if ($previous !== null) {
        fwrite(STDERR, $previous->getMessage()."\n");
    }
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
