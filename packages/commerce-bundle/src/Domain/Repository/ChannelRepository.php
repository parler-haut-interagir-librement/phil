<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Repository;

use Phil\CommerceBundle\Domain\Model\Channel;

/**
 * Finds and stores channels. `Infrastructure\Doctrine\DoctrineChannelRepository` is the Doctrine adapter.
 */
interface ChannelRepository
{
    public function find(string $code): ?Channel;

    /**
     * @return list<Channel> every channel, by code
     */
    public function all(): array;

    public function add(Channel $channel): void;
}
