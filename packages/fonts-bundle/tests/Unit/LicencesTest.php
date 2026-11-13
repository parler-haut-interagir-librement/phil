<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Tests\Unit;

use Override;
use Phil\Fonts\Exception\InvalidLicenceRegister;
use Phil\Fonts\Licence\LicenceRecord;
use Phil\FontsBundle\Application\Exception\FontUnreadable;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\FontsBundle\Application\FontFiles;
use Phil\FontsBundle\Application\Licences;
use Phil\FontsBundle\Tests\Unit\Fixture\Directory;
use Phil\FontsBundle\Tests\Unit\Fixture\Fonts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Licences::class)]
final class LicencesTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testNoRegisterYetRecordsNothing(): void
    {
        $directory = Directory::with(['fonts/Sans.ttf' => Fonts::trueType('Example Sans')]);
        $licences = new Licences(new FontCatalogue($directory.'/fonts'), $directory.'/config/licences.json');

        self::assertSame($directory.'/config/licences.json', $licences->path());
        self::assertSame([], $licences->kept()->records());
        self::assertSame(['Sans.ttf'], $licences->changedFiles());
    }

    public function testWritesTheRegisterThenFindsWhatChanged(): void
    {
        $directory = Directory::with(['fonts/Sans.ttf' => Fonts::trueType('Example Sans'), 'fonts/Serif.ttf' => Fonts::trueType('Example Serif')]);
        $written = new Licences(new FontCatalogue($directory.'/fonts'), $directory.'/config/fonts/licences.json')->write();

        self::assertCount(2, $written->records());
        self::assertSame($written->toJson(), file_get_contents($directory.'/config/fonts/licences.json'));
        self::assertSame([], new Licences(new FontCatalogue($directory.'/fonts'), $directory.'/config/fonts/licences.json')->changedFiles());

        file_put_contents($directory.'/fonts/Sans.ttf', Fonts::trueType('Example Sans', 'Regular', 400, 0x0002));
        unlink($directory.'/fonts/Serif.ttf');
        file_put_contents($directory.'/fonts/Mono.ttf', Fonts::trueType('Example Mono'));
        $licences = new Licences(new FontCatalogue($directory.'/fonts'), $directory.'/config/fonts/licences.json');

        self::assertSame(['Mono.ttf', 'Sans.ttf', 'Serif.ttf'], $licences->changedFiles());
        self::assertSame(['Mono.ttf', 'Sans.ttf'], array_map(static fn (LicenceRecord $record): string => $record->file, $licences->current()->records()));
    }

    public function testKeepsTheRecordOfAWoff2FileReadElsewhere(): void
    {
        $directory = Directory::with(['fonts/Sans.woff2' => Fonts::woff2('Example Sans')]);
        $register = $directory.'/licences.json';
        file_put_contents($register, new FontCatalogue($directory.'/fonts', new FontFiles(str_pad(...)))->licences()->toJson());
        $licences = new Licences(new FontCatalogue($directory.'/fonts'), $register);

        self::assertSame([], $licences->changedFiles());
        self::assertSame('Sans.woff2', $licences->current()->records()[0]->file);
    }

    public function testARegisterThatIsNotJsonIsUnreadable(): void
    {
        $directory = Directory::with(['licences.json' => '{']);

        try {
            new Licences(new FontCatalogue($directory), $directory.'/licences.json')->kept();
            self::fail('The register is not JSON.');
        } catch (FontUnreadable $fontUnreadable) {
            self::assertStringStartsWith('The font file "licences.json" cannot be read: The licence register is not valid JSON', $fontUnreadable->getMessage());
            self::assertInstanceOf(InvalidLicenceRegister::class, $fontUnreadable->getPrevious());
        }
    }
}
