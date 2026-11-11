<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Application;

use Phil\BlockEditorBundle\Application\Exception\InvalidSuggestion;
use Phil\BlockEditorBundle\Application\Exception\UploadRefused;
use Phil\BlockEditorBundle\Application\Reference\Suggestion;
use Phil\BlockEditorBundle\Application\Upload\ImageUpload;
use Phil\BlockEditorBundle\Application\Upload\UploadedImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Suggestion::class)]
#[CoversClass(InvalidSuggestion::class)]
#[CoversClass(ImageUpload::class)]
#[CoversClass(UploadedImage::class)]
#[CoversClass(UploadRefused::class)]
final class ValuesTest extends TestCase
{
    public function testASuggestionIsWrittenForTheMenu(): void
    {
        self::assertSame(['reference' => 'user:42', 'label' => 'Ada', 'url' => '/u/42', 'hint' => 'ada@example.com'], new Suggestion('user:42', 'Ada', '/u/42', 'ada@example.com')->toArray());
        self::assertSame(['reference' => 'page:a/b.c-d_e', 'label' => str_repeat('é', 200), 'url' => null, 'hint' => null], new Suggestion('page:a/b.c-d_e', str_repeat('é', 200))->toArray());
        self::assertSame('x:1', new Suggestion('x:1', 'X')->reference);
        self::assertSame('k'.str_repeat('a', 31).':'.str_repeat('1', 128), new Suggestion('k'.str_repeat('a', 31).':'.str_repeat('1', 128), 'L')->reference);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badReferences(): iterable
    {
        yield 'no kind' => ['42'];
        yield 'upper-case kind' => ['User:42'];
        yield 'kind too long' => ['k'.str_repeat('a', 32).':1'];
        yield 'identifier too long' => ['user:'.str_repeat('1', 129)];
        yield 'empty identifier' => ['user:'];
        yield 'space' => ['user:4 2'];
        yield 'text after' => ["user:42\nx"];
        yield 'text before' => ["x\nuser:42"];
    }

    #[DataProvider('badReferences')]
    public function testAReferenceMustBeKindAndIdentifier(string $reference): void
    {
        $this->expectExceptionObject(InvalidSuggestion::reference($reference));
        $this->expectExceptionMessageIsOrContains('is not of the form kind:identifier');

        new Suggestion($reference, 'Label');
    }

    public function testALabelIsNeeded(): void
    {
        $this->expectExceptionObject(new InvalidSuggestion('A suggestion needs a label of 1 to 200 characters.'));

        new Suggestion('user:1', '');
    }

    public function testALabelIsShort(): void
    {
        $this->expectException(InvalidSuggestion::class);

        new Suggestion('user:1', str_repeat('a', 201));
    }

    public function testAnUploadedImageIsTheFileOfAnImageBlock(): void
    {
        self::assertSame(['url' => '/a.png'], new UploadedImage('/a.png')->file());
        self::assertSame(['id' => 7, 'url' => '/a.png', 'credit' => 'Me', 'width' => 640, 'height' => 480], new UploadedImage('/a.png', 640, 480, ['id' => 7, 'url' => '/evil', 'credit' => 'Me'])->file());
        self::assertSame(['url' => '/a.png', 'height' => 480], new UploadedImage('/a.png', null, 480)->file());

        $upload = new ImageUpload('/tmp/x', 'cat.png', 'image/png', 12);
        self::assertSame(['/tmp/x', 'cat.png', 'image/png', 12], [$upload->path, $upload->clientName, $upload->clientMimeType, $upload->size]);
        self::assertSame('Too big', new UploadRefused('Too big')->getMessage());
    }
}
