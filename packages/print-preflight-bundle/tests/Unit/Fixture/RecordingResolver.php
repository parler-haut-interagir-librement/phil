<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit\Fixture;

use Override;
use Phil\Fonts\EmbeddingPermissions;
use Phil\Fonts\FontStyle;
use Phil\PrintPreflight\UsedFont;
use Phil\PrintPreflightBundle\Application\Port\FontResolver;

/**
 * A font resolver that keeps what it is asked and answers a font of that name, restricted.
 */
final class RecordingResolver implements FontResolver
{
    /** @var list<array{string, int, FontStyle, bool, bool}> */
    public array $asked = [];

    #[Override]
    public function resolve(string $family, int $weight, FontStyle $style, bool $embedded, bool $subset): UsedFont
    {
        $this->asked[] = [$family, $weight, $style, $embedded, $subset];

        return new UsedFont($family, new EmbeddingPermissions(0x0002), $embedded, $subset);
    }
}
