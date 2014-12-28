<?php
namespace Cli\Service\Message;

use Cli\Service\Message\LanguageDetect,
    Common\Entity\Message;

class RuleProfile extends RuleAbstract
{
    private $_lockedEm;

    public function setEntityManager(\Doctrine\ORM\EntityManager $em)
    {
        $this->_lockedEm = $em;
        return $this;
    }

    public function find(Message $message)
    {
        $fp = fopen('./data/ml/mail/test2.txt', 'w+');
        fwrite($fp, $message->getSubject());
        fwrite($fp, "\n");
        fwrite($fp, strip_tags($message->getBody()));
        fclose($fp);

        $output = trim(shell_exec("python ./python/src/prod_mail2_predict.py"));

        if ($output) {
            $profileRepo = $this->_lockedEm->getRepository('Common\Entity\Profile');
            $profile = $profileRepo->findOneBy(array('id' => $output));

            $msgProfile = new Message\Profile();
            $msgProfile->setMessage($message)
                       ->setOwner(true)
                       ->setProfile($profile);
            $message->getMessageProfiles()->add($msgProfile);

            $state      = new Message\State();
            $state->setMessage($message)
                  ->setState(Message\State::STATE_OWNED);
            $message->getStates()->add($state);
        }
    }
}