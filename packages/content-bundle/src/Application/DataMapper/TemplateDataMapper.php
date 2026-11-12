<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\DataMapper;

use Override;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;

/**
 * Writes `TemplateCapable`: `templateKey` on the unlocalized row, so every locale renders the same layout, and
 * `templateData` on the localized row, replaced whole.
 *
 * - `templateKey`: a string, or `null` to clear it.
 * - `templateData`: a map of field names to values, or `null` for no field. The values are the template's,
 *   kept as submitted; a rich-text field is the application's to compile and purify.
 */
final readonly class TemplateDataMapper implements DataMapper
{
    #[Override]
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if ($unlocalized instanceof TemplateCapable && SubmittedData::has($data, 'templateKey')) {
            $unlocalized->setTemplateKey(SubmittedData::string($data, 'templateKey'));
        }

        if ($localized instanceof TemplateCapable && SubmittedData::has($data, 'templateData')) {
            $localized->setTemplateData($this->fields(SubmittedData::map($data, 'templateData') ?? []));
        }
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @return array<string, mixed>
     *
     * @throws InvalidContent
     */
    private function fields(array $fields): array
    {
        $named = [];

        foreach ($fields as $name => $value) {
            if (!\is_string($name)) {
                throw InvalidContent::because(\sprintf('"templateData" maps field names to values; got the key %d.', $name));
            }

            $named[$name] = $value;
        }

        return $named;
    }
}
