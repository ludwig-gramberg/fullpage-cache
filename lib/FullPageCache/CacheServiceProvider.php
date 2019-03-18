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
        }
        return null;
    }

}