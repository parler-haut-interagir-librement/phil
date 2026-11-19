<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Form;

use Override;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Between an `Attribution` and its fields; "No modifications" is checked when the changes are the sentence that box
 * writes. Fields that make no attribution — no title, no license, a text the attribution refuses — give none, and leave
 * the form valid: the constraints of `AttributionType` check each field, which a failed transformation would skip.
 *
 * @phpstan-type AttributionFields array{kind: ResourceKind|null, title: string|null, license: string|null, author: string|null, url: string|null, notice: string|null, changes: string|null, unchanged: bool}
 *
 * @implements DataTransformerInterface<Attribution, AttributionFields>
 */
final readonly class AttributionTransformer implements DataTransformerInterface
{
    /**
     * @param string $unchanged the sentence "No modifications" writes in the changes
     */
    public function __construct(
        private string $unchanged,
    ) {
    }

    /**
     * @return AttributionFields
     */
    #[Override]
    public function transform(mixed $value): array
    {
        if (!$value instanceof Attribution) {
            return ['kind' => ResourceKind::Text, 'title' => null, 'license' => null, 'author' => null, 'url' => null, 'notice' => null, 'changes' => null, 'unchanged' => false];
        }

        return [
            'kind' => $value->kind,
            'title' => $value->title,
            'license' => $value->license,
            'author' => $value->author,
            'url' => $value->url,
            'notice' => $value->notice,
            'changes' => $value->changes,
            'unchanged' => $this->unchanged === $value->changes,
        ];
    }

    #[Override]
    public function reverseTransform(mixed $value): ?Attribution
    {
        if (null === $value) {
            return null;
        }

        try {
            return Attribution::of(
                $value['kind'] ?? ResourceKind::Other,
                $value['title'] ?? '',
                $value['license'] ?? '',
                $value['author'],
                $value['url'],
                $value['notice'],
                $value['changes'],
            );
        } catch (InvalidLicensing) {
            return null;
        }
    }
}
