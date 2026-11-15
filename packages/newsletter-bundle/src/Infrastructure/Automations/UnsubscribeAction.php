<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Automations;

use Override;
use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Catalogue\OptionType;
use Phil\Automations\Contract\CompensableAction;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;
use Phil\NewsletterBundle\Application\Subscriptions;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Model\MailingList;

/**
 * The action `newsletter.unsubscribe`: removes the run's subscriber from a list of their site. Undone by adding them
 * back, only when this step removed them.
 */
final readonly class UnsubscribeAction implements CompensableAction
{
    public const string TYPE = 'newsletter.unsubscribe';

    public function __construct(
        private Subscriptions $subscriptions,
        private SubscriberRepository $subscribers,
        private MailingListRepository $lists,
    ) {
    }

    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription(self::TYPE, 'Newsletter: remove from a list', [new OptionSpec('list', OptionType::Identifier, true, 'List key')], true);
    }

    #[Override]
    public function execute(ActionContext $context): Payload
    {
        [$subscriber, $list] = Lists::resolve($this->subscribers, $this->lists, $context);

        return Payload::of(['left' => $this->subscriptions->leave($subscriber->id, $list->id), 'list' => $list->id]);
    }

    #[Override]
    public function compensate(ActionContext $context, Payload $result): void
    {
        $listId = $result->identifier('list');
        $list = null === $listId ? null : $this->lists->get($listId);

        if (true === $result->get('left') && $list instanceof MailingList && null !== $context->subject) {
            $this->subscriptions->join($context->subject, $list);
        }
    }
}
