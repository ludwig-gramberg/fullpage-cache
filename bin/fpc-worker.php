<?php
namespace FullPageCache;

// options help

use Webframework\Application\DependencyManager;
use Webframework\Application\Settings;

$usageHelp = <<<HELP

usage: php fpc-worker.php [-h] [-v] -d=<path>
  -h show this help
  -v verbose, print extra output and errors
  -d=<path> app-directory, must point to project root 

example:
  php fpc-worker.php -d=/var/www/html

HELP;

// options

$options = getopt('hvd::');
$showHelp = array_key_exists('h', $options);
$isVerbose = array_key_exists('v', $options);
$appDirectory = array_key_exists('d', $options) ? $options['d'] : null;

if(!$appDirectory) {
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

    // prep worker
    $config = $di->getInstance('FullPageCache\\Config'); /* @var $config Config */
    $backend = $di->getInstance('FullPageCache\\Backend'); /* @var $config Backend */

    // init worker
    $worker = new CacheWorker($config, $backend, 'fpc-cache-worker-'.getmypid(), .2, null, 128);
    $worker->run();
    $worker->shutdown();

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