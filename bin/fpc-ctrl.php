<?php
namespace FullPageCache;

// options help

use Webframework\Application\DependencyManager;
use Webframework\Application\Settings;

$usageHelp = <<<HELP

usage: php fpc-worker.php [-h] [-v] -d=<path> <command>
  -h show this help
  -v verbose, print extra output and errors
  -d=<path> app-directory, must point to project root 

<command>
  stats     show cache stats
  flush     flush entire cache (reset cache)
  refresh   request cache to refresh all pages

example:
  php fpc-worker.php -d=/var/www/html -- stats

HELP;

// options

$commands = null;
$options = getopt('hvd::', [], $commands);
$commands = array_slice($argv, $commands);
$command = !empty($commands) ? $commands[0] : null;

$showHelp = array_key_exists('h', $options);
$isVerbose = array_key_exists('v', $options);
$appDirectory = array_key_exists('d', $options) ? $options['d'] : null;

if(!$appDirectory || $showHelp) {
    echo $usageHelp;
    exit;
}

require_once $appDirectory.'/vendor/ludwig-gramberg/webframework/bin/wf-cli-bootstrap.php';

try {
    echo PHP_EOL;
    echo $cli_color("located composer at $composerAutoloadFile", $cli_color_green);
    echo PHP_EOL;

    // ini files and settings

    $pathIniApplication = APP_PATH.'config/application.ini';
    $pathIniCli = APP_PATH.'config/cli.ini';
    $pathIniResources = APP_PATH.'config/resources.ini';

    $settings = new Settings([$pathIniApplication, $pathIniCli, $pathIniResources]);
    $di = new DependencyManager($settings);

    // prep backend
    $backend = $di->getInstance('FullPageCache\\Backend'); /* @var $backend Backend */

    switch($command) {
        case 'stats' :
            $stats = $backend->getStats();
            echo "cache size: ".number_format($stats->getMemoryBytes()/1024,0,'','')." KiB\n";
            echo "pages stored: ".$stats->getNumberOfPages()."\n";
            break;
            
        case 'flush' :
            $backend->flush();
            echo "cache flushed\n";
            break;

        case 'refresh' :
            $backend->refreshAll();
            echo "cache refresh requested\n";
            break;
    }

} catch(\Throwable $e) {

    error_log((string)$e);

    echo PHP_EOL;
    echo $cli_color('an error occured, see error log for further details', $cli_color_red);
    echo PHP_EOL;

    if($isVerbose) {
        echo PHP_EOL;
        echo $e;
    }
}

echo PHP_EOL;
exit;