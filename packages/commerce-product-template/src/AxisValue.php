<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * One value of an axis: the code written in the SKU (`M`, `XL`, `BK`), the label a buyer reads, the swatch a selector
 * shows, the name the supplier of the template's blank gives it, the photo of the blank in this value for each face,
 * and the channels that sell it.
 *
 * The supplier's name belongs to the template, not to the value: two blanks from two suppliers name the same colour
 * otherwise.
 */
final readonly class AxisValue
{
    /**
     * @param array<string, string> $photos   by face code
     * @param list<string>          $channels the channels that sell it; every channel when empty
     */
    private function __construct(
        public string $code,
        public Label $label,
        public ?Swatch $swatch,
        public ?string $supplierName,
        public array $photos,
        public array $channels,
    ) {
    }

    /**
     * @param string                   $code     1 to 16 letters, digits, ".", "_" or "-", starting with a letter or digit
     * @param array<array-key, string> $photos   the photo of the blank in this value, by face code, relative to the
     *                                           store of images
     * @param list<string>             $channels the channels that sell the products of this value; every channel when
     *                                           empty
     */
    public static function of(string $code, Label $label, ?Swatch $swatch = null, ?string $supplierName = null, array $photos = [], array $channels = []): self
    {
        Check::skuCode($code, 'an axis value', 16);

        if (null !== $supplierName) {
            Check::text($supplierName, \sprintf('The supplier name of %s', $code));
        }

        $checked = [];

        foreach ($photos as $face => $path) {
            $face = Check::identifier((string) $face, 'a face');
            $checked[$face] = Check::path($path, \sprintf('the %s photo of %s', $face, $code));
        }

        return new self($code, $label, $swatch, $supplierName, $checked, Check::channels($channels));
    }

    public function photo(string $face): ?string
    {
        return $this->photos[$face] ?? null;
    }

    /**
     * @internal the faces a template checks its values against
     *
     * @param list<string> $faces
     */
    public function checkFaces(array $faces): void
    {
        foreach (array_keys($this->photos) as $face) {
            if (!\in_array($face, $faces, true)) {
                throw InvalidDefinition::because(\sprintf('The value %s has a photo of the face %s, which the template does not have.', $this->code, $face));
            }
        }
    }
}
