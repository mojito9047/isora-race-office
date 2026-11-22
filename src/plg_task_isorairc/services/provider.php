<?php

\defined('_JEXEC') or die;

use Isora\Plugin\Task\Isorairc\Extension\Isorairc;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin     = new Isorairc($dispatcher, (array) PluginHelper::getPlugin('task', 'isorairc'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
