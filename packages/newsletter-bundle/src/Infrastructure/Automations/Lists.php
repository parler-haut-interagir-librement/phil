<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Automations;

use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Exception\ActionFailed;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Subscriber;

/**
 * The run's subscriber and the list an action's option names, on the subscriber's site.
 */
final class Lists
{
    /**
     * @return array{Subscriber, MailingList}
     *
     * @throws ActionFailed when either is not there
     */
    public static function resolve(SubscriberRepository $subscribers, MailingListRepository $lists, ActionContext $context): array
    {
        $subscriber = null === $context->subject ? null : $subscribers->get($context->subject);

        if (!$subscriber instanceof Subscriber) {
            throw ActionFailed::permanent('The run is about no subscriber of the newsletter.');
        }

        $list = $lists->byKey($subscriber->site, $context->options->string('list') ?? '');

        if (!$list instanceof MailingList) {
            throw ActionFailed::permanent("The subscriber's site has no list of that key.");
        }

        return [$subscriber, $list];
    }
}
