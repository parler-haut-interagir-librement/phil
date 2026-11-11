<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\Url\KnownUrlType;
use Phil\Url\Url;
use Phil\UrlBundle\Infrastructure\Validator\UrlOfType;

#[ORM\Entity]
class Shop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'phil_url')]
        public Url $website,
        #[UrlOfType([KnownUrlType::Facebook, KnownUrlType::Instagram])]
        public ?string $social = null,
    ) {
    }
}
