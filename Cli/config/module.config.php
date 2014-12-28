<?php
namespace Cli;

return array(
    'controllers' => array(
        'invokables' => array(
            'cli' => 'Cli\Controller\CliController',
        ),
    ),

    'console' => array(
        'router' => array(
            'routes' => array(
                'import-messages' => array(
                    'type'    => 'simple',
                    'options' => array(
                        'route'     => 'import messages',
                        'defaults'  => array(
                            'controller' => 'cli',
                            'action'     => 'importMessages',
                        ),
                    ),
                )
            ),
        ),
    ),

    'service_manager' => array(
        'abstract_factories' => array(
            'Cli\Service\Message\ImportAbstractFactory',
            'Cli\Service\Message\RuleAbstractFactory',
        ),
        'invokables' => array(
            'MessageService' => 'Cli\Service\MessageService',
        )
    )
);