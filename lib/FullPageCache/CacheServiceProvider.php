<?php
namespace FullPageCache;

use Webframework\Application\DependencyManager;
use Webframework\Provider\ProviderInterface;

class CacheServiceProvider implements ProviderInterface {

    public static function getInstance(string $className, DependencyManager $di, array $settings) {
        switch($className) {

            case 'FullPageCache\\Config' :

                $domains = preg_split('/[\s]*,[\s]*/', $settings['domains'], -1, PREG_SPLIT_NO_EMPTY);
                $schemes = preg_split('/[\s]*,[\s]*/', $settings['schemes'], -1, PREG_SPLIT_NO_EMPTY);

                $config = new Config($domains, $schemes);

                if(array_key_exists('ignore_ssl_errors', $settings) && $settings['ignore_ssl_errors']) {
                    $config->setCacheClientIgnoreSslErrors(true);
                }

                return $config;

            case 'FullPageCache\\Backend' :

                $redisHost = $settings['redis_host'];
                $redisPort = $settings['redis_port'];
                $redisTimeout = $settings['redis_timeout_ms']/1000;
                $redisAuth = array_key_exists('redis_auth', $settings) ? $settings['redis_auth'] : null;

                $backend = new Backend($redisHost, $redisPort, $redisTimeout, $redisAuth);

                return $backend;
        }
        return null;
    }

}