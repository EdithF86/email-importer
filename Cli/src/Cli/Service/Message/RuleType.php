<?php
namespace Cli\Service\Message;

use Common\Entity\Message,
    Common\Entity\Message\Metadata,
    Common\Entity\Message\Type;

class RuleType extends RuleAbstract
{
    public function find(Message $message)
    {
        $fp = fopen('./data/ml/mail/test.txt', 'w+');
        fwrite($fp, $message->getSubject());
        fwrite($fp, "\n");
        fwrite($fp, strip_tags($message->getBody()));
        fclose($fp);

        $type = new Type();
        $type->setMessage($message);

        $output = trim(shell_exec("python ./python/src/prod_mail_predict.py"));

        if (strcasecmp($output, Type::TYPE_FULFILLMENT) === 0) {
            $type->setType(Type::TYPE_FULFILLMENT);
        } elseif (strcasecmp($output, Type::TYPE_PRODUCT) === 0) {
            $type->setType(Type::TYPE_PRODUCT);
        } else {
            $type->setType(Type::TYPE_UNDEFINED);
        }

        $message->getTypes()->add($type);
    }
}