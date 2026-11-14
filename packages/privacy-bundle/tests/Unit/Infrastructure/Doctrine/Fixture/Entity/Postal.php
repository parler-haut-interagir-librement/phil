<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\PrivacyBundle\Domain\Attribute\Anonymize;

/**
 * An embeddable, whose declarations the reader does not follow.
 */
#[ORM\Embeddable]
class Postal
{
    #[ORM\Column]
    #[Anonymize('address')]
    public string $street = '';
}
