<?php namespace Enstart\Ext\Croute;

use Enstart\Container\ContainerInterface;
use Enstart\ServiceProvider\ServiceProviderInterface;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Register the service provider
     *
     * @param  ContainerInterface $c
     */
    public function register(ContainerInterface $c)
    {
        $c->singleton('Enstart\Ext\Croute\Parser', function ($c) {
            $parser =  (new \Enstart\Ext\Croute\Parser($c->make('Enstart\Router\RouterInterface')))
                ->enabled($c->config->get('croute.enabled', false))
                ->useCache($c->config->get('croute.use_cache', true))
                ->cachePath($c->config->get('croute.cache'))
                ->setControllers($c->config->get('croute.controllers', []));

            return $parser;
        });
        $c->alias('Enstart\Ext\Croute\Parser', 'croute');

        $c->croute->registerControllerRoutes();
    }
}
