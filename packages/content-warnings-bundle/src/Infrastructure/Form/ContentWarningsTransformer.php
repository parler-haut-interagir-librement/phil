<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Infrastructure\Form;

use Override;
use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\ContentWarning;
use Phil\ContentWarnings\ContentWarnings;
use Phil\ContentWarnings\Exception\InvalidWarning;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * `ContentWarnings` to the theme keys a choice field checks, and back: the warnings shown keep their severity and
 * note, and those of themes the catalogue no longer has are kept unseen. One instance per field, which remembers the
 * warnings it was given.
 *
 * @implements DataTransformerInterface<ContentWarnings, list<string>>
 */
final class ContentWarningsTransformer implements DataTransformerInterface
{
    private ContentWarnings $original;

    public function __construct(
        private readonly Catalogue $catalogue,
    ) {
        $this->original = ContentWarnings::none();
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function transform(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        $this->original = $value;

        return array_values(array_filter($value->themes(), $this->catalogue->has(...)));
    }

    #[Override]
    public function reverseTransform(mixed $value): ?ContentWarnings
    {
        $value ??= [];
        $warnings = [];

        foreach ($this->original as $warning) {
            if (!$this->catalogue->has($warning->theme) || \in_array($warning->theme, $value, true)) {
                $warnings[$warning->theme] = $warning;
            }
        }

        try {
            foreach ($value as $theme) {
                $warnings[$theme] ??= new ContentWarning($theme);
            }

            return [] === $warnings ? null : ContentWarnings::of(...array_values($warnings));
        } catch (InvalidWarning $invalidWarning) {
            throw new TransformationFailedException($invalidWarning->getMessage(), $invalidWarning->getCode(), previous: $invalidWarning);
        }
    }
}
