<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures;

use Override;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\LocaleDimensions\Locale;

/**
 * A translation Doctrine does not map; its properties let a test map it by hand.
 */
final class LooseTranslation implements Translation
{
    public string $locale;

    public string $text = '';

    public function __construct(
        public Translatable $translatable,
        Locale $locale,
    ) {
        $this->locale = $locale->value;
    }

    #[Override]
    public function translatable(): Translatable
    {
        return $this->translatable;
    }

    #[Override]
    public function locale(): Locale
    {
        return Locale::fromString($this->locale);
    }
}
