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

namespace CCDNForum\ForumBundle\Component\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
    
use CCDNForum\ForumBundle\Component\Helper\PostLockHelper;
use CCDNForum\ForumBundle\Entity\Forum;
use CCDNForum\ForumBundle\Entity\Category;
use CCDNForum\ForumBundle\Entity\Board;
use CCDNForum\ForumBundle\Entity\Topic;
use CCDNForum\ForumBundle\Entity\Post;
use CCDNForum\ForumBundle\Entity\Subscription;

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
class Authorizer
{
    /**
     *
     * @access protected
     * @var \CCDNForum\ForumBundle\Component\Helper\PostLockHelper $postLockHelper
     */
    protected $postLockHelper;

    /**
     *
     * @access protected
     * @var \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface $securityAuthorizationChecker
     */
    protected $securityAuthorizationChecker;

    /**
     *
     * @access public
     * @param \Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface $securityAuthorizationChecker
     * @param \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage $securityTokenStorage
     * @param \CCDNForum\ForumBundle\Component\Helper\PostLockHelper    $postLockHelper
     */
    public function __construct(AuthorizationCheckerInterface $securityAuthorizationChecker, TokenStorage $securityTokenStorage, PostLockHelper $postLockHelper)
    {
        $this->securityAuthorizationChecker = $securityAuthorizationChecker;
        $this->securityTokenStorage = $securityTokenStorage;
        $this->postLockHelper = $postLockHelper;
    }

    public function canShowForum(Forum $forum)
    {
        return $forum->isAuthorisedToRead($this->securityAuthorizationChecker);
    }

    public function canShowForumUnassigned()
    {
        return true;
    }

    public function canShowCategory(Category $category, Forum $forum = null)
    {
        if ($forum) {
            if ($category->getForum()) {
                if ($category->getForum()->getId() != $forum->getId()) {
                    return false;
                }
            }

            if (! $this->canShowForum($forum)) {
                return false;
            }
        }

        if (! $category->isAuthorisedToRead($this->securityAuthorizationChecker)) {
            return false;
        }

        return true;
    }

    public function canShowBoard(Board $board, Forum $forum = null)
    {
        if ($board->getCategory()) {
            if (! $this->canShowCategory($board->getCategory(), $forum)) {
                return false;
            }
        }

        if (! $board->isAuthorisedToRead($this->securityAuthorizationChecker)) {
            return false;
        }

        return true;
    }

    public function canCreateTopicOnBoard(Board $board, Forum $forum = null)
    {
        if (! $this->canShowBoard($board, $forum)) {
            return false;
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_USER')) {
            return false;
        }

        if (! $board->isAuthorisedToCreateTopic($this->securityAuthorizationChecker)) {
            return false;
        }

        return true;
    }

    public function canReplyToTopicOnBoard(Board $board, Forum $forum = null)
    {
        if (! $this->canShowBoard($board, $forum)) {
            return false;
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_USER')) {
            return false;
        }

        if (! $board->isAuthorisedToReplyToTopic($this->securityAuthorizationChecker)) {
            return false;
        }

        return true;
    }

    public function canShowTopic(Topic $topic, Forum $forum = null)
    {
        if ($topic->getBoard()) {
            if (! $this->canShowBoard($topic->getBoard(), $forum)) {
                return false;
            }
        }

        if ($topic->isDeleted()) {
            if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
                return false;
            }
        }

        return true;
    }

    public function canReplyToTopic(Topic $topic, Forum $forum = null)
    {
        if ($topic->isClosed()) {
            return false;
        }

        if (! $topic->getBoard()) {
            return false;
        }

        if (! $this->canShowTopic($topic, $forum)) {
            return false;
        }

        if (! $topic->getBoard()->isAuthorisedToReplyToTopic($this->securityAuthorizationChecker)) {
            return false;
        }

        return true;
    }

    public function canDeleteTopic(Topic $topic, Forum $forum = null)
    {
        if ($topic->isDeleted()) {
            return false;
        }

        if (! $this->canShowTopic($topic, $forum) && ! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
            return false;
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        return true;
    }

    public function canRestoreTopic(Topic $topic, Forum $forum = null)
    {
        if (! $topic->isDeleted()) {
            return false;
        }

        if (! $this->canShowTopic($topic, $forum) && ! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
            return false;
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        return true;
    }

    public function canCloseTopic(Topic $topic, Forum $forum = null)
    {
        if ($topic->isClosed()) {
            return false;
        }

        if (! $this->canShowTopic($topic, $forum)) {
            if (! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
                return false;
            }
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        return true;
    }

    public function canReopenTopic(Topic $topic, Forum $forum = null)
    {
        if (! $topic->isClosed()) {
            return false;
        }

        if (! $this->canShowTopic($topic, $forum)) {
            if (! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
                return false;
            }
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        return true;
    }

    public function canTopicChangeBoard(Topic $topic, Forum $forum = null)
    {
        if (! $this->canShowTopic($topic, $forum)) {
            if (! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
                return false;
            }
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        return true;
    }

    public function canStickyTopic(Topic $topic, Forum $forum = null)
    {
        if (! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
            if (! $this->canShowTopic($topic, $forum)) {
                return false;
            }
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        if ($topic->isSticky()) {
            return false;
        }

        return true;
    }

    public function canUnstickyTopic(Topic $topic, Forum $forum = null)
    {
        if (! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
            if (! $this->canShowTopic($topic, $forum)) {
                return false;
            }
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        if (! $topic->isSticky()) {
            return false;
        }

        return true;
    }

    public function canShowPost(Post $post, Forum $forum = null)
    {
        if ($post->getTopic()) {
            if (! $this->canShowTopic($post->getTopic(), $forum)) {
                return false;
            }
        }

        if ($post->isDeleted() && ! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        return true;
    }

    public function canEditPost(Post $post, Forum $forum = null)
    {
        if (! $this->securityAuthorizationChecker->isGranted('ROLE_USER')) {
            return false;
        }

        if (! $this->canShowPost($post, $forum) && ! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
            return false;
        }

        if ($this->postLockHelper->isLocked($post)) {
            if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
                return false;
            }
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
            if (! $post->getCreatedBy()) {
                return false;
            } else {
                if ($post->getCreatedBy()->getId() != $this->securityTokenStorage->getToken()->getUser()->getId()) {
                    return false;
                }
            }
        }

        return true;
    }

    public function canDeletePost(Post $post, Forum $forum = null)
    {
        if ($post->isDeleted()) {
            return false;
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_USER')) {
            return false;
        }

        if (! $this->canShowPost($post, $forum) && ! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        if ($post->isLocked()) {
            if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
                return false;
            }
        }

        if (! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
            if (! $post->getCreatedBy()) {
                return false;
            } else {
                if ($post->getCreatedBy()->getId() != $this->securityTokenStorage->getToken()->getUser()->getId()) {
                    return false;
                }
            }
        }

        return true;
    }

    public function canRestorePost(Post $post, Forum $forum = null)
    {
        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        if (! $post->isDeleted()) {
            return false;
        }

        if (! $this->canShowPost($post, $forum) && ! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        return true;
    }

    public function canLockPost(Post $post, Forum $forum = null)
    {
        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        if (! $this->canShowPost($post, $forum) && ! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
            return false;
        }

        if ($post->isLocked()) {
            return false;
        }

        return true;
    }

    public function canUnlockPost(Post $post, Forum $forum = null)
    {
        if (! $this->securityAuthorizationChecker->isGranted('ROLE_MODERATOR')) {
            return false;
        }

        if (! $this->canShowPost($post, $forum) && ! $this->securityAuthorizationChecker->isGranted('ROLE_ADMIN')) {
            return false;
        }

        if (! $post->isLocked()) {
            return false;
        }

        return true;
    }

    public function canSubscribeToTopic(Topic $topic, Forum $forum = null, Subscription $subscription = null)
    {
        if (! $this->securityAuthorizationChecker->isGranted('ROLE_USER')) {
            return false;
        }

        if (! $this->canShowTopic($topic, $forum)) {
            return false;
        }

        if ($subscription) {
            if ($subscription->getTopic()) {
                if ($subscription->getTopic()->getId() != $topic->getId()) {
                    return false;
                }
            }

            if ($subscription->isSubscribed()) {
                return false;
            }
        }

        return true;
    }

    public function canUnsubscribeFromTopic(Topic $topic, Forum $forum = null, Subscription $subscription = null)
    {
        if (! $this->securityAuthorizationChecker->isGranted('ROLE_USER')) {
            return false;
        }

        if (! $this->canShowTopic($topic, $forum)) {
            return false;
        }

        if ($subscription) {
            if ($subscription->getTopic()) {
                if ($subscription->getTopic()->getId() != $topic->getId()) {
                    return false;
                }
            }

            if (! $subscription->isSubscribed()) {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }
}
