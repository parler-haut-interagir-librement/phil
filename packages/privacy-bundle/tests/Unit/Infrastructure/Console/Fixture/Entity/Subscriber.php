<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

/**
 * A Doctrine entity whose personal field the register finds without being told the class.
 */
#[ORM\Entity]
class Subscriber
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 0;

    #[ORM\Column]
    #[PersonalData('email', retention: 'R-002')]
    public string $email = '';
}
