<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\PrivacyBundle\Domain\Attribute\Anonymize;
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

#[ORM\Entity]
#[ORM\Table(name: 'account')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['account' => Account::class, 'partner' => Partner::class])]
class Account extends Person
{
    #[ORM\Id]
    #[ORM\Column]
    public int $id = 0;

    #[ORM\Column(name: 'email_address')]
    #[PersonalData('email')]
    #[Anonymize('email', options: ['domain' => 'example.org'])]
    public string $email = '';

    #[ORM\Column]
    #[PersonalData('phone')]
    public string $phone = '';

    #[ORM\Column]
    #[PersonalData('nickname')]
    public string $nickname = '';

    #[PersonalData('secret')]
    public string $notMapped = '';

    #[ORM\Column]
    #[Anonymize('date_shift')]
    public string $birth = '';

    #[ORM\Embedded(class: Postal::class)]
    public Postal $postal;

    public function __construct()
    {
        $this->postal = new Postal();
    }
}
