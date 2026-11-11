<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\Phone\PhoneNumber;
use Phil\Phone\PhoneNumberType;
use Phil\PhoneBundle\Infrastructure\Validator\ValidPhoneNumber;

#[ORM\Entity]
class Shop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'phil_phone')]
        public PhoneNumber $phone,
        #[ValidPhoneNumber(types: [PhoneNumberType::Mobile, PhoneNumberType::FixedLineOrMobile], regions: ['CA', 'US'])]
        public ?string $mobile = null,
    ) {
    }
}
