<?php
namespace Cli\Service\Message;

use Zend\ServiceManager\AbstractFactoryInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

class RuleAbstractFactory implements AbstractFactoryInterface
{

    /**
     * Cache of canCreateServiceWithName lookups
     * @var array
     */
    protected $lookupCache = array();

    /**
     * Determine if we can create a service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param                         $name
     * @param                         $requestedName
     *
     * @return bool
     * @throws \Zend\ServiceManager\Exception\ServiceNotFoundException
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        $className = '\Cli\Service\Message\\' . $requestedName;

        if (!class_exists($className)) {
            $this->lookupCache[$requestedName] = false;
            return false;
        }

        $reflection = new \ReflectionClass($className);
        if (!$reflection->isSubclassOf('\Cli\Service\Message\RuleAbstract')) {
            $this->lookupCache[$requestedName] = false;
            return false;
        }

        $this->lookupCache[$requestedName] = true;
        return true;
    }

    /**
     * Create service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param                         $name
     * @param                         $requestedName
     *
     * @return DoctrineResource
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        $className = '\Cli\Service\Message\\' . $requestedName;

        $model     = new $className();
        $model->setServiceLocator($serviceLocator);

        return $model;
    }
}