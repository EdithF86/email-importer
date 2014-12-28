<?php
namespace Cli\Service\Message;

use Common\Entity\Message,
    Common\Entity\Message\Address,
    Common\Entity\Message\Metadata;

class RuleCustomer extends RuleAbstract
{
    public function find(Message $message)
    {
        if ($message->getAddresses()->count() === 0) {
            return;
        }

        foreach($message->getAddresses() as $address) {
            $from = strtolower(trim($address->getAddress()));

            $em   = $this->getServiceLocator()->get('doctrine.entitymanager.orm_oracle');
            $repo = $em->getRepository('Common\Oracle\Customer');
            $customer = $repo->findByEmail($from);

            if (is_null($customer)) {
                return;
            }

            $metadata = new Metadata();
            $metadata->setMessage($message)
                     ->setType(Metadata::TYPE_CUSTOMER)
                     ->setOracleId($customer->getId())
                     ->setValue($customer->getEmail());
            if (!$this->metaExists($metadata, $message)) {
                $message->getMetadata()->add($metadata);
            }
        }
    }
}
