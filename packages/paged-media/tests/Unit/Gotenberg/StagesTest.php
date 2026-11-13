<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Gotenberg;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Pages;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\Read\DocumentInformation;
use Phil\PagedMedia\Gotenberg\Signing\Certificate;
use Phil\PagedMedia\Gotenberg\Signing\CertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthorityUrl;
use Phil\PagedMedia\Gotenberg\Stage\Attach;
use Phil\PagedMedia\Gotenberg\Stage\Autotag;
use Phil\PagedMedia\Gotenberg\Stage\Bookmark;
use Phil\PagedMedia\Gotenberg\Stage\ColorTarget;
use Phil\PagedMedia\Gotenberg\Stage\ConvertColors;
use Phil\PagedMedia\Gotenberg\Stage\Decrypt;
use Phil\PagedMedia\Gotenberg\Stage\Delete;
use Phil\PagedMedia\Gotenberg\Stage\Detach;
use Phil\PagedMedia\Gotenberg\Stage\Encrypt;
use Phil\PagedMedia\Gotenberg\Stage\EncryptionAlgorithm;
use Phil\PagedMedia\Gotenberg\Stage\ExtractAttachments;
use Phil\PagedMedia\Gotenberg\Stage\ExtractText;
use Phil\PagedMedia\Gotenberg\Stage\FacturXLevel;
use Phil\PagedMedia\Gotenberg\Stage\FlattenForm;
use Phil\PagedMedia\Gotenberg\Stage\FlattenTransparency;
use Phil\PagedMedia\Gotenberg\Stage\ImageFormat;
use Phil\PagedMedia\Gotenberg\Stage\Insert;
use Phil\PagedMedia\Gotenberg\Stage\Merge;
use Phil\PagedMedia\Gotenberg\Stage\Operation;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMedia\Gotenberg\Stage\Permissions;
use Phil\PagedMedia\Gotenberg\Stage\Redact;
use Phil\PagedMedia\Gotenberg\Stage\Region;
use Phil\PagedMedia\Gotenberg\Stage\Relationship;
use Phil\PagedMedia\Gotenberg\Stage\Remediate;
use Phil\PagedMedia\Gotenberg\Stage\Render;
use Phil\PagedMedia\Gotenberg\Stage\Rotate;
use Phil\PagedMedia\Gotenberg\Stage\Select;
use Phil\PagedMedia\Gotenberg\Stage\SetBookmarks;
use Phil\PagedMedia\Gotenberg\Stage\SetForm;
use Phil\PagedMedia\Gotenberg\Stage\SetMetadata;
use Phil\PagedMedia\Gotenberg\Stage\Sign;
use Phil\PagedMedia\Gotenberg\Stage\Split;
use Phil\PagedMedia\Gotenberg\Stage\Stage;
use Phil\PagedMedia\Gotenberg\Stage\StampFont;
use Phil\PagedMedia\Gotenberg\Stage\StampImage;
use Phil\PagedMedia\Gotenberg\Stage\StampPdf;
use Phil\PagedMedia\Gotenberg\Stage\StampText;
use Phil\PagedMedia\Gotenberg\Stage\Timestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Merge::class)]
#[CoversClass(Split::class)]
#[CoversClass(Select::class)]
#[CoversClass(Delete::class)]
#[CoversClass(Insert::class)]
#[CoversClass(Rotate::class)]
#[CoversClass(StampText::class)]
#[CoversClass(StampImage::class)]
#[CoversClass(StampPdf::class)]
#[CoversClass(SetMetadata::class)]
#[CoversClass(SetBookmarks::class)]
#[CoversClass(Bookmark::class)]
#[CoversClass(SetForm::class)]
#[CoversClass(FlattenForm::class)]
#[CoversClass(FlattenTransparency::class)]
#[CoversClass(ConvertColors::class)]
#[CoversClass(Redact::class)]
#[CoversClass(Region::class)]
#[CoversClass(Autotag::class)]
#[CoversClass(Attach::class)]
#[CoversClass(Detach::class)]
#[CoversClass(Optimize::class)]
#[CoversClass(Encrypt::class)]
#[CoversClass(Permissions::class)]
#[CoversClass(Decrypt::class)]
#[CoversClass(Remediate::class)]
#[CoversClass(Sign::class)]
#[CoversClass(Timestamp::class)]
#[CoversClass(Render::class)]
#[CoversClass(ExtractText::class)]
#[CoversClass(ExtractAttachments::class)]
#[CoversClass(Operation::class)]
#[CoversClass(Check::class)]
final class StagesTest extends TestCase
{
    /**
     * @param array<string, mixed> $options
     * @param list<string>         $files
     */
    #[DataProvider('stages')]
    public function testAStageGivesItsOperationItsOptionsAndItsFiles(Stage $stage, Operation $operation, array $options, array $files = []): void
    {
        self::assertSame($operation, $stage->operation());
        self::assertSame($options, $stage->options());
        self::assertSame($files, array_map(static fn (InputFile $file): string => $file->name, $stage->files()));
    }

    /**
     * @return iterable<string, array{0: Stage, 1: Operation, 2: array<string, mixed>, 3?: list<string>}>
     */
    public static function stages(): iterable
    {
        $annex = InputFile::fromContents('annex.pdf', '%PDF');
        $logo = InputFile::fromContents('logo.PNG', 'png');
        $pem = Certificate::pem(InputFile::fromContents('cert.pem', 'c'), InputFile::fromContents('key.pem', 'k'));
        $tsa = new TimestampAuthorityUrl('https://tsa.example.com/');

        yield 'merge' => [new Merge(), Operation::Merge, []];
        yield 'split every' => [Split::every(2), Operation::Split, ['every' => 2]];
        yield 'split every page' => [Split::every(1), Operation::Split, ['every' => 1]];
        yield 'split by ranges' => [Split::ranges('1', Pages::range(2, 3)), Operation::Split, ['ranges' => ['1', '2-3']]];
        yield 'select' => [new Select('3,1'), Operation::Select, ['pages' => '3,1']];
        yield 'select pages' => [new Select(Pages::last()), Operation::Select, ['pages' => 'last']];
        yield 'delete' => [new Delete('2-3'), Operation::Delete, ['pages' => '2-3']];
        yield 'delete pages' => [new Delete(Pages::even()), Operation::Delete, ['pages' => 'even']];
        yield 'insert' => [new Insert($annex), Operation::Insert, ['source' => 'annex.pdf', 'at' => 0], ['annex.pdf']];
        yield 'insert pages after one' => [new Insert($annex, 2, '1'), Operation::Insert, ['source' => 'annex.pdf', 'at' => 2, 'sourcePages' => '1'], ['annex.pdf']];
        yield 'insert selection' => [new Insert($annex, 0, Pages::odd()), Operation::Insert, ['source' => 'annex.pdf', 'at' => 0, 'sourcePages' => 'odd'], ['annex.pdf']];
        yield 'rotate' => [new Rotate(-90), Operation::Rotate, ['angle' => -90]];
        yield 'rotate pages' => [new Rotate(180, '2'), Operation::Rotate, ['angle' => 180, 'pages' => '2']];
        yield 'rotate selection' => [new Rotate(0, Pages::all()), Operation::Rotate, ['angle' => 0, 'pages' => 'all']];
        yield 'stamp text' => [new StampText('DRAFT'), Operation::StampText, ['text' => 'DRAFT', 'x' => 0.0, 'y' => 0.0]];
        yield 'stamp text everything' => [
            new StampText('DRAFT', 72, 36.5, 24, StampFont::TimesBold, '#ff0000', 0.5, 45, '1-2'),
            Operation::StampText,
            ['text' => 'DRAFT', 'pages' => '1-2', 'x' => 72.0, 'y' => 36.5, 'fontSize' => 24.0, 'font' => 'Times-Bold', 'color' => '#ff0000', 'opacity' => 0.5, 'rotation' => 45.0],
        ];
        yield 'stamp text selection' => [new StampText('A', pages: Pages::last()), Operation::StampText, ['text' => 'A', 'pages' => 'last', 'x' => 0.0, 'y' => 0.0]];
        yield 'stamp image' => [new StampImage($logo), Operation::StampImage, ['image' => 'logo.PNG', 'x' => 0.0, 'y' => 0.0], ['logo.PNG']];
        yield 'stamp image everything' => [
            new StampImage(InputFile::fromContents('seal.jpeg', 'j'), 10, 20, 50, 60, 1, 90, 'last'),
            Operation::StampImage,
            ['image' => 'seal.jpeg', 'pages' => 'last', 'x' => 10.0, 'y' => 20.0, 'width' => 50.0, 'height' => 60.0, 'opacity' => 1.0, 'rotation' => 90.0],
            ['seal.jpeg'],
        ];
        yield 'stamp image selection' => [new StampImage(InputFile::fromContents('a.jpg', 'j'), pages: Pages::odd()), Operation::StampImage, ['image' => 'a.jpg', 'pages' => 'odd', 'x' => 0.0, 'y' => 0.0], ['a.jpg']];
        yield 'stamp pdf' => [new StampPdf($annex), Operation::StampPdf, ['source' => 'annex.pdf', 'x' => 0.0, 'y' => 0.0], ['annex.pdf']];
        yield 'stamp pdf everything' => [
            new StampPdf($annex, 1, 5, 6, 0.5, 0, -30, '2'),
            Operation::StampPdf,
            ['source' => 'annex.pdf', 'sourcePage' => 1, 'pages' => '2', 'x' => 5.0, 'y' => 6.0, 'scale' => 0.5, 'opacity' => 0.0, 'rotation' => -30.0],
            ['annex.pdf'],
        ];
        yield 'stamp pdf selection' => [new StampPdf($annex, pages: Pages::all()), Operation::StampPdf, ['source' => 'annex.pdf', 'pages' => 'all', 'x' => 0.0, 'y' => 0.0], ['annex.pdf']];
        yield 'set metadata' => [new SetMetadata(title: 'Report'), Operation::SetMetadata, ['title' => 'Report']];
        yield 'set metadata everything' => [
            new SetMetadata('T', 'A', 'S', 'K', 'C', 'P', new DateTimeImmutable('2026-01-01 07:00:05', new DateTimeZone('America/Montreal')), 'D:20260102000000Z'),
            Operation::SetMetadata,
            ['title' => 'T', 'author' => 'A', 'subject' => 'S', 'keywords' => 'K', 'creator' => 'C', 'producer' => 'P', 'creationDate' => 'D:20260101120005Z', 'modDate' => 'D:20260102000000Z'],
        ];
        yield 'set metadata from a read' => [
            SetMetadata::from(new DocumentInformation('T', 'A', 'S', 'K', 'C', 'P', 'D:1', 'D:2')),
            Operation::SetMetadata,
            ['title' => 'T', 'author' => 'A', 'subject' => 'S', 'keywords' => 'K', 'creator' => 'C', 'producer' => 'P', 'creationDate' => 'D:1', 'modDate' => 'D:2'],
        ];
        yield 'set metadata modification date' => [new SetMetadata(modDate: new DateTimeImmutable('2026-06-30 23:59:59 UTC')), Operation::SetMetadata, ['modDate' => 'D:20260630235959Z']];
        yield 'set bookmarks' => [
            new SetBookmarks(new Bookmark('One', 1, [new Bookmark('Two', 2)]), new Bookmark('Three', 3)),
            Operation::SetBookmarks,
            ['bookmarks' => [['title' => 'One', 'pageNumber' => 1, 'children' => [['title' => 'Two', 'pageNumber' => 2]]], ['title' => 'Three', 'pageNumber' => 3]]],
        ];
        yield 'set form' => [new SetForm(['name' => 'Ada', 'colors' => ['Red', 'Green']]), Operation::SetForm, ['fields' => ['name' => 'Ada', 'colors' => ['Red', 'Green']]]];
        yield 'flatten form' => [new FlattenForm(), Operation::FlattenForm, []];
        yield 'flatten transparency' => [new FlattenTransparency(), Operation::FlattenTransparency, []];
        yield 'convert colors' => [new ConvertColors(ColorTarget::OutputIntent), Operation::ConvertColors, ['target' => 'output-intent']];
        yield 'redact' => [
            new Redact(new Region(1, 0, 10.5, 100, 20), new Region(2, 1, 2, 3, 4)),
            Operation::Redact,
            ['regions' => [['page' => 1, 'x' => 0.0, 'y' => 10.5, 'width' => 100.0, 'height' => 20.0], ['page' => 2, 'x' => 1.0, 'y' => 2.0, 'width' => 3.0, 'height' => 4.0]]],
        ];
        yield 'autotag' => [new Autotag(), Operation::Autotag, []];
        yield 'attach' => [new Attach(InputFile::fromContents('data.csv', 'a')), Operation::Attach, ['file' => 'data.csv'], ['data.csv']];
        yield 'attach everything' => [
            new Attach(InputFile::fromContents('data.csv', 'a'), 'source.csv', 'text/csv', Relationship::Source),
            Operation::Attach,
            ['file' => 'data.csv', 'name' => 'source.csv', 'mimeType' => 'text/csv', 'relationship' => 'Source'],
            ['data.csv'],
        ];
        yield 'detach' => [new Detach('data.csv'), Operation::Detach, ['name' => 'data.csv']];
        yield 'optimize' => [new Optimize(), Operation::Optimize, []];
        yield 'optimize without passes' => [new Optimize(false, false, false, false), Operation::Optimize, ['objectStreams' => false, 'dedup' => false, 'recompressImages' => false, 'subsetFonts' => false]];
        yield 'optimize without one pass' => [new Optimize(recompressImages: false), Operation::Optimize, ['recompressImages' => false]];
        yield 'encrypt' => [new Encrypt('user'), Operation::Encrypt, ['userPassword' => 'user', 'algorithm' => 'aes256']];
        yield 'encrypt for the owner' => [
            new Encrypt(null, 'owner', EncryptionAlgorithm::Rc440, new Permissions(false, false, false, false, false, false, false)),
            Operation::Encrypt,
            ['ownerPassword' => 'owner', 'algorithm' => 'rc4-40', 'permissions' => ['print' => false, 'printHighQuality' => false, 'modifyContent' => false, 'extractText' => false, 'modifyAnnotations' => false, 'fillForms' => false, 'assemble' => false]],
        ];
        yield 'encrypt denying one' => [new Encrypt('u', 'o', permissions: new Permissions(extractText: false)), Operation::Encrypt, ['userPassword' => 'u', 'ownerPassword' => 'o', 'algorithm' => 'aes256', 'permissions' => ['extractText' => false]]];
        yield 'decrypt' => [new Decrypt(), Operation::Decrypt, []];
        yield 'decrypt with a password' => [new Decrypt('secret'), Operation::Decrypt, ['password' => 'secret']];
        yield 'remediate' => [new Remediate(Profile::PdfA2b), Operation::Remediate, ['profile' => 'PDF/A-2b']];
        yield 'remediate a pair' => [
            new Remediate([Profile::PdfA4, Profile::PdfUa2], 'fr-CA', true, 'A figure'),
            Operation::Remediate,
            ['profile' => ['PDF/A-4', 'PDF/UA-2'], 'language' => 'fr-CA', 'aggressive' => true, 'figureAltText' => 'A figure'],
        ];
        yield 'remediate a factur-x' => [
            new Remediate(Profile::FacturX, xml: InputFile::fromContents('factur-x.xml', '<x/>'), level: FacturXLevel::En16931, documentType: 'CREDIT_NOTE'),
            Operation::Remediate,
            ['profile' => 'Factur-X', 'xml' => 'factur-x.xml', 'level' => 'EN 16931', 'documentType' => 'CREDIT_NOTE'],
            ['factur-x.xml'],
        ];
        yield 'sign' => [new Sign($pem), Operation::Sign, ['certificate' => 'cert.pem', 'key' => 'key.pem'], ['cert.pem', 'key.pem']];
        yield 'sign everything' => [
            new Sign(Certificate::pkcs12(InputFile::fromContents('signer.p12', 'p'), 'secret'), 'Approval', 'Montréal', 'a@example.com', 'Ada', true, $tsa),
            Operation::Sign,
            ['certificate' => 'signer.p12', 'certificatePassword' => 'secret', 'reason' => 'Approval', 'location' => 'Montréal', 'contactInfo' => 'a@example.com', 'name' => 'Ada', 'pades' => true, 'timestampUrl' => 'https://tsa.example.com/'],
            ['signer.p12'],
        ];
        yield 'timestamp' => [new Timestamp($tsa), Operation::Timestamp, ['timestampUrl' => 'https://tsa.example.com/']];
        yield 'render' => [new Render(), Operation::Render, []];
        yield 'render everything' => [new Render('1', 72, ImageFormat::Jpeg, 80), Operation::Render, ['pages' => '1', 'dpi' => 72, 'format' => 'jpeg', 'quality' => 80]];
        yield 'render selection' => [new Render(Pages::all(), 1, ImageFormat::Png), Operation::Render, ['pages' => 'all', 'dpi' => 1, 'format' => 'png']];
        yield 'render quality bounds' => [new Render(format: ImageFormat::Jpeg, quality: 1), Operation::Render, ['format' => 'jpeg', 'quality' => 1]];
        yield 'render best quality' => [new Render(format: ImageFormat::Jpeg, quality: 100), Operation::Render, ['format' => 'jpeg', 'quality' => 100]];
        yield 'extract text' => [new ExtractText(), Operation::ExtractText, []];
        yield 'extract text everything' => [new ExtractText('2,1', true, true, true), Operation::ExtractText, ['pages' => '2,1', 'invisible' => true, 'artifacts' => true, 'annotations' => true]];
        yield 'extract text selection' => [new ExtractText(Pages::odd()), Operation::ExtractText, ['pages' => 'odd']];
        yield 'extract attachments' => [new ExtractAttachments(), Operation::ExtractAttachments, []];
        yield 'extract one attachment' => [new ExtractAttachments('factur-x.xml'), Operation::ExtractAttachments, ['name' => 'factur-x.xml']];
    }

    public function testASignatureTakesTheCertificateItsSourceGivesNow(): void
    {
        $source = new class implements CertificateSource {
            public function certificate(): Certificate
            {
                return Certificate::pkcs12(InputFile::fromContents('now.pfx', 'p'));
            }
        };
        $tsa = new TimestampAuthorityUrl('http://tsa.example.com');

        $sign = Sign::with($source, 'R', 'L', 'C', 'N', true, $tsa);

        self::assertSame(['certificate' => 'now.pfx', 'reason' => 'R', 'location' => 'L', 'contactInfo' => 'C', 'name' => 'N', 'pades' => true, 'timestampUrl' => 'http://tsa.example.com'], $sign->options());
        self::assertSame(['certificate' => 'now.pfx'], Sign::with($source)->options());
    }

    public function testListsOfRegionsAndBookmarksAreKeptAsLists(): void
    {
        $region = new Region(1, 0, 0, 1, 1);
        $bookmark = new Bookmark('A', 1);

        self::assertSame([$region], new Redact(...['first' => $region])->regions);
        self::assertSame([$bookmark], new SetBookmarks(...['first' => $bookmark])->bookmarks);
    }

    public function testARemediationKnowsWhetherItMakesAnArchive(): void
    {
        self::assertTrue(new Remediate(Profile::PdfA1b)->isArchival());
        self::assertTrue(new Remediate(Profile::FacturX)->isArchival());
        self::assertTrue(new Remediate([Profile::PdfUa1, Profile::PdfA3u])->isArchival());
        self::assertFalse(new Remediate(Profile::PdfUa2)->isArchival());
    }

    #[DataProvider('invalidStages')]
    public function testAStageRefusesOptionsGotenbergWouldRefuse(callable $build, string $message): void
    {
        $this->expectException(InvalidStage::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    /**
     * @return iterable<string, array{callable, string}>
     */
    public static function invalidStages(): iterable
    {
        $pdf = InputFile::fromContents('a.pdf', '%PDF');
        $png = InputFile::fromContents('a.png', 'png');
        $xml = InputFile::fromContents('f.xml', '<x/>');

        yield 'split every 0' => [static fn (): Split => Split::every(0), 'A split cuts every 1 page or more; 0 is not.'];
        yield 'split without ranges' => [static fn (): Split => Split::ranges(), 'A split by ranges takes one page selection at least.'];
        yield 'insert a non-pdf' => [static fn (): Insert => new Insert($png), 'The PDF to insert is a .pdf file; "a.png" is not.'];
        yield 'insert before page 0' => [static fn (): Insert => new Insert($pdf, -1), 'Pages are inserted after page 0 or later; -1 is not a page.'];
        yield 'rotate 45' => [static fn (): Rotate => new Rotate(45), 'A rotation is a multiple of 90; 45 is not.'];
        yield 'stamp no text' => [static fn (): StampText => new StampText(' '), 'The text of a stamp is not empty.'];
        yield 'stamp text size 0' => [static fn (): StampText => new StampText('A', fontSize: 0), 'A font size is above 0; 0 is not.'];
        yield 'stamp text colour' => [static fn (): StampText => new StampText('A', color: 'red'), 'The colour of a stamp is written #rrggbb; "red" is not.'];
        yield 'stamp text colour without hash' => [static fn (): StampText => new StampText('A', color: '0ff0000'), 'is written #rrggbb'];
        yield 'stamp text colour not hexadecimal' => [static fn (): StampText => new StampText('A', color: '#gg0000'), 'is written #rrggbb'];
        yield 'stamp text colour first digit' => [static fn (): StampText => new StampText('A', color: '#g00000'), 'is written #rrggbb'];
        yield 'stamp text colour too long' => [static fn (): StampText => new StampText('A', color: '#ff00000'), 'is written #rrggbb'];
        yield 'stamp text opacity' => [static fn (): StampText => new StampText('A', opacity: 1.5), 'An opacity is from 0 to 1; 1.5 is not.'];
        yield 'stamp text negative opacity' => [static fn (): StampText => new StampText('A', opacity: -0.1), 'An opacity is from 0 to 1; -0.1 is not.'];
        yield 'stamp a pdf as an image' => [static fn (): StampImage => new StampImage($pdf), 'A stamped image is a .png, .jpg, .jpeg file; "a.pdf" is not.'];
        yield 'stamp image width' => [static fn (): StampImage => new StampImage($png, width: 0), 'The width of a stamp is above 0; 0 is not.'];
        yield 'stamp image height' => [static fn (): StampImage => new StampImage($png, height: -1), 'The height of a stamp is above 0; -1 is not.'];
        yield 'stamp image opacity' => [static fn (): StampImage => new StampImage($png, opacity: 2), 'An opacity is from 0 to 1; 2 is not.'];
        yield 'stamp an image as a pdf' => [static fn (): StampPdf => new StampPdf($png), 'A stamped PDF is a .pdf file; "a.png" is not.'];
        yield 'stamp pdf page 0' => [static fn (): StampPdf => new StampPdf($pdf, 0), 'The page of a stamped PDF counts from 1; 0 does not.'];
        yield 'stamp pdf scale' => [static fn (): StampPdf => new StampPdf($pdf, scale: 0), 'The scale of a stamp is above 0; 0 is not.'];
        yield 'stamp pdf opacity' => [static fn (): StampPdf => new StampPdf($pdf, opacity: 1.01), 'An opacity is from 0 to 1; 1.01 is not.'];
        yield 'set no metadata' => [static fn (): SetMetadata => new SetMetadata(), 'Setting the metadata takes one field at least.'];
        yield 'set no bookmark' => [static fn (): SetBookmarks => new SetBookmarks(), 'Setting the bookmarks takes one bookmark at least.'];
        yield 'bookmark without title' => [static fn (): Bookmark => new Bookmark(' ', 1), 'A bookmark has a title.'];
        yield 'bookmark on page 0' => [static fn (): Bookmark => new Bookmark('A', 0), 'A bookmark points at a page counted from 1; 0 is not.'];
        yield 'fill no field' => [static fn (): SetForm => new SetForm([]), 'Filling a form takes one field at least.'];
        yield 'fill a field without name' => [static fn (): SetForm => new SetForm(['a' => 'b', ' ' => 'c']), 'A form field has a name.'];
        yield 'redact nothing' => [static fn (): Redact => new Redact(), 'A redaction takes one region at least.'];
        yield 'region on page 0' => [static fn (): Region => new Region(0, 0, 0, 1, 1), 'A region is on a page counted from 1; 0 is not.'];
        yield 'region width' => [static fn (): Region => new Region(1, 0, 0, 0, 1), 'The width of a region is above 0; 0 is not.'];
        yield 'region height' => [static fn (): Region => new Region(1, 0, 0, 1, 0), 'The height of a region is above 0; 0 is not.'];
        yield 'attach without name' => [static fn (): Attach => new Attach($pdf, ''), 'The name of an attachment is not empty.'];
        yield 'attach without type' => [static fn (): Attach => new Attach($pdf, null, ' '), 'The media type of an attachment is not empty.'];
        yield 'detach nothing' => [static fn (): Detach => new Detach(''), 'The name of the attachment to remove is not empty.'];
        yield 'encrypt without password' => [static fn (): Encrypt => new Encrypt(), 'Encrypting a PDF takes a user password, an owner password, or both.'];
        yield 'encrypt with an empty user password' => [static fn (): Encrypt => new Encrypt(''), 'A user password is not empty.'];
        yield 'encrypt with an empty owner password' => [static fn (): Encrypt => new Encrypt('u', ''), 'An owner password is not empty.'];
        yield 'remediate two archives' => [static fn (): Remediate => new Remediate([Profile::PdfA2b, Profile::PdfA3b]), '"PDF/A-2b, PDF/A-3b" is not.'];
        yield 'remediate with an empty language' => [static fn (): Remediate => new Remediate(Profile::PdfUa1, ''), 'A language is not empty.'];
        yield 'remediate pdf/a with an xml' => [static fn (): Remediate => new Remediate(Profile::PdfA3b, xml: $xml, level: FacturXLevel::Basic), 'An invoice XML, its level and its document type go with the Factur-X profile only.'];
        yield 'remediate pdf/a with a level' => [static fn (): Remediate => new Remediate(Profile::PdfA3b, level: FacturXLevel::Basic), 'go with the Factur-X profile only'];
        yield 'remediate pdf/a with a document type' => [static fn (): Remediate => new Remediate(Profile::PdfA3b, documentType: 'INVOICE'), 'go with the Factur-X profile only'];
        yield 'remediate factur-x with a pdf as xml' => [static fn (): Remediate => new Remediate(Profile::FacturX, xml: $pdf, level: FacturXLevel::Basic), 'An invoice is a .xml file; "a.pdf" is not.'];
        yield 'remediate factur-x xml without level' => [static fn (): Remediate => new Remediate(Profile::FacturX, xml: $xml), 'A Factur-X invoice XML is given with the level it declares, and a level with its XML.'];
        yield 'remediate factur-x level without xml' => [static fn (): Remediate => new Remediate(Profile::FacturX, level: FacturXLevel::Minimum), 'A Factur-X invoice XML is given with the level it declares'];
        yield 'remediate factur-x document type without xml' => [static fn (): Remediate => new Remediate(Profile::FacturX, documentType: 'INVOICE'), 'A Factur-X document type is given with the invoice XML.'];
        yield 'sign with an empty reason' => [static fn (): Sign => new Sign(Certificate::pkcs12(InputFile::fromContents('a.p12', 'p')), ''), 'The reason of a signature is not empty.'];
        yield 'sign with an empty location' => [static fn (): Sign => new Sign(Certificate::pkcs12(InputFile::fromContents('a.p12', 'p')), null, ' '), 'The location of a signature is not empty.'];
        yield 'sign with an empty contact' => [static fn (): Sign => new Sign(Certificate::pkcs12(InputFile::fromContents('a.p12', 'p')), null, null, ''), 'The contact of a signer is not empty.'];
        yield 'sign with an empty name' => [static fn (): Sign => new Sign(Certificate::pkcs12(InputFile::fromContents('a.p12', 'p')), null, null, null, ''), 'The name of a signer is not empty.'];
        yield 'render at 0 dpi' => [static fn (): Render => new Render(dpi: 0), 'A resolution is above 0; 0 is not.'];
        yield 'render quality 0' => [static fn (): Render => new Render(format: ImageFormat::Jpeg, quality: 0), 'A JPEG quality is from 1 to 100; 0 is not.'];
        yield 'render quality 101' => [static fn (): Render => new Render(format: ImageFormat::Jpeg, quality: 101), 'A JPEG quality is from 1 to 100; 101 is not.'];
        yield 'render png quality' => [static fn (): Render => new Render(format: ImageFormat::Png, quality: 50), 'A quality goes with the JPEG format only.'];
        yield 'render default quality' => [static fn (): Render => new Render(quality: 50), 'A quality goes with the JPEG format only.'];
        yield 'extract an unnamed attachment' => [static fn (): ExtractAttachments => new ExtractAttachments(''), 'The name of an attachment is not empty.'];
    }

    public function testEachOperationKnowsItsRoute(): void
    {
        self::assertSame('/forms/pdf/stamp-text', Operation::StampText->route());
        self::assertSame('/forms/pdf/extract-attachments', Operation::ExtractAttachments->route());
    }

    public function testTheOperationsThatRewriteTheFileAreTheOnesGotenbergLists(): void
    {
        $rewriting = array_values(array_filter(Operation::cases(), static fn (Operation $operation): bool => $operation->rewrites()));

        self::assertSame(
            ['merge', 'split', 'select', 'delete', 'insert', 'flatten-form', 'flatten-transparency', 'convert-colors', 'redact', 'optimize', 'encrypt', 'decrypt', 'remediate'],
            array_map(static fn (Operation $operation): string => $operation->value, $rewriting),
        );
    }
}
