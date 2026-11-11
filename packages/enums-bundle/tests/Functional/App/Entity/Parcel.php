<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\Enums\Collection\EnumSet;
use Phil\EnumsBundle\Infrastructure\Validator\EnumSetOf;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelOption;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelStatus;

/**
 * A parcel: its status, a case the workflow moves, and its options, a set stored as a bitmask and validated.
 */
#[ORM\Entity]
#[ORM\Table(name: 'parcel')]
class Parcel
{
    #[ORM\Id]
    #[ORM\Column]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(enumType: ParcelStatus::class)]
    public ParcelStatus $status = ParcelStatus::Ordered;

    /** @var EnumSet<ParcelOption> */
    #[ORM\Column(type: 'parcel_options')]
    #[EnumSetOf(ParcelOption::class, max: 2)]
    public EnumSet $options;

    public function __construct()
    {
        $this->options = EnumSet::none(ParcelOption::class);
    }
}
