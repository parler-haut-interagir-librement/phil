<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceBundle\Domain\Model\Channel;
use Phil\CommerceBundle\Domain\Repository\ChannelRepository;

/**
 * `ChannelRepository` on the Doctrine ORM: a channel is one row of `phil_commerce_channel`, its hostnames in a JSON
 * column; an application has few channels, read whole.
 */
final readonly class DoctrineChannelRepository implements ChannelRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(string $code): ?Channel
    {
        return $this->entityManager->find(Channel::class, $code);
    }

    #[Override]
    public function all(): array
    {
        return $this->entityManager->getRepository(Channel::class)->findBy([], ['code' => 'ASC']);
    }

    #[Override]
    public function add(Channel $channel): void
    {
        $this->entityManager->persist($channel);
    }
}
