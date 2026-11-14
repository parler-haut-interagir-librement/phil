<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Application;

use Phil\MentionsBundle\Application\Member;
use Phil\MentionsBundle\Application\Rendering\MentionLinker;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use Phil\MentionsBundle\Tests\Unit\Fixture\Directory;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MentionLinker::class)]
final class MentionLinkerTest extends TestCase
{
    public function testHandlesBecomeLinksToProfiles(): void
    {
        $directory = new Directory();

        self::assertSame(
            'Hi <a class="mention" href="/members/ada" data-mention="user:1">@Ada</a>, <a class="mention" href="https://example.test/bob" data-mention="user:2">@bob</a>, <span class="mention" data-mention="user:3">@chloé</span> and @nobody; <a class="mention" href="/members/ada" data-mention="user:1">@ada</a>.',
            new MentionLinker($directory, MentionPattern::default(), 20)->link('Hi @Ada, @bob, @chloé and @nobody; @ada.'),
        );
        self::assertSame([['resolve', ['Ada', 'bob', 'chloé', 'nobody']]], $directory->calls);
    }

    public function testLinksCodeAndScriptsAreNotRead(): void
    {
        $html = '<p>@ada <a href="/x">@ada</a> <code>@ada</code> <pre><code>@ada</code> @ada</pre> <SCRIPT>@ada</SCRIPT> <style>@ada</style> <textarea>@ada</textarea> <abbr>@ada</abbr></p></a></pre>@ada';

        self::assertSame(
            '<p><span class="mention" data-mention="user:3">@ada</span> <a href="/x">@ada</a> <code>@ada</code> <pre><code>@ada</code> @ada</pre> <SCRIPT>@ada</SCRIPT> <style>@ada</style> <textarea>@ada</textarea> <abbr><span class="mention" data-mention="user:3">@ada</span></abbr></p></a></pre><span class="mention" data-mention="user:3">@ada</span>',
            new MentionLinker($this->directory(new Member(ResourceReference::of('user', '3'), 'ada', 'Ada')), MentionPattern::default(), 20)->link($html),
        );
    }

    public function testAnUnsafeOrForeignAddressGivesASpan(): void
    {
        foreach (['javascript:alert(1)', 'mailto:ada@example.test', '//evil.test/x"><b>'] as $url) {
            $linker = new MentionLinker($this->directory(new Member(ResourceReference::of('user', '"1'), 'ada', 'Ada', $url)), MentionPattern::default(), 20);

            self::assertStringStartsWith('<span class="mention" data-mention="user:&quot;1">@ada</span>', $linker->link('@ada'), $url);
        }

        $linker = new MentionLinker($this->directory(new Member(ResourceReference::of('user', '1'), 'ada', 'Ada', '/m?a=1&b="2"')), MentionPattern::default(), 20);
        self::assertSame('<a class="mention" href="/m?a=1&amp;b=&quot;2&quot;" data-mention="user:1">@ada</a>', $linker->link('@ada'));
    }

    public function testNothingToLinkAsksNothing(): void
    {
        $directory = new Directory();
        $linker = new MentionLinker($directory, MentionPattern::default(), 20);

        self::assertSame('no mention', $linker->link('no mention'));
        self::assertSame('mail@example.test <a href="/">@ada</a>', $linker->link('mail@example.test <a href="/">@ada</a>'));
        self::assertSame('@nobody', $linker->link('@nobody'));
        self::assertSame([['resolve', ['nobody']]], $directory->calls);
    }

    public function testOnlyTheFirstHandlesAreResolved(): void
    {
        $directory = new Directory();

        self::assertSame(
            '<a class="mention" href="/members/ada" data-mention="user:1">@ada</a> <a class="mention" href="/members/ada" data-mention="user:1">@ADA</a> @bob',
            new MentionLinker($directory, MentionPattern::default(), 1)->link('@ada @ADA @bob'),
        );
        self::assertSame([['resolve', ['ada']]], $directory->calls);
    }

    public function testHandlesAreComparedWithoutCaseInAnyScript(): void
    {
        $directory = $this->directory(new Member(ResourceReference::of('user', '4'), 'Élodie', 'Élodie', '/m/élodie'));

        self::assertSame(
            '<a class="mention" href="/m/élodie" data-mention="user:4">@ÉLODIE</a> <a class="mention" href="/m/élodie" data-mention="user:4">@élodie</a>',
            new MentionLinker($directory, MentionPattern::default(), 20)->link('@ÉLODIE @élodie'),
        );
        self::assertSame([['resolve', ['ÉLODIE']]], $directory->calls);
    }

    public function testQuotesAreEscapedInAttributes(): void
    {
        $linker = new MentionLinker($this->directory(new Member(ResourceReference::of('user', "o'k"), 'ok', 'OK', "/m/o'k")), MentionPattern::default(), 20);

        self::assertSame('<a class="mention" href="/m/o&apos;k" data-mention="user:o&apos;k">@ok</a>', $linker->link('@ok'));
    }

    public function testATextThatIsNotUtf8IsLeftAsItIs(): void
    {
        self::assertSame(
            "\xff @ada <b></b> ".'<a class="mention" href="/members/ada" data-mention="user:1">@ada</a>',
            new MentionLinker(new Directory(), MentionPattern::default(), 20)->link("\xff @ada <b></b> @ada"),
        );
    }

    private function directory(Member $member): Directory
    {
        $directory = new Directory();
        $directory->members = [$member];

        return $directory;
    }
}
