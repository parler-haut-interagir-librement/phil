<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\CommentsBundle\Infrastructure\Http\PageController;
use Phil\CommentsBundle\Infrastructure\Http\PageForm;
use Phil\CommentsBundle\Infrastructure\Twig\Components\PageCommentsComponent;
use Phil\CommentsBundle\Tests\Functional\App\TestKernel;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The comments on a whole page in a browser without JavaScript: the section drawn by the server with the recipes of
 * the kit, and its plain forms posting to the signed address and coming back to the article, on the Doctrine stores
 * on SQLite.
 */
#[CoversClass(PageCommentsComponent::class)]
#[CoversClass(PageController::class)]
#[CoversClass(PageForm::class)]
final class PageCommentsTest extends WebTestCase
{
    private const string ORIGIN = 'http://localhost';

    private KernelBrowser $client;

    private static bool $ariaRoles = false;

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, self::$ariaRoles);
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        $this->client = self::createClient();
        $this->client->disableReboot();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        self::$ariaRoles = false;
        parent::tearDown();
    }

    public function testAConversationUnderAnArticle(): void
    {
        $page = $this->article('ada');
        self::assertSame('On the page', $page->filter('#phil-comments-page h2')->text());
        self::assertSame('No comment on the page yet.', $page->filter('.phil-comments-page__empty')->text());
        self::assertSame('Comment on the page', $page->filter('label[for="phil-comments-page-open"]')->text());
        self::assertCount(1, $page->filter('form.phil-comments-page__open textarea.isTextarea, form.phil-comments-page__open textarea[data-slot="textarea"]'));
        self::assertCount(0, $page->filter('[data-controller]'), 'nothing needs JavaScript');

        // A thread on a passage belongs to the comments controller, not to this section.
        $this->comments()->open(ResourceReference::of('page', '1'), Anchor::of('b1', 'text', 0, 'cat'), ResourceReference::of('user', 'bob'), 'On a passage');

        $this->post('ada', $page->filter('form.phil-comments-page__open'), ['body' => 'A fine article.']);
        self::assertResponseRedirects('/article#phil-comments-page', 303);

        $page = $this->article('bob');
        self::assertSame(['A fine article.'], $page->filter('.phil-comments-page__body')->each(static fn (Crawler $body): string => $body->text()));
        self::assertSame('user:ada', $page->filter('.phil-comments-page__meta strong')->text());
        self::assertSame('2026-11-06', $page->filter('.phil-comments-page__meta time')->text());
        self::assertCount(1, $page->filter('.phil-comments-page__thread[data-slot="card"]'));
        self::assertCount(0, $page->filter('.phil-comments-page__edit'), 'bob may not edit what ada wrote');
        self::assertCount(0, $page->filter('form.phil-comments-page__status'), 'nor resolve her thread');
        self::assertCount(0, $page->filter('[role="comment"]'));

        $this->post('bob', $page->filter('form.phil-comments-page__reply'), ['body' => 'Thanks!']);
        self::assertResponseRedirects('/article#phil-comments-page', 303);

        $page = $this->article('ada');
        self::assertSame(['A fine article.', 'Thanks!'], $page->filter('.phil-comments-page__body')->each(static fn (Crawler $body): string => $body->text()));
        self::assertCount(1, $page->filter('.phil-comments-page__edit'));

        $this->post('ada', $page->filter('.phil-comments-page__edit form'), ['body' => 'A fine article, really.']);
        $this->post('ada', $page->filter('form.phil-comments-page__status'), []);

        $page = $this->article('ed');
        self::assertSame(['A fine article, really.', 'Thanks!'], $page->filter('.phil-comments-page__body')->each(static fn (Crawler $body): string => $body->text()));
        self::assertSame('edited', trim(explode('·', $page->filter('.phil-comments-page__meta')->first()->text())[1] ?? ''));
        self::assertSame('Resolved by user:ada', $page->filter('.phil-comments-page__thread [data-slot="badge"]')->text());
        self::assertCount(0, $page->filter('form.phil-comments-page__reply'), 'a resolved thread takes no reply');
        self::assertSame('Reopen', $page->filter('form.phil-comments-page__status button')->text());
        self::assertCount(2, $page->filter('form.phil-comments-page__delete'), 'the editor moderates');

        $this->post('ed', $page->filter('form.phil-comments-page__delete')->eq(1), []);
        $this->post('ed', $page->filter('form.phil-comments-page__status'), []);

        $page = $this->article('ed');
        self::assertSame(['A fine article, really.'], $page->filter('.phil-comments-page__body')->each(static fn (Crawler $body): string => $body->text()));
        self::assertSame('Resolve', $page->filter('form.phil-comments-page__status button')->text());

        $this->post('ed', $page->filter('form.phil-comments-page__delete'), []);
        self::assertSame('No comment on the page yet.', $this->article('ed')->filter('.phil-comments-page__empty')->text());
        self::assertCount(1, $this->comments()->discussions(ResourceReference::of('page', '1'), ResourceReference::of('user', 'ed')), 'the thread on the passage stays');
    }

    public function testNobodySignedInIsAskedToSignIn(): void
    {
        $page = $this->article(null);

        self::assertSame('Sign in to read and write comments.', $page->filter('#phil-comments-page [data-slot="alert"]')->text());
        self::assertCount(0, $page->filter('form'));
    }

    public function testWhatStopsAFormIsSaidAndTheTextKept(): void
    {
        $form = $this->article('ada')->filter('form.phil-comments-page__open');
        $action = (string) $form->attr('action');
        $token = (string) $form->filter('input[name="_token"]')->attr('value');

        // From another site, as the browser says it.
        $this->client->request('POST', $action, ['_token' => $token, 'do' => 'open', 'body' => 'Kept text'], server: ['HTTP_X_TEST_AUTHOR' => 'ada', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('This form was not sent from this site. Go back to the page and send it again.', $this->client->getCrawler()->filter('[role="alert"]')->text());
        self::assertSame('Kept text', $this->client->getCrawler()->filter('textarea')->text());
        self::assertSame('/article', $this->client->getCrawler()->filter('a')->attr('href'));

        // A page changed in the address: the signature no longer matches.
        $this->client->request('POST', str_replace('page%3A1', 'page%3A2', $action), ['_token' => $token, 'do' => 'open', 'body' => 'x'], server: $this->server('ada'));
        self::assertResponseStatusCodeSame(403);
        self::assertSame('/', $this->client->getCrawler()->filter('a')->attr('href'));

        foreach ([
            [null, ['do' => 'open', 'body' => 'x'], 401, 'Sign in to comment.'],
            ['ada', ['do' => 'open', 'body' => ''], 400, 'The comment cannot be kept: it is empty or too long.'],
            ['ada', ['do' => 'shout', 'body' => 'x'], 400, 'The comment cannot be kept: it is empty or too long.'],
            ['ada', ['do' => 'reply', 'id' => 'nope', 'body' => 'x'], 404, 'This thread or comment no longer exists.'],
        ] as [$author, $fields, $status, $message]) {
            $this->client->request('POST', $action, ['_token' => $token, ...$fields], server: $this->server($author));
            self::assertResponseStatusCodeSame($status, (string) json_encode($fields));
            self::assertSame($message, $this->client->getCrawler()->filter('[role="alert"]')->text());
        }

        $thread = $this->comments()->openOnPage(ResourceReference::of('page', '1'), ResourceReference::of('user', 'ada'), 'Mine');
        $this->client->request('POST', $action, ['_token' => $token, 'do' => 'edit', 'id' => $thread->comments[0]->id, 'body' => 'Not mine'], server: $this->server('bob'));
        self::assertResponseStatusCodeSame(403);
        self::assertSame('You may not do this.', $this->client->getCrawler()->filter('[role="alert"]')->text());
        self::assertSame('Comment not saved', $this->client->getCrawler()->filter('h1')->text());
    }

    public function testTheRolesOfAria13AreWrittenWhenTurnedOn(): void
    {
        self::ensureKernelShutdown();
        self::$ariaRoles = true;
        $this->setUp();
        $this->comments()->openOnPage(ResourceReference::of('page', '1'), ResourceReference::of('user', 'ada'), 'Hello');

        $page = $this->article('ada');

        self::assertSame('Hello', $page->filter('[role="comment"] .phil-comments-page__body')->text());
    }

    private function article(?string $author): Crawler
    {
        $crawler = $this->client->request('GET', '/article', server: null === $author ? [] : ['HTTP_X_TEST_AUTHOR' => $author]);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * Sends a form of the section as the browser of this site would, its fields with the values given.
     *
     * @param array<string, string> $values
     */
    private function post(string $author, Crawler $form, array $values): void
    {
        $fields = [];

        foreach ($form->filter('input[type="hidden"]')->each(static fn (Crawler $input): array => [(string) $input->attr('name'), (string) $input->attr('value')]) as [$name, $value]) {
            $fields[$name] = $value;
        }

        $this->client->request('POST', (string) $form->attr('action'), [...$fields, ...$values], server: $this->server($author));
        self::assertResponseRedirects('/article#phil-comments-page', 303);
    }

    /**
     * @return array<string, string>
     */
    private function server(?string $author): array
    {
        return ['HTTP_ORIGIN' => self::ORIGIN, ...(null === $author ? [] : ['HTTP_X_TEST_AUTHOR' => $author])];
    }

    private function comments(): Comments
    {
        $comments = self::getContainer()->get(Comments::class);
        self::assertInstanceOf(Comments::class, $comments);

        return $comments;
    }
}
