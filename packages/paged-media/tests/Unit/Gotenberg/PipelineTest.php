<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\InvalidPipeline;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\Signing\Certificate;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthorityUrl;
use Phil\PagedMedia\Gotenberg\Stage\Attach;
use Phil\PagedMedia\Gotenberg\Stage\Decrypt;
use Phil\PagedMedia\Gotenberg\Stage\Encrypt;
use Phil\PagedMedia\Gotenberg\Stage\ExtractAttachments;
use Phil\PagedMedia\Gotenberg\Stage\ExtractText;
use Phil\PagedMedia\Gotenberg\Stage\Merge;
use Phil\PagedMedia\Gotenberg\Stage\Operation;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMedia\Gotenberg\Stage\Remediate;
use Phil\PagedMedia\Gotenberg\Stage\Render;
use Phil\PagedMedia\Gotenberg\Stage\Rotate;
use Phil\PagedMedia\Gotenberg\Stage\Sign;
use Phil\PagedMedia\Gotenberg\Stage\Stage;
use Phil\PagedMedia\Gotenberg\Stage\StampImage;
use Phil\PagedMedia\Gotenberg\Stage\StampText;
use Phil\PagedMedia\Gotenberg\Stage\Timestamp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pipeline::class)]
#[CoversClass(Operation::class)]
#[CoversClass(InvalidPipeline::class)]
final class PipelineTest extends TestCase
{
    public function testAListWritesEachStageWithItsOperationFirst(): void
    {
        $pipeline = new Pipeline(new Merge(), new StampText('Brouillon/é', 72.0, 0, 24), new Optimize(dedup: false));

        self::assertSame(
            '[{"op":"merge"},{"op":"stamp-text","text":"Brouillon/é","x":72.0,"y":0.0,"fontSize":24.0},{"op":"optimize","dedup":false}]',
            $pipeline->toJson(),
        );
        self::assertCount(3, $pipeline->stages);
    }

    public function testAListGathersTheFilesItsStagesName(): void
    {
        $logo = InputFile::fromContents('logo.png', 'png');
        $data = InputFile::fromContents('data.csv', 'a');

        $pipeline = new Pipeline(new Merge(), new StampImage($logo), new Attach($data));

        self::assertSame([$logo, $data], $pipeline->files());
        self::assertSame([], new Pipeline(new Merge())->files());
    }

    public function testAListKeepsItsStagesAsAList(): void
    {
        $merge = new Merge();
        $optimize = new Optimize();

        self::assertSame([$merge], new Pipeline(...['first' => $merge])->stages);
        self::assertSame([$merge, $optimize], new Pipeline($merge)->then(...['last' => $optimize])->stages);
    }

    public function testAListGrowsByStagesThatFollowItsRules(): void
    {
        $pipeline = new Pipeline(new Merge())->then(new Rotate(90), new Optimize());

        self::assertSame([Operation::Merge, Operation::Rotate, Operation::Optimize], array_map(static fn (Stage $stage): Operation => $stage->operation(), $pipeline->stages));
        self::assertTrue($pipeline->contains(Operation::Rotate));
        self::assertFalse($pipeline->contains(Operation::Sign));

        $this->expectException(InvalidPipeline::class);
        new Pipeline(new Encrypt('u'))->then(new Rotate(90));
    }

    /**
     * @param list<Stage> $stages
     */
    #[DataProvider('validLists')]
    public function testAListInAnOrderGotenbergTakesIsKept(array $stages): void
    {
        self::assertCount(\count($stages), new Pipeline(...$stages)->stages);
    }

    /**
     * @return iterable<string, array{list<Stage>}>
     */
    public static function validLists(): iterable
    {
        $tsa = new TimestampAuthorityUrl('https://tsa.example.com');
        $sign = new Sign(Certificate::pkcs12(InputFile::fromContents('a.p12', 'p')));

        yield 'decrypt first' => [[new Decrypt('p'), new Rotate(90), new Optimize()]];
        yield 'decrypt then encrypt' => [[new Decrypt('p'), new Encrypt('n')]];
        yield 'render last' => [[new Merge(), new Render()]];
        yield 'render alone' => [[new Render()]];
        yield 'after remediate' => [[new Remediate(Profile::PdfA2b), new Optimize(), new Sign(Certificate::pkcs12(InputFile::fromContents('a.p12', 'p'))), new Timestamp($tsa), new ExtractText()]];
        yield 'encrypt an accessible pdf' => [[new Remediate(Profile::PdfUa1), new Encrypt('u'), new Optimize(), new Render()]];
        yield 'encrypt then optimize' => [[new Encrypt('u'), new Optimize(), new ExtractAttachments()]];
        yield 'sign then timestamp' => [[$sign, new Timestamp($tsa), new Render()]];
        yield 'timestamp then sign' => [[new Timestamp($tsa), $sign]];
        yield 'remediate then encrypt then optimize' => [[new Remediate(Profile::PdfUa2), new Encrypt('u'), new Optimize()]];
    }

    /**
     * @param list<Stage> $stages
     */
    #[DataProvider('invalidLists')]
    public function testAListInAnOrderGotenbergRefusesIsRefusedBeforeItIsSent(array $stages, string $message): void
    {
        $this->expectException(InvalidPipeline::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Pipeline(...$stages);
    }

    /**
     * @return iterable<string, array{list<Stage>, string}>
     */
    public static function invalidLists(): iterable
    {
        $tsa = new TimestampAuthorityUrl('https://tsa.example.com');
        $sign = new Sign(Certificate::pkcs12(InputFile::fromContents('a.p12', 'p')));

        yield 'empty' => [[], 'A list of stages holds one stage at least.'];
        yield 'decrypt second' => [[new Merge(), new Decrypt()], '"decrypt" comes first; it is stage 1.'];
        yield 'render before another' => [[new Render(), new Merge()], '"render" comes last; it is stage 0 of 1.'];
        yield 'extract text in the middle' => [[new Merge(), new ExtractText(), new Optimize()], '"extract-text" comes last; it is stage 1 of 2.'];
        yield 'two terminals' => [[new ExtractText(), new Render()], '"extract-text" comes last'];
        yield 'two remediations' => [[new Remediate(Profile::PdfA2b), new Remediate(Profile::PdfUa1)], 'A list holds one "remediate" at most.'];
        yield 'two encryptions' => [[new Encrypt('a'), new Encrypt('b')], 'A list holds one "encrypt" at most.'];
        yield 'two signatures' => [[$sign, $sign], 'A list holds one "sign" at most.'];
        yield 'two timestamps' => [[new Timestamp($tsa), new Timestamp($tsa)], 'A list holds one "timestamp" at most.'];
        yield 'rotate after remediate' => [[new Remediate(Profile::PdfA2b), new Rotate(90)], '"rotate" cannot follow "remediate": only encrypt, optimize, sign, timestamp, render, extract-text and extract-attachments may.'];
        yield 'rotate after encrypt' => [[new Encrypt('u'), new Rotate(90)], '"rotate" cannot follow "encrypt": only optimize, render, extract-text and extract-attachments may.'];
        yield 'sign after remediate and encrypt' => [[new Remediate(Profile::PdfUa1), new Encrypt('u'), $sign], '"sign" cannot follow "encrypt"'];
        yield 'optimize after sign' => [[$sign, new Optimize()], '"optimize" cannot follow "sign": only sign, timestamp, render, extract-text and extract-attachments may.'];
        yield 'stamp after timestamp' => [[new Timestamp($tsa), new StampText('A')], '"stamp-text" cannot follow "timestamp"'];
        yield 'encrypt with a timestamp' => [[new Encrypt('u'), new Optimize(), new Merge()], '"merge" cannot follow "encrypt"'];
        yield 'encrypt before a timestamp' => [[new Timestamp($tsa), new Encrypt('u')], '"encrypt" cannot follow "timestamp"'];
        yield 'encrypt an archive' => [[new Remediate(Profile::PdfA2b), new Encrypt('u')], '"encrypt" does not combine with "sign", "timestamp", or a PDF/A or Factur-X "remediate".'];
        yield 'encrypt a factur-x' => [[new Remediate([Profile::FacturX, Profile::PdfUa1]), new Encrypt('u')], '"encrypt" does not combine'];
        yield 'decrypt and sign' => [[new Decrypt('p'), $sign], '"decrypt" does not combine with "sign" or "timestamp": sign the decrypted PDF in a request of its own.'];
        yield 'decrypt and timestamp' => [[new Decrypt('p'), new Timestamp($tsa)], '"decrypt" does not combine with "sign" or "timestamp"'];
    }

    public function testEachOperationKnowsTheRulesOfItsPlace(): void
    {
        self::assertSame([Operation::Render, Operation::ExtractText, Operation::ExtractAttachments], array_values(array_filter(Operation::cases(), static fn (Operation $operation): bool => $operation->isTerminal())));
        self::assertSame([Operation::Encrypt, Operation::Remediate, Operation::Sign, Operation::Timestamp], array_values(array_filter(Operation::cases(), static fn (Operation $operation): bool => $operation->runsOnce())));
        self::assertNull(Operation::Merge->followers());
        self::assertSame([Operation::Optimize], Operation::Encrypt->followers());
    }
}
