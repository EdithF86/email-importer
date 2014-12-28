<?php
namespace Cli\Service\Message;

use Common\Entity\Message,
    Common\Entity\Message\Metadata;

use Zend\ServiceManager\ServiceLocatorInterface;

abstract class RuleAbstract {

    private $serviceLocator;

    /**
     * @param \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    protected function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    protected function getConfig()
    {
        return $this->serviceLocator->get('Config');
    }

    public abstract function find(Message $message);
    
    protected function metaExists(Metadata $metadata, Message $message)
    {
        $exists = $message->getMetadata()->filter(function($entry) use ($metadata) {
            if ($entry->getMessage()->getId() == $metadata->getMessage()->getId() &&
                $entry->getType() == $metadata->getType() &&
                $entry->getOracleId() == $metadata->getOracleId() &&
                $entry->getValue() == $metadata->getValue()
            ) {
                return true;
            } else {
                return false;
            }
        })->count() > 0 ? true : false;

        if ($exists) {
            return true;
        } else {
            return false;
        }
    }
}