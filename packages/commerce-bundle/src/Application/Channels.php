<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Phil\CommerceBundle\Domain\Exception\UnknownChannel;
use Phil\CommerceBundle\Domain\Model\Channel;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\ChannelRepository;

/**
 * The channels of the application: which one answers a request, and the orders each one opens.
 */
final readonly class Channels
{
    public function __construct(
        private ChannelRepository $channels,
    ) {
    }

    /**
     * The open channel that answers on the request's host, a port after it ignored.
     */
    public function forHost(string $host): Channel
    {
        $hostname = strtolower(explode(':', $host)[0]);

        foreach ($this->channels->all() as $channel) {
            if ($channel->isActive() && $channel->answersOn($hostname)) {
                return $channel;
            }
        }

        throw UnknownChannel::host($hostname);
    }

    public function open(string $code): Channel
    {
        $channel = $this->channels->find($code);

        return $channel instanceof Channel && $channel->isActive() ? $channel : throw UnknownChannel::code($code);
    }

    /**
     * A cart of the channel: in its currency, sold through it, starting in its tax zone.
     */
    public function newOrder(Channel $channel, string $number): Order
    {
        $order = Order::open($number, $channel->currency);
        $order->changeChannel($channel->code);
        $order->changeTaxZone($channel->taxZone());

        return $order;
    }
}
