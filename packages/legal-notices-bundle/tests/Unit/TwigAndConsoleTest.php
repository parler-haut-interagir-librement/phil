<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use DateTimeImmutable;
use Phil\LegalNotices\Event\DocumentPublished;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNotices\PlainTextBody;
use Phil\LegalNotices\SiteIdentity;
use Phil\LegalNoticesBundle\Application\Contract\FooterLinkProvider;
use Phil\LegalNoticesBundle\Infrastructure\Console\AnnounceCommand;
use Phil\LegalNoticesBundle\Infrastructure\Console\CheckCommand;
use Phil\LegalNoticesBundle\Infrastructure\Source\ConfigDocumentSource;
use Phil\LegalNoticesBundle\Infrastructure\Twig\FooterLinks;
use Phil\LegalNoticesBundle\Infrastructure\Twig\KitComponents;
use Phil\LegalNoticesBundle\Infrastructure\Twig\LegalNoticesExtension;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\Announcements;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(FooterLinks::class)]
#[CoversClass(LegalNoticesExtension::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(CheckCommand::class)]
#[CoversClass(AnnounceCommand::class)]
final class TwigAndConsoleTest extends TestCase
{
    public function testTheFooterLinks(): void
    {
        $links = new FooterLinks(Kit::notices(), $this->urls(), new MockClock(new DateTimeImmutable('2026-11-15')), ['terms', 'code_of_conduct']);

        self::assertSame([
            ['url' => '/legal', 'label' => 'link.legal_notice', 'domain' => 'phil_legal_notices'],
            ['url' => '/terms/terms', 'label' => 'policy.terms', 'domain' => 'phil_terms'],
            ['url' => '/terms/code_of_conduct', 'label' => 'policy.code_of_conduct', 'domain' => 'phil_terms'],
        ], $links->links());
        self::assertSame([
            ['url' => '/legal', 'label' => 'link.legal_notice', 'domain' => 'phil_legal_notices'],
            ['url' => '/privacy', 'label' => 'link.privacy_policy', 'domain' => 'phil_legal_notices'],
        ], new FooterLinks(Kit::notices(), $this->urls(), new MockClock(new DateTimeImmutable('2026-12-01')))->links());

        self::assertSame($links->links(), new LegalNoticesExtension($links)->links());
        self::assertSame('/legal /terms/terms /terms/code_of_conduct · Table:Row', $this->render(new LegalNoticesExtension($links), "{% for link in phil_legal_notices_links() %}{{ link.url }}{{ loop.last ? '' : ' ' }}{% endfor %} · {{ phil_legal_notices_kit('Table:Row') }}"));
    }

    /**
     * The name of a recipe follows the subdirectory the Toolkit installs the kit in, slashes and all.
     */
    public function testTheNamesOfTheRecipes(): void
    {
        $links = new FooterLinks(Kit::notices(), $this->urls(), new MockClock());

        self::assertSame('ui:Badge', $this->render(new LegalNoticesExtension($links, new KitComponents('ui')), "{{ phil_legal_notices_kit('Badge') }}"));
        self::assertSame('Table:Cell', new KitComponents()->name('Table:Cell'));
        self::assertSame('Table:Cell', new KitComponents('/')->name('Table:Cell'));
        self::assertSame('ui:kit:Table:Cell', new KitComponents('/ui/kit/')->name('Table:Cell'));
    }

    /**
     * A template rendered with the functions of the extension, as Twig declares them from its attributes.
     */
    private function render(LegalNoticesExtension $extension, string $template): string
    {
        $twig = new Environment(new ArrayLoader(['page' => $template]));
        $twig->addExtension(new AttributeExtension(LegalNoticesExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([LegalNoticesExtension::class => static fn (): LegalNoticesExtension => $extension]));

        return $twig->render('page');
    }

    public function testTheCheck(): void
    {
        $failing = new CommandTester(new CheckCommand(Kit::notices()));
        self::assertSame(Command::FAILURE, $failing->execute([]));
        self::assertStringContainsString('missing_field: The legal_notice requires the field "publisher_address" of the identity.', $failing->getDisplay());
        self::assertStringContainsString('missing_field: The privacy_policy requires the field "dpo_contact" of the identity.', $failing->getDisplay());
        self::assertStringContainsString('9 problem(s) found.', $failing->getDisplay());

        $passing = new CommandTester(new CheckCommand(new LegalNotices(new ConfigDocumentSource([]), new PlainTextBody(), new SiteIdentity())));
        self::assertSame(Command::SUCCESS, $passing->execute([]));
        self::assertStringContainsString('The public legal documents lack nothing.', $passing->getDisplay());
        self::assertSame('phil:legal-notices:check', new CheckCommand(Kit::notices())->getName());
    }

    public function testTheAnnouncements(): void
    {
        $events = new EventDispatcher();
        $announced = new Announcements();
        $events->addListener(DocumentPublished::class, $announced->record(...));
        $command = new AnnounceCommand(Kit::notices(), $events, new MockClock(new DateTimeImmutable('2026-12-02 00:00')));
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(['legal_notice 2', 'privacy_policy 2'], $announced->take());
        self::assertSame("legal_notice v2\nprivacy_policy v2\n", $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '31']));
        self::assertSame(['legal_notice 1', 'legal_notice 2', 'privacy_policy 2'], $announced->take());

        self::assertSame(Command::SUCCESS, $tester->execute(['--days' => '366']));
        self::assertSame(['legal_notice 1', 'legal_notice 2', 'privacy_policy 2'], $announced->take());

        foreach (['0', '367', '999', 'x', '1.5', '01', ' 1', "1\n"] as $days) {
            self::assertSame(Command::INVALID, $tester->execute(['--days' => $days]), $days);
            self::assertStringContainsString('--days goes from 1 to 366.', $tester->getDisplay());
        }

        self::assertSame('phil:legal-notices:announce', $command->getName());
    }

    public function testTheLinksOfTheOtherPackagesComeLast(): void
    {
        $link = ['url' => '/accessibility', 'label' => 'link', 'domain' => 'other'];
        $provider = new readonly class($link) implements FooterLinkProvider {
            /**
             * @param array{url: string, label: string, domain: string} $link
             */
            public function __construct(
                private array $link,
            ) {
            }

            public function footerLinks(): array
            {
                return [$this->link, $this->link];
            }
        };

        $links = new FooterLinks(Kit::notices(), $this->urls(), new MockClock(new DateTimeImmutable('2026-11-15')), ['terms'], [$provider])->links();

        self::assertCount(4, $links);
        self::assertSame([$link, $link], \array_slice($links, 2));
        self::assertSame('policy.terms', $links[1]['label']);
    }

    private function urls(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('phil_legal_notices_legal_notice', new Route('/legal'));
        $routes->add('phil_legal_notices_privacy_policy', new Route('/privacy'));
        $routes->add('phil_terms_policy', new Route('/terms/{policy}'));

        return new UrlGenerator($routes, new RequestContext());
    }
}
