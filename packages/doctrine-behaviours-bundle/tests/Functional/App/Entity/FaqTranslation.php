<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\DoctrineBehaviours\TranslationEntity;
use Phil\LocaleDimensions\Locale;

#[ORM\Entity]
#[ORM\Table(name: 'faq_translation')]
class FaqTranslation implements Translation
{
    use TranslationEntity;

    #[ORM\Id]
    #[ORM\Column(length: 48)]
    public string $id;

    #[ORM\Column]
    public string $question = '';

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Faq::class)]
        #[ORM\JoinColumn(name: 'faq_id', nullable: false)]
        private Faq $translatable,
        Locale $locale,
    ) {
        $this->id = $translatable->id.'/'.$locale->value;
        $this->writtenIn($locale);
    }

    #[Override]
    public function translatable(): Faq
    {
        return $this->translatable;
    }
}
