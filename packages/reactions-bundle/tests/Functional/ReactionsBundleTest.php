<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Functional;

use Override;
use Phil\ReactionsBundle\Infrastructure\Twig\Components\ReactionsComponent;
use Phil\ReactionsBundle\Infrastructure\Twig\KitComponents;
use Phil\ReactionsBundle\PhilReactionsBundle;
use Phil\ReactionsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * The whole bundle with each kit, its recipes installed as the Toolkit would: a page, its plain forms and the component.
 */
#[CoversClass(PhilReactionsBundle::class)]
#[CoversClass(ReactionsComponent::class)]
final class ReactionsBundleTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function kits(): iterable
    {
        yield 'PHIL UX in a subdirectory' => ['ux-kit', 'ui'];
        yield 'PHIL UX' => ['ux-kit', ''];
        yield 'Shadcn in a subdirectory' => ['ux-kit-shadcn', 'ui'];
    }

    #[DataProvider('kits')]
    public function testAMemberSeesAPlainFormThatWorks(string $kit, string $subdirectory): void
    {
        $client = $this->client($kit, $subdirectory, 'ada');
        $page = $this->page($client);

        self::assertCount(3, $page->filter('.phil-reactions'));
        $first = $page->filter('.phil-reactions')->first();
        self::assertSame('post', $first->filter('form')->attr('method'));
        self::assertSame('Réactions', $first->filter('[role=group]')->first()->attr('aria-label'));
        self::assertSame('polite', $first->filter('[role=status]')->last()->attr('aria-live'));

        $like = $first->filter('button[value=like]');
        self::assertSame(['react', 'false'], [$like->attr('name'), $like->attr('aria-pressed')]);
        self::assertStringContainsString('J’aime', $like->text());
        self::assertSame('live#action:prevent', $like->attr('data-action'));
        self::assertSame('react', $like->attr('data-live-action-param'));
        self::assertSame('like', $like->attr('data-live-code-param'));

        self::assertCount(0, $first->filter('button[value=refused]'));
        self::assertStringContainsString('Autres réactions', $first->filter('details summary')->text());
        $search = $first->filter('details input[type=search]');
        self::assertNotNull($search->attr('hidden'));
        self::assertSame('Chercher une réaction par son nom', $search->attr('aria-label'));
        self::assertSame('✨', trim($first->filter('button[value=spark] [aria-hidden=true]')->text()));
        self::assertSame('Bravo', $first->filter('button[value=spark]')->attr('data-search'));
        self::assertSame('J’adore', $first->filter('button[value=gnome] img')->attr('alt'));

        $client->submit($first->filter('button[value=like]')->form());
        self::assertResponseRedirects('/article', 303);

        $after = $client->followRedirect()->filter('.phil-reactions');
        foreach ([0, 1] as $sameContent) {
            $button = $after->eq($sameContent)->filter('button[value=like]');
            self::assertSame(['withdraw', 'true', 'withdraw'], [$button->attr('name'), $button->attr('aria-pressed'), $button->attr('data-live-action-param')]);
            self::assertStringContainsString('1 réaction', $button->text());
        }

        self::assertSame('false', $after->eq(2)->filter('button[value=like]')->attr('aria-pressed'));
        self::assertSame('Ada', trim(explode(':', $after->eq(0)->filter('.phil-reactions__names li')->text())[1]));

        $client->submit($after->eq(0)->filter('button[value=like]')->form());
        self::assertResponseRedirects('/article', 303);
        self::assertSame('false', $client->followRedirect()->filter('button[value=like]')->first()->attr('aria-pressed'));
    }

    #[DataProvider('kits')]
    public function testAVisitorSeesOnlyWhatIsCounted(string $kit, string $subdirectory): void
    {
        $client = $this->client($kit, $subdirectory, 'ada');
        $client->submit($this->page($client)->filter('button[value=spark]')->first()->form());

        $visitor = $this->visitorPage($client)->filter('.phil-reactions')->first();

        self::assertCount(0, $visitor->filter('button'));
        self::assertCount(0, $visitor->filter('details'));
        self::assertStringContainsString('1 réaction', $visitor->text());
        self::assertStringContainsString('Bravo', $visitor->text());
    }

    public function testAPrivateReactionIsNeverCounted(): void
    {
        $client = $this->client('ux-kit', 'ui', 'ada');
        $client->submit($this->page($client)->filter('button[value=not_useful]')->first()->form());
        self::assertResponseRedirects();

        $first = $client->followRedirect()->filter('.phil-reactions')->first();
        self::assertSame('true', $first->filter('button[value=not_useful]')->attr('aria-pressed'));
        self::assertCount(0, $first->filter('button[value=not_useful] .isVisuallyHidden')->reduce(static fn (Crawler $node): bool => str_contains($node->text(), 'réaction')));

        $client->setServerParameter('HTTP_X_MEMBER', 'linus');
        $linus = $this->page($client)->filter('.phil-reactions')->first();
        self::assertSame('false', $linus->filter('button[value=not_useful]')->attr('aria-pressed'));
        self::assertStringNotContainsString('Ada', $linus->text());
    }

    public function testTheForeignSignatureIsRefused(): void
    {
        $client = $this->client('ux-kit', 'ui', 'ada');
        $form = $this->page($client)->filter('button[value=like]')->first()->form();
        $action = str_replace('article%3A1', 'article%3A2', $form->getUri());

        $client->request('POST', $action, $form->getPhpValues());

        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('[role=alert]', 'Cette réaction ne vous est pas permise.');
    }

    public function testTheLiveComponent(): void
    {
        $client = $this->client('ux-kit', 'ui', 'ada');
        $component = $this->live($client);

        self::assertSame('article:1', $this->component($component)->content);

        $component->call('react', ['code' => 'spark', 'token' => 'csrf-token']);
        $rendered = $component->render()->crawler()->filter('.phil-reactions');

        self::assertSame('Votre réaction est enregistrée.', $rendered->filter('[role=status]')->last()->text());
        self::assertSame('true', $rendered->filter('button[value=spark]')->attr('aria-pressed'));

        $component->call('withdraw', ['token' => 'csrf-token']);
        self::assertSame('Votre réaction est retirée.', $component->render()->crawler()->filter('[role=status]')->last()->text());
    }

    /**
     * The names of those who reacted are a list written in the language of the page, how many others ending it.
     */
    public function testTheNamesAreAListInTheLanguageOfThePage(): void
    {
        $client = $this->client('ux-kit', 'ui', 'ada');
        foreach (['ada', 'linus'] as $member) {
            $client->setServerParameter('HTTP_X_MEMBER', $member);
            $client->submit($this->page($client)->filter('button[value=like]')->first()->form());
            self::assertResponseRedirects('/article', 303);
        }

        self::assertSame('J’aime : Ada et Linus', $this->page($client)->filter('.phil-reactions__names li')->first()->text());
    }

    public function testTheSubdirectoryIsTakenFromTheToolkit(): void
    {
        $client = $this->client('ux-kit', 'ui', 'ada');
        $kit = $client->getContainer()->get(KitComponents::class);
        $this->assertInstanceOf(KitComponents::class, $kit);

        self::assertSame('ui:ReactionButton', $kit->name('ReactionButton'));
    }

    public function testSeveralReactionsUpToTheCapWithoutJavaScript(): void
    {
        $client = $this->client('ux-kit', 'ui', 'ada', 2);
        $first = $this->page($client)->filter('.phil-reactions')->first();
        self::assertCount(0, $first->filter('.phil-reactions__full'));

        $client->submit($first->filter('button[value=like]')->form());
        $first = $client->followRedirect()->filter('.phil-reactions')->first();
        $client->submit($first->filter('button[value=gnome]')->form());
        $first = $client->followRedirect()->filter('.phil-reactions')->first();

        self::assertSame(['withdraw', 'withdraw'], [$first->filter('button[value=like]')->attr('name'), $first->filter('button[value=gnome]')->attr('name')]);
        self::assertStringContainsString('toutes les réactions permises', $first->filter('.phil-reactions__full')->text());
        self::assertCount(0, $first->filter('button[value=spark]'));

        $client->submit($first->filter('button[value=gnome]')->form());
        $first = $client->followRedirect()->filter('.phil-reactions')->first();
        self::assertSame('react', $first->filter('button[value=gnome]')->attr('name'));
        self::assertSame('withdraw', $first->filter('button[value=like]')->attr('name'));
        self::assertCount(0, $first->filter('.phil-reactions__full'));
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        \assert(\is_string($options['kit'] ?? null) && \is_string($options['subdirectory'] ?? null));

        $perMember = $options['per_member'] ?? 1;
        \assert(\is_int($perMember));

        return new TestKernel('test', true, $options['kit'], $options['subdirectory'], $perMember);
    }

    private function client(string $kit, string $subdirectory, string $member, int $perMember = 1): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = self::createClient(['kit' => $kit, 'subdirectory' => $subdirectory, 'per_member' => $perMember]);
        $client->disableReboot();

        $kernel = $client->getKernel();
        $this->assertInstanceOf(TestKernel::class, $kernel);
        $kernel->resetDatabase();
        $client->setServerParameter('HTTP_SEC_FETCH_SITE', 'same-origin');
        $client->setServerParameter('HTTP_X_MEMBER', $member);

        return $client;
    }

    private function page(KernelBrowser $client): Crawler
    {
        $page = $client->request('GET', '/article');
        self::assertResponseIsSuccessful();

        return $page;
    }

    private function visitorPage(KernelBrowser $client): Crawler
    {
        $client->setServerParameter('HTTP_X_MEMBER', '');

        return $this->page($client);
    }

    private function live(KernelBrowser $client): TestLiveComponent
    {
        return $this->createLiveComponent('PhilReactions', ['content' => 'article:1'], $client);
    }

    private function component(TestLiveComponent $component): ReactionsComponent
    {
        $instance = $component->component();
        $this->assertInstanceOf(ReactionsComponent::class, $instance);

        return $instance;
    }
}
