<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure;

use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\EmailComponents;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\RenderMode;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\RenderStash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use Twig\Error\RuntimeError;
use Twig\Extension\AttributeExtension;
use Twig\Node\Nodes;
use Twig\TwigFilter;
use Twig\TwigFunction;

#[CoversClass(EmailComponents::class)]
final class EmailComponentsTest extends TestCase
{
    private EmailComponents $components;

    private RenderStash $stash;

    protected function setUp(): void
    {
        $this->components = new EmailComponents(Theme::create(['primary' => '#123456', 'primary_text' => '#fefefe', 'panel' => '#abcdef', 'border' => '#010203', 'muted' => '#777']));
        $this->stash = new RenderStash(
            new RenderContext(TemplateKey::fromString('t'), Site::shared(), Locale::fromString('en'), ['vip']),
            TemplateDefinition::create('s', 'b', segments: ['vip', 'new'], embeds: ['logo' => 'logo.png']),
        );
    }

    public function testTheComponentsTheSandboxOffers(): void
    {
        $extension = new AttributeExtension(EmailComponents::class);
        $functions = $extension->getFunctions();
        $filters = $extension->getFilters();

        self::assertSame(['button', 'table', 'image', 'segment'], array_map(static fn (TwigFunction $function): string => $function->getName(), $functions));
        self::assertSame(['panel', 'footer'], array_map(static fn (TwigFilter $filter): string => $filter->getName(), $filters));
        self::assertSame(['html'], $functions[0]->getSafe(new Nodes()));
        self::assertTrue($functions[0]->needsContext());
        self::assertTrue($functions[3]->needsContext());
        self::assertSame([], $functions[3]->getSafe(new Nodes()));
        self::assertSame('html', $filters[0]->getPreEscape());
        self::assertSame(['html'], $filters[1]->getSafe(new Nodes()));
    }

    public function testAButtonIsATableCellWithItsLink(): void
    {
        $html = $this->components->button($this->context(), 'Open <now>', 'https://Example.com/a?b=1&c=2', ['align' => 'center']);

        self::assertSame('<table role="presentation" border="0" cellpadding="0" cellspacing="0" class="phil-email-button" align="center" style="margin:16px 0;border-collapse:separate;"><tr><td align="center" bgcolor="#123456" style="border-radius:6px;background-color:#123456;"><a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener" style="display:inline-block;padding:12px 24px;font-family:-apple-system, &apos;Segoe UI&apos;, Roboto, Helvetica, Arial, sans-serif;font-size:16px;font-weight:600;line-height:1.25;color:#fefefe;text-decoration:none;border-radius:6px;">Open &lt;now&gt;</a></td></tr></table>', $html);
        self::assertStringContainsString('align="left"', $this->components->button($this->context(), 'a', 'http://example.com/'));
        self::assertStringContainsString('href="mailto:ada@example.com"', $this->components->button($this->context(), 'a', 'MAILTO:ada@example.com'));
    }

    public function testInTextAButtonIsItsLabelAndItsAddress(): void
    {
        $this->stash->mode = RenderMode::Text;

        self::assertSame('Open: https://example.com/', $this->components->button($this->context(), 'Open', 'https://example.com/'));
        self::assertSame('Write: ada@example.com', $this->components->button($this->context(), 'Write', 'mailto:ada@example.com'));
        self::assertSame("A | B\n1 | 2", $this->components->table($this->context(), [[1, 2]], ['header' => ['A', 'B']]));
        self::assertSame("1 | \n3 | 4", $this->components->table($this->context(), [[1, null], [3, 4]]));
        self::assertSame('[Logo]', $this->components->image($this->context(), 'cid:logo', 'Logo'));
        self::assertSame('', $this->components->image($this->context(), 'cid:logo', ''));
        self::assertSame('x', $this->components->panel($this->context(), 'x'));
        self::assertSame('y', $this->components->footer($this->context(), 'y'));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function refusedLinks(): iterable
    {
        yield 'relative' => ['/a', 'An address is an absolute http or https address.'];
        yield 'javascript' => ['javascript:alert(1)', 'An address is an absolute http or https address.'];
        yield 'bad mailto' => ['mailto:nobody', 'A mailto address holds one email address.'];
        yield 'list' => [['x'], 'An address is a text.'];
    }

    #[DataProvider('refusedLinks')]
    public function testAButtonRefusesAnAddress(mixed $url, string $error): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessageIsOrContains($error);

        $this->components->button($this->context(), 'a', $url);
    }

    public function testAButtonRefusesAnAlignmentOrALabel(): void
    {
        try {
            $this->components->button($this->context(), 'a', 'https://example.com/', ['align' => 'middle']);
            self::fail('The alignment is refused.');
        } catch (RuntimeError $runtimeError) {
            self::assertSame('A button is aligned "left", "center" or "right".', $runtimeError->getRawMessage());
        }

        $this->expectExceptionMessageIsOrContains('The label of a button is a text.');
        $this->components->button($this->context(), null, 'https://example.com/');
    }

    public function testATableEscapesItsCells(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'S';
            }
        };
        $html = $this->components->table($this->context(), [['<b>', 1.5, $stringable]], ['header' => ['A&B'], 'caption' => 'Cap<']);

        self::assertSame('<table border="0" cellpadding="0" cellspacing="0" width="100%" class="phil-email-table" style="width:100%;margin:16px 0;border-collapse:collapse;font-family:-apple-system, &apos;Segoe UI&apos;, Roboto, Helvetica, Arial, sans-serif;font-size:15px;"><caption style="text-align:left;padding:0 0 8px;font-weight:600;">Cap&lt;</caption><thead><tr><th scope="col" align="left" style="padding:8px;border-bottom:2px solid #010203;text-align:left;">A&amp;B</th></tr></thead><tbody><tr><td style="padding:8px;border-bottom:1px solid #010203;">&lt;b&gt;</td><td style="padding:8px;border-bottom:1px solid #010203;">1.5</td><td style="padding:8px;border-bottom:1px solid #010203;">S</td></tr></tbody></table>', $html);
        self::assertStringNotContainsString('<thead>', $this->components->table($this->context(), []));
    }

    public function testATableRefusesWhatIsNotRows(): void
    {
        foreach ([['x', 'The rows of a table are a list of lists of cells.'], [['x'], 'A row of a table is a list of cells.'], [[[['x']]], 'A cell of a table is a text.']] as [$rows, $error]) {
            try {
                $this->components->table($this->context(), $rows);
                self::fail('The rows are refused.');
            } catch (RuntimeError $runtimeError) {
                self::assertSame($error, $runtimeError->getRawMessage());
            }
        }
    }

    public function testAnImageIsEmbeddedOrHttps(): void
    {
        self::assertSame('<img src="cid:logo" alt="Logo &quot;x&quot;" width="120" style="display:block;border:0;outline:none;text-decoration:none;max-width:100%;height:auto;">', $this->components->image($this->context(), 'cid:logo', 'Logo "x"', ['width' => 120]));
        self::assertSame('<img src="https://example.com/a.png" alt="" style="display:block;border:0;outline:none;text-decoration:none;max-width:100%;height:auto;">', $this->components->image($this->context(), 'https://Example.com/a.png', ''));
        self::assertStringContainsString('width="1200"', $this->components->image($this->context(), 'cid:logo', 'x', ['width' => 1200]));
        self::assertStringContainsString('width="1"', $this->components->image($this->context(), 'cid:logo', 'x', ['width' => 1]));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function refusedImages(): iterable
    {
        yield 'not embedded' => ['cid:other', [], 'The image "other" is not one the template embeds.'];
        yield 'http' => ['http://example.com/a.png', [], 'An address is an absolute https address.'];
        yield 'mailto' => ['mailto:ada@example.com', [], 'An address is an absolute https address.'];
        yield 'width zero' => ['cid:logo', ['width' => 0], 'The width of an image is an integer from 1 to 1200.'];
        yield 'width too large' => ['cid:logo', ['width' => 1201], 'The width of an image is an integer from 1 to 1200.'];
        yield 'width as text' => ['cid:logo', ['width' => '10'], 'The width of an image is an integer from 1 to 1200.'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('refusedImages')]
    public function testAnImageRefuses(string $src, array $options, string $error): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessageIsOrContains($error);

        $this->components->image($this->context(), $src, 'x', $options);
    }

    public function testASegmentMustBeDeclared(): void
    {
        self::assertTrue($this->components->segment($this->context(), 'vip'));
        self::assertFalse($this->components->segment($this->context(), 'new'));

        foreach (['old' => '"old" is not a segment the template declares; declare it in its segments.', 'list' => '"array" is not a segment the template declares; declare it in its segments.'] as $case => $error) {
            try {
                $this->components->segment($this->context(), 'list' === $case ? [] : $case);
                self::fail('The segment is refused.');
            } catch (RuntimeError $runtimeError) {
                self::assertSame($error, $runtimeError->getRawMessage());
            }
        }
    }

    public function testAPanelAndAFooterWrapTheirContent(): void
    {
        self::assertSame('<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="phil-email-panel" style="width:100%;margin:16px 0;"><tr><td bgcolor="#abcdef" style="padding:16px 20px;border-radius:6px;background-color:#abcdef;"><p>x</p></td></tr></table>', $this->components->panel($this->context(), '<p>x</p>'));
        self::assertSame('<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" class="phil-email-footer" style="width:100%;margin:24px 0 0;"><tr><td align="center" style="padding:16px 0 0;border-top:1px solid #010203;font-size:13px;line-height:1.5;color:#777;text-align:center;">y</td></tr></table>', $this->components->footer($this->context(), 'y'));
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [RenderStash::CONTEXT_NAME => $this->stash];
    }
}
