<?php
namespace Cli\Controller;

use Zend\Mvc\Controller\AbstractActionController;

class CliController extends AbstractActionController
{

    public function __construct()
    {
    }

    public function importMessagesAction()
    {
        $messageService = $this->getServiceLocator()->get('MessageService');
        $messageService->import();
    }
}