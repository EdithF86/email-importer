<?php
namespace Cli\Service\Message;

use Common\Entity\Message,
    Common\Entity\Message\Address,
    Common\Entity\Message\State;

use Zend\Mail\Storage\Message as ZendMessage,
    Zend\ServiceManager\ServiceLocatorInterface;


abstract class ImportAbstract {

    protected $message;

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

    protected function rules(\Doctrine\ORM\EntityManager $lockedEm)
    {
        if ($this->message->getStates()->filter(function($entry) {
                return in_array($entry->getState(),array(
                    State::STATE_SPAM, 
                    State::STATE_DELIVERY_NOTICE, 
                    State::STATE_AUTO_REPLY)
                );
        })->count()) {
            return;
        }

        $rule = $this->getServiceLocator()->get('RuleArticle');
        $rule->find($this->message);
        $rule = $this->getServiceLocator()->get('RuleCustomer');
        $rule->find($this->message);
        $rule = $this->getServiceLocator()->get('RuleOrder');
        $rule->find($this->message);

        $rule = $this->getServiceLocator()->get('RuleType');
        $rule->find($this->message);
        $rule = $this->getServiceLocator()->get('RuleProfile');
        $rule->setEntityManager($lockedEm)->find($this->message);
    }

    public abstract function import();
    
    protected abstract function getState(ZendMessage $part);
}