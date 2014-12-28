<?php
namespace Cli\Service\Message;

use Common\Entity\Message,
    Common\Entity\Message\Address;

use Zend\Mail\Storage\Message as ZendMessage;

class ImportFacebook extends ImportAbstract
{
    public function import()
    {
        //parent::getMetadata();
    }

    protected function getState(ZendMessage $part)
    {
        //$state = null;
    }
}
