<?php

/*
 * This file is part of the CCDNForum ForumBundle
 *
 * (c) CCDN (c) CodeConsortium <http://www.codeconsortium.com/>
 *
 * Available on github <http://www.github.com/codeconsortium/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CCDNForum\ForumBundle\Component\Dispatcher\Listener;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use CCDNForum\ForumBundle\Component\Dispatcher\ForumEvents;
use CCDNForum\ForumBundle\Component\Dispatcher\Event\UserTopicEvent;

/**
 *
 * @category CCDNForum
 * @package  ForumBundle
 *
 * @author   Reece Fowell <reece@codeconsortium.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @version  Release: 2.0
 * @link     https://github.com/codeconsortium/CCDNForumForumBundle
 *
 */
class SubscriberListener implements EventSubscriberInterface
{
    /**
     *
     * @access private
     * @var \CCDNForum\ForumBundle\Model\FrontModel\SubscriptionModel $subscriptionModel
     */
    protected $subscriptionModel;

    /**
     *
     * @access protected
     * @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $securityTokenStorage
     */
    protected $securityTokenStorage;

    /**
     *
     * @access public
     * @param \CCDNForum\ForumBundle\Model\FrontModel\SubscriptionModel $subscriptionModel
     * @param \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface          $securityTokenStorage
     */
    public function __construct($subscriptionModel, TokenStorageInterface $securityTokenStorage)
    {
        $this->subscriptionModel = $subscriptionModel;
        $this->securityTokenStorage = $securityTokenStorage;
    }

    /**
     *
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            ForumEvents::USER_TOPIC_CREATE_COMPLETE      => 'onTopicCreateComplete',
            ForumEvents::USER_TOPIC_REPLY_COMPLETE       => 'onTopicReplyComplete'
        );
    }

    /**
     *
     * @access public
     * @param \CCDNForum\ForumBundle\Component\Dispatcher\Event\UserTopicEvent $event
     */
    public function onTopicCreateComplete(UserTopicEvent $event)
    {
        if ($event->getTopic()) {
            if ($event->getTopic()->getId() && $event->authorWantsToSubscribe()) {
                $user = $this->securityTokenStorage->getToken()->getUser();

                $this->subscriptionModel->subscribe($event->getTopic(), $user);
            }
        }
    }

    /**
     *
     * @access public
     * @param \CCDNForum\ForumBundle\Component\Dispatcher\Event\UserTopicEvent $event
     */
    public function onTopicReplyComplete(UserTopicEvent $event)
    {
        if ($event->getTopic()) {
            if ($event->getTopic()->getId()) {
                $user = $this->securityTokenStorage->getToken()->getUser();

                if ($event->authorWantsToSubscribe()) {
                    $this->subscriptionModel->subscribe($event->getTopic(), $user);
                }

                $subscriptions = $this->subscriptionModel->findAllSubscriptionsForTopicById($event->getTopic()->getId());

                $this->subscriptionModel->markTheseAsUnread($subscriptions, $user);
            }
        }
    }
}
