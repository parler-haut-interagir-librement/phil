<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\PrivacyBundle\Domain\Attribute\Anonymize;
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

/**
 * A child of single table inheritance: the fields of `Account` are read on `Account`, its own here.
 */
#[ORM\Entity]
class Partner extends Account
{
    #[ORM\Column(nullable: true)]
    #[Anonymize('lorem')]
    public ?string $note = null;

    #[ORM\Column(nullable: true)]
    #[PersonalData('nickname')]
    public ?string $alias = null;
}
