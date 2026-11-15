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

/**
 * The action `newsletter.subscribe`: adds the run's subscriber, already confirmed, to a list of their site. Undone by
 * removing them from it, only when this step added them.
 */
final readonly class SubscribeAction implements CompensableAction
{
    public const string TYPE = 'newsletter.subscribe';

    public function __construct(
        private Subscriptions $subscriptions,
        private SubscriberRepository $subscribers,
        private MailingListRepository $lists,
    ) {
    }

    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription(self::TYPE, 'Newsletter: add to a list', [new OptionSpec('list', OptionType::Identifier, true, 'List key')], true);
    }

    #[Override]
    public function execute(ActionContext $context): Payload
    {
        [$subscriber, $list] = Lists::resolve($this->subscribers, $this->lists, $context);

        return Payload::of(['joined' => $this->subscriptions->join($subscriber->id, $list), 'list' => $list->id]);
    }

    #[Override]
    public function compensate(ActionContext $context, Payload $result): void
    {
        $list = $result->identifier('list');

        if (true === $result->get('joined') && null !== $list && null !== $context->subject) {
            $this->subscriptions->leave($context->subject, $list);
        }
    }
}
