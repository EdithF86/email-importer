<?php
namespace Cli;

use Zend\Console\Adapter\AdapterInterface as Console,
    Zend\ModuleManager\Feature\ConsoleBannerProviderInterface,
    Zend\ModuleManager\Feature\ConsoleUsageProviderInterface,
    Zend\Mvc\MvcEvent;

/**
 * Class that contains standard Module configuration
 *
 * @author free.duerinckx
 *
 */
class Module implements ConsoleUsageProviderInterface
{

    /**
     * This method is defined in ConsoleBannerProviderInterface
     */
    public function getConsoleBanner(Console $console)
    {
        return
            "\n" .
            "==------------------------------------------------------==\n" .
            "                Welcome to IWT In The Dark                \n" .
            "==------------------------------------------------------==\n" .
            "\n"
        ;
    }

    public function getConsoleUsage(Console $console)
    {
        return array(
            'sync cobas <role> <userIds>' => '',
            array('role',       'role (ADMIN, COBAS or GUEST)'),
            array('userIds',    'sync oracle user(s) (e.g. 125|57)'),
            'sync snippets'     => '',
            'sync social stats' => '',
            'sync from oracle'  => '',
            'sync email from oracle' => '',
            'import messages'   => '',
            'find metadata messages' => '',
            'ML calculate niches'  => '',
            'ML dump emails' => '',
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/../../config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__,
                ),
            ),
        );
    }

    public function onBootstrap(MvcEvent $e)
    {
    }

    public function getServiceConfig()
    {
        return array(
            'factories' => array(
                'SyncOracle' => function ($serviceManager) {
                    $syncOracle = new \Cli\Service\SyncOracle();
                    $syncOracle->setServiceLocator($serviceManager)
                               ->connect();
                    
                    return $syncOracle;
                },
            ),
        );
    }
}