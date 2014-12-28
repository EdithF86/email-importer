<?php
namespace Cli\Service\Message;

use Cli\Service\Message\RFC822;

use Common\Entity\Message,
    Common\Entity\Message\Attachment,
    Common\Entity\Message\Address,
    Common\Entity\Message\State;

use Zend\Mail\Storage\Message as ZendMessage;

class ImportEmail extends ImportAbstract
{
    private static $msgContentType = null;

    public function import()
    {
        $conn = new \Zend\Mail\Storage\Imap(
            $this->getConfig()['support']['customer_mails']
        );

        $em = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');

        $em->getConnection()->beginTransaction();
        $messageRepo = $em->getRepository('\Common\Entity\Message');

        for ($i = $conn->countMessages(); $i; --$i) {
            try {
                $email     = $conn->getMessage($i);

                // check for doubles
                $messageId = isset($email->message_id) ? $email->message_id : $conn->getUniqueId($i);
                $messageId = trim($messageId, " \t\r\n<>");
                $messageId = preg_replace('/[^a-zA-Z0-9-_@]\./', '_', $messageId);

                /*if ($messageId == 'DUB121-W14A620C1763C7BC8BC9DA3A7360@phx.gbl') {
                    $this->message = new Message();
                    $this->message->setCode($messageId)
                            ->setImportType(Message::IMPORT_TYPE_EMAIL)
                            ->setSubject($email->subject)
                            ->setSendedOn(new \DateTime(date('Y-m-d H:i:s', isset($email->date) ? strtotime($email->date) : time())));
                    $body = $this->getBody($email);
                    $this->message->setBody($body);
                    
                    $em->persist($this->message);

                    $this->setEmails($this->parseEmails($email->from), Address::TYPE_FROM);
                    $this->setEmails($this->parseEmails($email->to), Address::TYPE_TO);
                    
                    $em->persist($this->message);
                    $em->flush();
                    $em->getConnection()->commit();
                    die;
                }
                continue;*/

                $date      = new \DateTime(date('Y-m-d H:i:s', isset($email->date) ? strtotime($email->date) : time()));

                if ($messageRepo->findBy(array('code' => $messageId))) {
                    continue;
                }

                $from      = $this->parseEmails($email->from);
                if ($from[0]->host == 'archonia.com') {
                    continue;
                }

                // create the message
                $this->message = new Message();
                
                // set the email addresses
                $this->setEmails($this->parseEmails($email->from), Address::TYPE_FROM);
                $this->setEmails($this->parseEmails($email->to), Address::TYPE_TO);

                if (isset($email->cc) && !empty($email->cc)) {
                    $this->setEmails($this->parseEmails($email->cc), Address::TYPE_CC);
                }

                if (isset($email->bcc) && !empty($email->bcc)) {
                    $this->setEmails($this->parseEmails($email->bcc), Address::TYPE_BCC);
                }

                // set message data
                $this->message->setCode($messageId)
                        ->setImportType(Message::IMPORT_TYPE_EMAIL)
                        ->setSubject($email->subject)
                        ->setSendedOn($date);

                $body = $this->getBody($email);
                $this->message->setBody($body);
               
                $em->persist($this->message);
                $em->flush();

                parent::rules($em);

                // parent message
                if (isset($email->References)) {
                    $messageParent = $messageRepo->findOneBy(array('code' => $email->References));
                    if ($messageParent) {
                        $this->message->setParent($messageParent);

                        $msgProfile = $messageParent->getMessageProfiles()->filter(function($entry) {
                            if ($entry->getOwner()) {
                                return true;
                            } else {
                                return false;
                            }
                        });

                        if ($msgProfile->count() === 1) {
                            $messageProfile = new Message\Profile();
                            $messageProfile->setProfile($msgProfile->first()->getProfile())
                                           ->setOwner(true)
                                           ->setMessage($this->message);
                            $this->message->getMessageProfiles()->add($messageProfile);

                            $state      = new Message\State();
                            $state->setMessage($this->message)
                                  ->setState(Message\State::STATE_OWNED);
                            $this->message->getStates()->add($state);

                            $em->persist($this->message);
                            $em->flush();
                        }
                    }
                }

                $em->getConnection()->commit();
            } catch (\Exception $e) {
                \Common\Service\Logger::getInstance()->err('mail import error' . $this->message->getId(), array(
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                ));

                $em->getConnection()->rollback();

                // make sure our em is still open. Due to hacky emails, inserts can go wrong and make to em closed.
                if (!$em->isOpen()) {
                    $em = $em->create($em->getConnection(), $em->getConfiguration());
                }
            }
            $em->getConnection()->beginTransaction();
        }
    }

    /**
     * 
     * @param \Zend\Mail\Storage\Message $email
     * @return string
     */
    private function getBody(ZendMessage $email) {
        $bodies = array();

        if ($email->isMultipart()) {
            foreach ($email as $part) {
                if ($part->isMultipart()) {
                    $bodies[] = $this->getBody($part);
                } else {
                    $dummy = $this->getPart($part);
                    if ($dummy) {
                       if (self::$msgContentType) {
                           if (!isset($bodies[self::$msgContentType])) {
                               $bodies[self::$msgContentType] = '';
                           }
                           $bodies[self::$msgContentType] .= $dummy;
                       } else {
                           $bodies[] = $dummy;
                       }
                   }
                   $this->getState($part);
                }
           }
        } else {
           $dummy = $this->getPart($email);
           if ($dummy) {
               $bodies[self::$msgContentType] = $dummy;
           }
           $this->getState($email);
        }

        if (isset($bodies['text/html'])) {
            unset($bodies['text/plain']);
        }

        $body = implode("\r\n\r\n-------------------------\r\n\r\n", $bodies);

        return $body;
    }

    /**
     * @param \Zend\Mail\Storage\Message $part
     * @return null|string
     */
    private function getPart(ZendMessage $part)
    {
        self::$msgContentType = null;

        if (!empty($part->getHeaders()) && isset($part->contenttype)) {
            $dummy = explode(';', $part->contentType);
            $contentType = strtolower($dummy[0]);
            $contentTransferEncoding = isset($part->contentTransferEncoding) ? $part->contentTransferEncoding : NULL;            

            self::$msgContentType = $contentType;
            switch ($contentType) {
                case 'delivery-status':
                case 'rfc822':
                    break;

                case 'text/html':
                case 'text/plain':
                    $charset = NULL;
                    $matches = NULL;
                    if (preg_match('/charset=(.+)/', $part->contentType, $matches)) {
                        $charset = $matches[1];
                    }

                    $content = $part->getContent();

                    if ( $contentTransferEncoding == 'base64' ) {
                        $content = base64_decode($content);
                    } else if ( $contentTransferEncoding == 'quoted-printable' ) {
                        $content = quoted_printable_decode($content);
                    }

                    if (stripos($charset, 'UTF-7') !== false) {
                        $content = mb_convert_encoding($content, 'ISO-8859-1', 'UTF7-IMAP');
                        $content = iconv('ISO-8859-1', 'UTF-8//TRANSLIT//IGNORE', $content);
                    } elseif ($charset === '') {
                        $content = mb_convert_encoding($content, 'UTF-8');
                    } elseif ($charset != 'UTF-8') {
                        $content = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $content);
                    }

                    if ($contentType == 'text/plain') {
                        $content = strip_tags(html_entity_decode(stripslashes($content)));
                        $content = nl2br($content);                        
                    }

                    return $content;
                    break;

                default:
                    $this->saveAttachment($part);
                    break;
            }
        } else {
            return $part->getContent();
        }

        return;
    }

    /**
     * @param \Zend\Mail\Storage\Message $part
     */
    private function saveAttachment(ZendMessage $part)
    {
        @mkdir('./public/files/email/' . $this->message->getCode());

        preg_match('^name=\"(.*?)\"^', $part->contentType, $filename);
        if (!$filename) {
            $filename = uniqid();
        } else {
            $filename = $filename[1];
        }

        try {
            /**
             * other possibilities (not worked out): 7bit, 8bit, binary, ietf-token, x-token
             * http://www.faqs.org/rfcs/rfc2045.html (part 6.1)
             */
            if (isset($part->contenttransferencoding)) {
                $fp = fopen('./public/files/email/' . $this->message->getCode() . '/' . $filename, 'w');
                if (strtolower($part->contenttransferencoding) == 'base64') {
                    fwrite($fp, base64_decode($part->getContent()));
                } elseif (strtolower($part->contenttransferencoding) == 'quoted-printable') {
                    $fp = fopen('./public/files/email/' . $this->message->getCode() . '/' . $filename, 'w');
                    fwrite($fp, quoted_printable_decode($part->getContent()));
                }
                fclose($fp);
            } else {
                $fp = fopen('./public/files/email/' . $this->message->getCode() . '/' . $filename, 'w');
                fwrite($fp, $part->getContent());
                fclose($fp);
            }

            $link       = $this->getServiceLocator()->get('config')['api']['url'] . '/files/email/' . $this->message->getCode() . '/' . $filename;
            $file       = './public/files/email/' . $this->message->getCode() . '/' . $filename;
            $attachment = new Attachment();
            $attachment->setFile($file)
                       ->setLink($link)
                       ->setMessage($this->message);
            $this->message->getAttachments()->add($attachment);
            
        } catch (\Exception $e) {
            \Common\Service\Logger::getInstance()->err('mail import error attachments' . $this->message->getId(), array(
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ));
        }
    }

    /**
     * @param \Common\Entity\Email $email
     * @param \Zend\Mail\Storage\Message $part
     */
    protected function getState(ZendMessage $part)
    {
        $state = null;

        // state auto reply
        if (is_null($state) && !empty($part->getHeaders()) && isset($part->autosubmitted)) {
            $state = State::STATE_AUTO_REPLY;
        }

        // state delivery notice
        if (!empty($part->getHeaders()) && isset($part->contenttype)) {
            $dummy = explode(';', $part->contentType);
            if (stripos($dummy[0], 'delivery-status') !== FALSE) {
                $state = State::STATE_DELIVERY_NOTICE;
            }
        }

        // state spam
        if (is_null($state) && !empty($part->getHeaders()) && isset($part->subject)) {
            if (stripos($part->subject, 'spam-suspect') !== FALSE) {
                $state = State::STATE_SPAM;
            }
        }

        // state paypal
        $from = $this->message->getAddresses()->filter(
            function($entry) {
                return $entry->getType() == Address::TYPE_FROM;
            }
        )->first();
        if (strcasecmp($from->getAddress(), 'member@paypal.be') === 0) {
            $state = State::STATE_PAYPAL;
        }
        
        // state ups
        $from = $this->message->getAddresses()->filter(
            function($entry) {
                return $entry->getType() == Address::TYPE_FROM;
            }
        )->first();
        if (strcasecmp($from->getAddress(), 'auto-notify@ups.com') === 0) {
            $state = State::STATE_UPS;
        }

        // state new
        if (is_null($state)) {
            $state = State::STATE_NEW;
        }

        if ($this->message->getStates()->count() > 0) {
            $currentState = $this->message->getStates()[0]->getState();
            if ($currentState !== State::STATE_NEW) {
                return;
            }
            
            $stateObj = $this->message->getStates()[0];
        } else {
            $stateObj = new State();
        }

        $stateObj->setMessage($this->message)
                 ->setState($state);

        $this->message->getStates()->add($stateObj);
    }

    private function parseEmails($value)
    {
        try {
            $emails =  RFC822::parse($value, NULL, NULL, FALSE);
            return $emails;
        } catch (Exception $e) {
            \Common\Service\Logger::getInstance()->err('mail import error parse emails' . $this->message->getId(), array(
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ));
            return array();
        }
    }
    
    private function setEmails($emails, $type)
    {
        foreach ($emails as $dummy) {
            if (isset($dummy->groupname) && $dummy->groupname == 'undisclosed-recipients') {
                return;
            }
            $dummy->personal = trim(str_replace('"', '', $dummy->personal));
            $address = new Address();
            $address->setAddress(strtolower(trim($dummy->mailbox . '@' . $dummy->host)))
                ->setType($type)
                ->setName($dummy->personal)
                ->setMessage($this->message);
            $this->message->getAddresses()->add($address);
        }
    }
}