<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Editing;

use LogicException;
use Override;
use Phil\ContentBundle\Application\DataMapper\BodyDataMapper;
use Phil\ContentBundle\Application\Editing\AuthorRawHtml;
use Phil\ContentBundle\Application\Editing\WorkingCopies;
use Phil\ContentBundle\Application\Exception\PublicationLosesContent;
use Phil\ContentBundle\Application\Publication\ContentPublisher;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Infrastructure\Markup\UjoBodyConverter;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryWorkingCopies;
use Phil\ContentBundle\Tests\Unit\Fixture\LevelledReaders;
use Phil\ContentBundle\Tests\Unit\Fixture\SwitchableRawHtml;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The right to write raw HTML is checked on the author of each revision of a body, never on whoever publishes it: the
 * raw HTML of an author without it is published as text, even by a publisher who holds it.
 */
#[CoversClass(AuthorRawHtml::class)]
#[CoversClass(WorkingCopies::class)]
#[CoversClass(BodyDataMapper::class)]
#[CoversClass(ContentPublisher::class)]
#[CoversClass(UjoBodyConverter::class)]
#[CoversClass(WorkingCopy::class)]
final class RawHtmlAuthorshipTest extends TestCase
{
    private const string RAW = "Le site **ouvre**.\n\n<div><p>Bonjour</p><img src=\"https://example.org/x.png\" alt=\"\"></div>";

    private const string RAW_BLOCK = '"type":"raw"';

    private const string AS_TEXT = '&lt;div&gt;&lt;p&gt;Bonjour';

    private SwitchableRawHtml $user;

    private InMemoryContents $contents;

    private InMemoryWorkingCopies $copies;

    #[Override]
    protected function setUp(): void
    {
        $this->user = new SwitchableRawHtml();
        $this->contents = new InMemoryContents(compiler: new DefaultCompiler(), purifier: SanitizerPurifier::defaultPolicies(), rawHtml: $this->user);
        $this->copies = new InMemoryWorkingCopies();
    }

    public function testAPublisherWhoHoldsTheRightPublishesTheRawHtmlOfAnAuthorWithoutItAsText(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['bodySource' => self::RAW]);

        $this->user->granted = true;
        $this->contents->manager->publish($reference, 'fr');

        $live = $this->contents->manager->live($reference, 'fr');
        self::assertIsString($live['bodySource']);
        self::assertStringNotContainsString(self::RAW_BLOCK, $live['bodySource']);
        self::assertStringContainsString(self::AS_TEXT, $live['bodySource']);
        self::assertStringNotContainsString('<img', $this->text($live['bodyHtml']));
        self::assertFalse($live['bodyRawHtml']);
    }

    public function testThePublisherDoesNotNeedTheRightToPublishTheRawHtmlOfAnAuthorWhoHeldIt(): void
    {
        $this->user->granted = true;
        $reference = $this->contents->manager->create('example', 'fr', ['bodySource' => self::RAW]);
        self::assertTrue($this->contents->manager->draft($reference, 'fr')['bodyRawHtml']);

        $this->user->granted = false;
        $this->contents->manager->publish($reference, 'fr');

        $live = $this->contents->manager->live($reference, 'fr');
        self::assertStringContainsString(self::RAW_BLOCK, $this->text($live['bodySource']));
        self::assertTrue($live['bodyRawHtml']);
    }

    /**
     * An editor without the right who saves the draft without changing its body leaves it to its author; one who
     * changes it becomes its author.
     */
    public function testAnEditorWithoutTheRightKeepsTheRawHtmlOfTheAuthorUntilTheyChangeIt(): void
    {
        $this->user->granted = true;
        $reference = $this->contents->manager->create('example', 'fr', ['bodySource' => self::RAW]);

        $this->user->granted = false;
        $this->contents->manager->edit($reference, 'fr', ['excerptTitle' => 'Ouverture', 'bodySource' => self::RAW]);
        $this->contents->manager->publish($reference, 'fr');
        self::assertStringContainsString(self::RAW_BLOCK, $this->text($this->contents->manager->live($reference, 'fr')['bodySource']));

        $this->contents->manager->edit($reference, 'fr', ['bodySource' => self::RAW."\n\nFin."]);
        $this->user->granted = true;
        $this->contents->manager->publish($reference, 'fr');
        self::assertStringContainsString(self::AS_TEXT, $this->text($this->contents->manager->live($reference, 'fr')['bodySource']));
    }

    /**
     * The publication reads what the conversion loses at the level of the author: the source of an author who holds
     * the right loses nothing that the source of another author would.
     */
    public function testThePublicationReadsTheLossesAtTheLevelOfTheAuthor(): void
    {
        $user = new SwitchableRawHtml(true);
        $contents = new InMemoryContents(compiler: new DefaultCompiler(readers: ['levelled' => new LevelledReaders()]), purifier: SanitizerPurifier::defaultPolicies(), rawHtml: $user);
        $trusted = $contents->manager->create('example', 'fr', ['bodySource' => '<b>x</b>', 'bodyFormat' => 'levelled']);
        $user->granted = false;
        $other = $contents->manager->create('example', 'fr', ['bodySource' => '<b>x</b>', 'bodyFormat' => 'levelled']);

        $contents->manager->publish($trusted, 'fr');
        self::assertSame('published', $contents->manager->status($trusted)[0]->place);

        $this->expectException(PublicationLosesContent::class);
        $contents->manager->publish($other, 'fr');
    }

    /**
     * A named draft written by an author without the right stays text when an editor who holds it applies it.
     */
    public function testANamedDraftKeepsTheRightOfItsAuthorWhoeverAppliesIt(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['bodySource' => 'Bonjour']);
        $copies = $this->workingCopies();

        self::assertFalse($copies->saveNamed($reference, 'fr', 'Court', 'mallory', ['bodySource' => self::RAW])->rawHtml());
        $this->user->granted = true;
        $copies->applyNamed($reference, 'fr', 'Court');
        self::assertFalse($this->contents->manager->draft($reference, 'fr')['bodyRawHtml']);
        self::assertTrue($this->user->isGranted(), 'the editor keeps their own right');
        self::assertTrue($this->contents->rawHtml?->isGranted(), 'outside a working copy, the author is the editor');

        self::assertTrue($copies->saveNamed($reference, 'fr', 'Long', 'alice', ['bodySource' => self::RAW."\n\nFin."])->rawHtml());
        $this->user->granted = false;
        $copies->applyNamed($reference, 'fr', 'Long');
        self::assertTrue($this->contents->manager->draft($reference, 'fr')['bodyRawHtml']);
    }

    public function testAnAutosaveKeepsTheRightOfItsAuthor(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['bodySource' => 'Bonjour']);
        $copies = $this->workingCopies();

        $this->user->granted = true;
        $copies->autosave($reference, 'fr', 'alice', ['bodySource' => self::RAW]);
        $again = $copies->autosave($reference, 'fr', 'alice', ['bodySource' => self::RAW."\n\nFin."]);
        self::assertTrue($again->rawHtml(), 'saved again by an author who holds it');
        self::assertSame(['bodySource' => self::RAW."\n\nFin."], $again->data());
        $this->user->granted = false;
        $copies->applyAutosave($reference, 'fr', 'alice');

        self::assertTrue($this->contents->manager->draft($reference, 'fr')['bodyRawHtml']);
        self::assertNull($copies->autosaved($reference, 'fr', 'alice'));
    }

    /**
     * A named draft is found by its trimmed name, saved again under it, and refused when it does not exist, as an
     * autosave the author does not have.
     */
    public function testWorkingCopiesAreFoundByTheirTrimmedName(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['bodySource' => 'Bonjour']);
        $copies = $this->workingCopies();

        $copies->saveNamed($reference, 'fr', 'Court', 'alice', ['bodySource' => 'Un']);
        $copies->saveNamed($reference, 'fr', '  Court ', 'bob', ['bodySource' => 'Deux']);
        self::assertCount(1, $copies->named($reference, 'fr'));

        $copies->applyNamed($reference, 'fr', ' Court  ');
        self::assertSame('Deux', $this->contents->manager->draft($reference, 'fr')['bodySource']);

        try {
            $copies->applyNamed($reference, 'fr', 'Long');
            self::fail('No named draft is called so.');
        } catch (ContentNotFound) {
        }

        $this->expectException(ContentNotFound::class);
        $copies->applyAutosave($reference, 'fr', 'alice');
    }

    /**
     * Without raw HTML configured, a working copy records nothing and is written as any edit.
     */
    public function testWithoutRawHtmlConfiguredAWorkingCopyIsWrittenAsAnyEdit(): void
    {
        $contents = new InMemoryContents(compiler: new DefaultCompiler(), purifier: SanitizerPurifier::defaultPolicies());
        $reference = $contents->manager->create('example', 'fr', ['bodySource' => 'Bonjour']);
        $copies = new WorkingCopies($this->copies, $contents->manager, $contents->transaction, $contents->clock);

        self::assertFalse($copies->saveNamed($reference, 'fr', 'Court', 'alice', ['bodySource' => self::RAW])->rawHtml());
        $copies->applyNamed($reference, 'fr', 'Court');

        self::assertSame(self::RAW, $contents->manager->draft($reference, 'fr')['bodySource']);
        self::assertFalse($contents->manager->draft($reference, 'fr')['bodyRawHtml']);
    }

    /**
     * `as()` gives the user's own permission back once the edit is over, even when it fails, and nests.
     */
    public function testTheAuthorIsTheUserAgainAfterAWorkingCopy(): void
    {
        $author = new AuthorRawHtml($this->user);
        self::assertFalse($author->isGranted());

        self::assertSame('done', $author->as(true, static function () use ($author): string {
            self::assertTrue($author->isGranted());
            self::assertFalse($author->userIsGranted());
            $author->as(false, static function () use ($author): null {
                self::assertFalse($author->isGranted());

                return null;
            });
            self::assertTrue($author->isGranted(), 'the outer author is back');

            return 'done';
        }));
        self::assertFalse($author->isGranted());

        $this->user->granted = true;

        try {
            $failure = $author->as(false, static fn (): string => throw new LogicException('refused'));
        } catch (LogicException $logicException) {
            $failure = $logicException->getMessage();
        }

        self::assertSame('refused', $failure, 'the failure goes through');

        self::assertTrue($author->isGranted());
    }

    private function workingCopies(): WorkingCopies
    {
        return new WorkingCopies($this->copies, $this->contents->manager, $this->contents->transaction, $this->contents->clock, $this->contents->rawHtml);
    }

    private function text(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }
}
