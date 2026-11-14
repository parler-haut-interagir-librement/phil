<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

/**
 * A mapped superclass: its columns belong to each entity that extends it.
 */
#[ORM\MappedSuperclass]
abstract class Person
{
    #[ORM\Column]
    #[PersonalData('person_name')]
    public string $name = '';

    #[ORM\Column]
    public string $createdAt = '';
}
