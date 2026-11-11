<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Phil\Fonts\Licence\Licence;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Licence::class)]
final class LicenceTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, ?string, ?string}>
     */
    public static function licences(): iterable
    {
        yield 'OFL by its site' => [null, 'https://openfontlicense.org', 'OFL-1.1'];
        yield 'OFL by its old site' => [null, 'http://scripts.sil.org/OFL', 'OFL-1.1'];
        yield 'OFL by name' => ['This Font Software is licensed under the SIL Open Font License, Version 1.1.', null, 'OFL-1.1'];
        yield 'Apache by URL' => [null, 'http://www.apache.org/licenses/LICENSE-2.0', 'Apache-2.0'];
        yield 'Apache by name' => ['Licensed under the Apache License, Version 2.0', null, 'Apache-2.0'];
        yield 'Ubuntu by URL' => [null, 'https://ubuntu.com/legal/terms-and-policies/font-licence', 'UFL-1.0'];
        yield 'Ubuntu by name' => ['Ubuntu Font Licence 1.0', null, 'UFL-1.0'];
        yield 'MIT by URL' => [null, 'https://opensource.org/licenses/MIT', 'MIT'];
        yield 'MIT by name' => ['Released under the MIT License.', null, 'MIT'];
        yield 'two licences named: the first known one wins' => ['MIT License', 'https://openfontlicense.org', 'OFL-1.1'];
        yield 'a commercial licence' => ['This font is licensed for desktop use only.', 'https://example.com/eula', null];
        yield 'nothing' => [null, null, null];
        yield 'no name made of the URL and the description together' => [' license', 'mit', null];
    }

    #[DataProvider('licences')]
    public function testRecognisesTheCommonFontLicences(?string $description, ?string $url, ?string $spdx): void
    {
        self::assertSame($spdx, new Licence($description, $url)->spdx());
    }

    public function testKeepsWhatTheFontSays(): void
    {
        $licence = new Licence('Description', 'https://example.com');

        self::assertSame('Description', $licence->description);
        self::assertSame('https://example.com', $licence->url);
    }
}
