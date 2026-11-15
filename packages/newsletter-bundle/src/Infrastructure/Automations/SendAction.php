<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Automations;

use Override;
use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Catalogue\OptionType;
use Phil\Automations\Contract\Action;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Exception\ActionFailed;
use Phil\NewsletterBundle\Application\Message\SendDelivery;
use Phil\NewsletterBundle\Domain\Contract\DeliveryRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The action `newsletter.send`: queues a template, by its key, to the run's subscriber. The delivery's id is drawn
 * from the step's idempotency key, so that a retried step queues it once; whether the subscriber may still receive is
 * checked when the message is sent. A message cannot be unsent: the action has no compensation.
 */
final readonly class SendAction implements Action
{
    public const string TYPE = 'newsletter.send';

    public function __construct(
        private SubscriberRepository $subscribers,
        private DeliveryRepository $deliveries,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription(self::TYPE, 'Newsletter: send a template', [new OptionSpec('template', OptionType::Identifier, true, 'Template key')]);
    }

    #[Override]
    public function execute(ActionContext $context): Payload
    {
        $subscriber = null === $context->subject ? null : $this->subscribers->get($context->subject);

        if (!$subscriber instanceof Subscriber) {
            throw ActionFailed::permanent('The run is about no subscriber of the newsletter.');
        }

        $delivery = new Delivery(Delivery::idForKey($context->idempotencyKey), $subscriber->id, null, $context->options->string('template'), $this->clock->now());

        if ($this->deliveries->create($delivery)) {
            $this->bus->dispatch(new SendDelivery($delivery->id));
        }

        return Payload::of(['delivery' => $delivery->id]);
    }
}
