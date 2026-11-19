<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;

/**
 * Declarations the tests share.
 */
final class Declarations
{
    private function __construct()
    {
    }

    public static function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-08 10:00:00');
    }

    public static function of(string $license, string $id = '42'): LicenseDeclaration
    {
        return LicenseDeclaration::declare(SubjectReference::of('book', $id), new LicenseCatalog()->get($license), self::at());
    }

    /**
     * A book under the ORC License that gives everything: its notice, its Reserved Material, a text source, an image
     * and a font.
     */
    public static function complete(): LicenseDeclaration
    {
        $declaration = self::of(LicenseCatalog::ORC);
        $declaration->describe('© 2026 Example Press', 'The names of the setting, its maps and its art.', null, null, self::at());
        $declaration->attribute([
            Attribution::of(ResourceKind::Image, 'Cover <art>', LicenseCatalog::CC_BY, 'Jane Doe', 'https://example.com/cover', '© 2024 Jane Doe', 'Cropped'),
            Attribution::of(ResourceKind::Text, 'Example Reference Document', LicenseCatalog::ORC, 'Example Games', null, "© 2025 Example Games\nAll rights reserved."),
            Attribution::of(ResourceKind::Font, 'Example Serif', LicenseCatalog::CC0),
        ], self::at());

        return $declaration;
    }
}
