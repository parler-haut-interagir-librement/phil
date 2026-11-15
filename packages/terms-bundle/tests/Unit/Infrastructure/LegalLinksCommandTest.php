<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNotices\PlainTextBody;
use Phil\LegalNotices\SiteIdentity;
use Phil\LegalNoticesBundle\Infrastructure\Source\ConfigDocumentSource;
use Phil\LegalNoticesBundle\Infrastructure\Twig\FooterLinks;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Phil\TermsBundle\Infrastructure\Console\LegalLinksCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

#[CoversClass(LegalLinksCommand::class)]
final class LegalLinksCommandTest extends TestCase
{
    private const string COMPLETE = '<a href="/legal">a</a><a href="/privacy">b</a><a href="/terms">c</a>';

    private RequestStack $requests;

    protected function setUp(): void
    {
        $this->requests = new RequestStack();
    }

    public function testEveryLinkExistsAndTheLayoutShowsThem(): void
    {
        $tester = $this->tester(self::COMPLETE);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('The legal links exist and the layout of the terms shows them.', $tester->getDisplay());
    }

    public function testALayoutWithoutTheLinksIsReported(): void
    {
        $tester = $this->tester('<a href="/legal">a</a><p>/privacy</p>');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString("not_in_layout: layout does not link to /privacy; include @PhilLegalNotices/_links.html.twig.\n", $tester->getDisplay());
        self::assertStringContainsString("not_in_layout: layout does not link to /terms; include @PhilLegalNotices/_links.html.twig.\n", $tester->getDisplay());
        self::assertStringNotContainsString('link to /legal', $tester->getDisplay());
        self::assertStringContainsString('2 problem(s) found.', $tester->getDisplay());
    }

    public function testAnAddressIsLookedForAsTheLayoutEscapesIt(): void
    {
        $urls = new class implements UrlGeneratorInterface {
            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            /**
             * @param array<mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/a?'.$name.'&b';
            }
        };
        $tester = new CommandTester($this->command('<a href="/a?phil_legal_notices_legal_notice&amp;b">a</a><a href="/a?phil_legal_notices_privacy_policy&amp;b">b</a><a href="/a?phil_terms_policy&amp;b">c</a>', $this->routes(), '2026-12-15', [new Policy('terms', true)], $urls));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testADocumentWithoutAVersionInForceIsReported(): void
    {
        $tester = $this->tester(self::COMPLETE, now: '2026-11-01');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString("missing_document: the privacy_policy has no version in force.\n", $tester->getDisplay());
        self::assertStringNotContainsString('legal_notice', $tester->getDisplay());
    }

    public function testARequiredPolicyMissingFromTheLinksIsReported(): void
    {
        $tester = $this->tester(self::COMPLETE, policies: [new Policy('terms', true), new Policy('privacy', true), new Policy('optional', false)]);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('missing_policy: the policy "privacy" is not in phil_legal_notices.footer.terms_policies.', $tester->getDisplay());
        self::assertStringNotContainsString('"terms"', $tester->getDisplay());
        self::assertStringNotContainsString('optional', $tester->getDisplay());
    }

    public function testARouteNotImportedIsReported(): void
    {
        $routes = new RouteCollection();
        $tester = $this->tester(self::COMPLETE, $routes);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('missing_route: Unable to generate a URL for the named route "phil_legal_notices_legal_notice"', $tester->getDisplay());
        self::assertStringContainsString('1 problem(s) found.', $tester->getDisplay());
    }

    public function testALayoutThatCannotBeRenderedIsReported(): void
    {
        $tester = $this->tester('{{ nope() }');

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertMatchesRegularExpression('/^unreadable_layout: layout: /m', $tester->getDisplay());
        self::assertSame(0, $this->depth());
    }

    public function testTheLayoutIsRenderedWithARequestLeftNoTrace(): void
    {
        $tester = $this->tester('{% if has_request() %}'.self::COMPLETE.'{% endif %}');

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(0, $this->depth());
    }

    public function testTheCommandIsNamed(): void
    {
        self::assertSame('phil:terms:check-legal-links', $this->command(self::COMPLETE, $this->routes(), '2026-12-15', [new Policy('terms', true)])->getName());
    }

    private function depth(): int
    {
        return $this->requests->getCurrentRequest() instanceof Request ? 1 : 0;
    }

    private function routes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('phil_legal_notices_legal_notice', new Route('/legal'));
        $routes->add('phil_legal_notices_privacy_policy', new Route('/privacy'));
        $routes->add('phil_terms_policy', new Route('/{policy}'));

        return $routes;
    }

    /**
     * @param list<Policy> $policies
     */
    private function tester(string $layout, ?RouteCollection $routes = null, string $now = '2026-12-15', array $policies = [new Policy('terms', true)]): CommandTester
    {
        return new CommandTester($this->command($layout, $routes ?? $this->routes(), $now, $policies));
    }

    /**
     * @param list<Policy> $policies
     */
    private function command(string $layout, RouteCollection $routes, string $now, array $policies, ?UrlGeneratorInterface $urls = null): LegalLinksCommand
    {
        $twig = new Environment(new ArrayLoader(['layout' => $layout]), ['strict_variables' => true]);
        $twig->addFunction(new TwigFunction('has_request', fn (): bool => $this->requests->getCurrentRequest() instanceof Request));

        $source = new ConfigDocumentSource([
            'legal_notice' => [['number' => 1, 'format' => 'text', 'state' => 'published', 'published_at' => '2026-10-01', 'effective_at' => null, 'bodies' => ['en' => 'x'], 'summaries' => []]],
            'privacy_policy' => [['number' => 1, 'format' => 'text', 'state' => 'published', 'published_at' => '2026-12-01', 'effective_at' => null, 'bodies' => ['en' => 'x'], 'summaries' => []]],
        ]);
        $notices = new LegalNotices($source, new PlainTextBody(), new SiteIdentity(['publisher_name' => 'Example']));
        $links = new FooterLinks($notices, $urls ?? new UrlGenerator($routes, new RequestContext()), new MockClock(new DateTimeImmutable($now)), ['terms']);

        return new LegalLinksCommand($links, new Policies(...$policies), $twig, $this->requests, 'layout');
    }
}
