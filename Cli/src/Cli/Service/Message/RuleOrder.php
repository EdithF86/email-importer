<?php
namespace Cli\Service\Message;

use Common\Entity\Message,
    Common\Entity\Message\Metadata;

class RuleOrder extends RuleAbstract
{
    public function find(Message $message)
    {
        $corderCodes = array();
        foreach (array($message->getSubject(), $message->getBody()) as $mail) {
            $matches = NULL;
            if (preg_match_all('/\d{4}\/\d{2}-\d{4}\/\d{2}/m', $mail, $matches)) {
                $corderCodes = array_merge($corderCodes, $matches[0]);
            }
        }

        if (empty($corderCodes)) {
            return;
        }

        $em   = $this->getServiceLocator()->get('doctrine.entitymanager.orm_oracle');
        $repo = $em->getRepository('Common\Oracle\Order');
        $orders = $repo->findByCode($corderCodes);

        foreach($orders as $order) {
            // set order
            $metadata = new Metadata();
            $metadata->setMessage($message)
                     ->setType(Metadata::TYPE_ORDER)
                     ->setOracleId($order->getId())
                     ->setValue($order->getCode());
            if (!$this->metaExists($metadata, $message)) {
                $message->getMetadata()->add($metadata);
            }

            // take customer from order
            $metadata = new Metadata();
            $metadata->setMessage($message)
                     ->setType(Metadata::TYPE_CUSTOMER)
                     ->setOracleId($order->getCustomer()->getId())
                     ->setValue($order->getCustomer()->getEmail());
            if (!$this->metaExists($metadata, $message)) {
                $message->getMetadata()->add($metadata);
            }
        }
    }
}