<?php
namespace Cli\Service\Message;

use Common\Entity\Message,
    Common\Entity\Message\Metadata;

class RuleArticle extends RuleAbstract
{
    public function find(Message $message)
    {
        $this->articleCode($message);
        $this->articleBarcode($message);
        $this->articleUrl($message);
    }

    private function articleCode(Message $message)
    {
        $cproductCodes = array();
        foreach (array($message->getSubject(), $message->getBody()) as $mail) {
            $matches = NULL;
            if (preg_match_all('/[a-zA-Z0-9]{3}-[a-zA-Z0-9]{4}[0-9]{4}/mi', $mail, $matches)) {
                $cproductCodes = array_merge($cproductCodes, $matches[0]);
            }
        }
        $cproductCodes = array_values(array_unique($cproductCodes));
        $cproductCodes = array_map('trim', $cproductCodes);
        $cproductCodes = array_map('strtoupper', $cproductCodes);

        if (empty($cproductCodes)) {
            return;
        }

        $em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_oracle');
        $qb = $em->createQueryBuilder();
        $qb->select('p')
            ->from('Common\Oracle\Product', 'p')
            ->where('upper(p.articlecode) IN (:articlecodes)')
            ->setParameter(':articlecodes', $cproductCodes);

        foreach($qb->getQuery()->getResult() as $product) {
            $metadata = new Metadata();
            $metadata->setMessage($message)
                     ->setType(Metadata::TYPE_PRODUCT)
                     ->setOracleId($product->getId())
                     ->setValue($product->getName());

            if (!$this->metaExists($metadata, $message)) {
                $message->getMetadata()->add($metadata);
            }
        }
    }
    
    private function articleBarcode(Message $message)
    {
        $barcodes = array();
        foreach (array($message->getSubject(), $message->getBody()) as $mail) {
            if (preg_match_all('/[a-zA-Z0-9]{3}-[a-zA-Z0-9]{4}[0-9]{4}/mi', $mail, $matches)) {
                $barcodes = array_merge($barcodes, $matches[0]);
            }
            if (preg_match_all('/[a-zA-Z]{3}-[a-zA-Z]{3}-[a-zA-Z0-9]{8,}/mi', $mail, $matches)) {
                $barcodes = array_merge($barcodes, $matches[0]);
            }
        }
        $barcodes = array_values(array_unique($barcodes));
        $barcodes = array_map('trim', $barcodes);
        $barcodes = array_map('strtoupper', $barcodes);

        if (empty($barcodes)) {
            return;
        }

        $em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_oracle');
        $qb = $em->createQueryBuilder();
        $qb->select('p')
            ->from('Common\Oracle\Product', 'p')
            ->where('upper(p.barcode) IN (:barcode)')
            ->setParameter(':barcode', $barcodes);

        foreach($qb->getQuery()->getResult() as $product) {
            $metadata = new Metadata();
            $metadata->setMessage($message)
                     ->setType(Metadata::TYPE_PRODUCT)
                     ->setOracleId($product->getId())
                     ->setValue($product->getName());

            if (!$this->metaExists($metadata, $message)) {
                $message->getMetadata()->add($metadata);
            }
        }
    }

    private function articleUrl(Message $message)
    {
        $articleIds = array();
        foreach (array($message->getSubject(), $message->getBody()) as $mail) {
            if (preg_match_all('/article\/([0-9]*)\//mi', $mail, $matches)) {
                $articleIds = array_merge($articleIds, $matches[1]);
            }
        }
        $articleIds = array_values(array_unique($articleIds));
        $articleIds = array_map('trim', $articleIds);

        if (empty($articleIds)) {
            return;
        }

        $em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_oracle');
        $qb = $em->createQueryBuilder();
        $qb->select('p')
            ->from('Common\Oracle\Product', 'p')
            ->where('p.id IN (:id)')
            ->setParameter(':id', $articleIds);

        foreach($qb->getQuery()->getResult() as $product) {
            $metadata = new Metadata();
            $metadata->setMessage($message)
                     ->setType(Metadata::TYPE_PRODUCT)
                     ->setOracleId($product->getId())
                     ->setValue($product->getName());

            if (!$this->metaExists($metadata, $message)) {
                $message->getMetadata()->add($metadata);
            }
        }
    }
}