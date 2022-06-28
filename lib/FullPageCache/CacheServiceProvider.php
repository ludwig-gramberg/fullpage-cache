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

                // defaults
                
                $defaultRefreshInterval = 600; 
                $expireInterval = 600; 
                $cacheClientFetchTimeout = 30;
                
                if(array_key_exists('refresh_interval', $settings) && preg_match('/^[1-9][0-9]*$/', $settings['refresh_interval'])) {
                    $defaultRefreshInterval = intval($settings['refresh_interval']);
                }
                if(array_key_exists('expire_interval', $settings) && preg_match('/^[1-9][0-9]*$/', $settings['expire_interval'])) {
                    $expireInterval = intval($settings['expire_interval']);
                }
                if(array_key_exists('fetch_timeout', $settings) && preg_match('/^[1-9][0-9]*$/', $settings['fetch_timeout'])) {
                    $cacheClientFetchTimeout = intval($settings['fetch_timeout']);
                }
                
                $config = new Config($domains, $schemes, $defaultRefreshInterval, $expireInterval, $cacheClientFetchTimeout);

                // flags
                
                if(array_key_exists('ignore_ssl_errors', $settings) && $settings['ignore_ssl_errors']) {
                    $config->setCacheClientIgnoreSslErrors(true);
                }
                if(array_key_exists('canonical_trailing_slash', $settings) && $settings['canonical_trailing_slash']) {
                    $config->setCanonicalHasTrailingSlash(true);
                }

                return $config;
        }
        return null;
    }

}