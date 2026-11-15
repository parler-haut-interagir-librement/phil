<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The last number given in one series, one row per kind of document, locked while a number is taken.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_document_sequence')]
final class DocumentSequence
{
    #[ORM\Column(name: 'last_number', type: Types::BIGINT)]
    private int $last = 0;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'series', length: 16)]
        public readonly string $series,
    ) {
    }

    public function next(): int
    {
        return ++$this->last;
    }
}
