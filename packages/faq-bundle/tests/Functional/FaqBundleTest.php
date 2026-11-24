<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Infrastructure\DependencyInjection\FaqServices;
use Phil\FaqBundle\Infrastructure\Http\FaqController;
use Phil\FaqBundle\Infrastructure\Twig\Components\FaqComponent;
use Phil\FaqBundle\PhilFaqBundle;
use Phil\FaqBundle\Tests\Functional\App\TestKernel;
use Phil\StructuredData\JsonLdReader;
use Phil\StructuredData\Validation\Issue;
use Phil\StructuredData\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The whole bundle in an application, the recipes of the PHIL UX kit installed as the Toolkit would: the page as a
 * browser without JavaScript sees it, its search, its votes and its JSON-LD.
 */
#[CoversClass(PhilFaqBundle::class)]
#[CoversClass(FaqServices::class)]
#[CoversClass(FaqController::class)]
#[CoversClass(FaqComponent::class)]
final class FaqBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $kernel = self::$kernel;
        $this->assertInstanceOf(TestKernel::class, $kernel);
        $kernel->resetDatabase();
        $editor = self::getContainer()->get(FaqEditor::class);
        $this->assertInstanceOf(FaqEditor::class, $editor);
        $en = Locale::fromString('en');
        $fr = Locale::fromString('fr');

        $orders = $editor->addSection($en, 'Orders', 'orders');
        $editor->retitleSection($orders->id, $fr, 'Commandes');
        $pay = $editor->addQuestion($orders->id, $en, 'How do I pay?', 'By **card** or transfer.');
        $editor->rewriteQuestion($pay->id, $fr, 'Comment payer ?', 'Par **carte**.', published: true);
        $editor->addQuestion($orders->id, $en, 'Where is my order?', 'At the post office.');

        $returns = $editor->addSection($en, 'Returns', 'returns');
        $editor->addQuestion($returns->id, $en, 'Can I return a game?', 'Within 30 days.');
        $editor->addQuestion($returns->id, $en, 'A draft?', 'Hidden.', published: false);
        $this->entityManager()->clear();
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testThePageWorksWithoutJavaScript(): void
    {
        $page = $this->client->request('GET', '/faq/');
        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        self::assertSame([], $response->headers->getCookies());
        self::assertSame('Frequently asked questions', $page->filter('h1')->text());

        $faq = $page->filter('.phil-faq');
        self::assertSame(['phil--faq-bundle--faq', 'en', '/faq/unanswered'], [$faq->attr('data-controller'), $faq->attr('lang'), $faq->attr('data-phil--faq-bundle--faq-unanswered-url-value')]);
        self::assertSame('2 questions match “%query%”.', str_replace('%count%', '2', (string) $faq->attr('data-phil--faq-bundle--faq-found-many-value')));

        $search = $page->filter('form[role=search]');
        self::assertSame(['get', '/faq/'], [$search->attr('method'), $search->attr('action')]);
        self::assertSame('Search the questions', $search->filter('label[for=phil-faq-search-en]')->text());
        self::assertSame(['search', 'q', '100', 'input'], [$search->filter('#phil-faq-search-en')->attr('type'), $search->filter('#phil-faq-search-en')->attr('name'), $search->filter('#phil-faq-search-en')->attr('maxlength'), $search->filter('#phil-faq-search-en')->attr('data-phil--faq-bundle--faq-target')]);
        self::assertSame('Search', trim($search->filter('button[type=submit]')->text()));

        self::assertSame(['faq-orders', 'faq-returns'], $page->filter('section.phil-faq__section')->each(static fn (Crawler $one): string => (string) $one->attr('id')));
        self::assertSame(['Orders', 'Returns'], $page->filter('section.phil-faq__section h2')->each(static fn (Crawler $one): string => $one->text()));
        self::assertSame(['how-do-i-pay', 'where-is-my-order', 'can-i-return-a-game'], $page->filter('details')->each(static fn (Crawler $one): string => (string) $one->attr('id')));
        self::assertNull($page->filter('details')->first()->attr('open'));
        self::assertNotNull($page->filter('.phil-faq__none')->attr('hidden'));

        $pay = $page->filter('details#how-do-i-pay');
        self::assertSame('How do I pay?', $pay->filter('summary')->text());
        self::assertStringContainsString('<strong>card</strong>', $pay->filter('.phil-faq__answer')->html());
        self::assertSame('#how-do-i-pay', $pay->filter('a.phil-faq__link')->attr('href'));
        self::assertSame('http://localhost/faq/#how-do-i-pay', $pay->filter('.isCopyButton')->attr('data-copy-button-text-value'));

        $vote = $pay->filter('form.phil-faq__vote');
        self::assertSame(['post', '/faq/vote/how-do-i-pay', 'how-do-i-pay'], [$vote->attr('method'), $vote->attr('action'), $vote->attr('data-anchor')]);
        self::assertSame('Was this answer useful?', $vote->filter('legend')->text());
        self::assertSame(['yes', 'no'], $vote->filter('button[name=helpful]')->each(static fn (Crawler $one): string => (string) $one->attr('value')));
        self::assertSame('/faq/', $vote->filter('input[name=back]')->attr('value'));
        self::assertSame($vote->filter('input[name=vote_token]')->attr('value'), $page->filter('details#can-i-return-a-game input[name=vote_token]')->attr('value'));
    }

    public function testTheJsonLdIsAValidFaqPageWithoutToken(): void
    {
        $page = $this->client->request('GET', '/faq/');
        $html = (string) $this->client->getResponse()->getContent();
        $extraction = JsonLdReader::fromHtml($html);
        $report = new Validator(allowLocalhost: true)->validate($extraction->graph);
        $json = $page->filter('script[type="application/ld+json"]')->text();

        self::assertSame(1, $extraction->blocks);
        self::assertSame([], array_values(array_filter($report->issues, static fn (Issue $issue): bool => 'isPartOf' !== $issue->property && 'name' !== $issue->property)));
        self::assertStringContainsString('"@type":"FAQPage"', $json);
        self::assertStringContainsString('"inLanguage":"en"', $json);
        self::assertStringContainsString('How do I pay?', $json);
        self::assertStringNotContainsString((string) $page->filter('input[name=vote_token]')->first()->attr('value'), $json);
        self::assertStringNotContainsString('A draft?', $json);
    }

    public function testThePageIsInTheLanguageOfTheRequest(): void
    {
        $crawler = $this->client->request('GET', '/faq/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-CA,fr;q=0.9']);

        self::assertSame(['Commandes'], $crawler->filter('section.phil-faq__section h2')->each(static fn (Crawler $one): string => $one->text()));
        self::assertSame('Comment payer ?', $crawler->filter('details#how-do-i-pay summary')->text());
        self::assertSame('Questions fréquentes', $crawler->filter('h1')->text());
    }

    public function testASearchFiltersOnTheServerAndOpensWhatItFinds(): void
    {
        $page = $this->client->request('GET', '/faq/?q=CARD');

        self::assertSame(['how-do-i-pay'], $page->filter('details')->each(static fn (Crawler $one): string => (string) $one->attr('id')));
        self::assertNotNull($page->filter('details')->attr('open'));
        self::assertSame('One question matches “CARD”.', $page->filter('[role=status]')->first()->text());
        self::assertSame('CARD', $page->filter('#phil-faq-search-en')->attr('value'));
        self::assertSame('/faq/', $page->filter('.phil-faq__clear a')->attr('href'));
        self::assertSame(0, $this->rows('phil_faq_unanswered_search'));
    }

    public function testASearchWithoutAnswerLinksToTheContactFormAndIsCountedWithoutWhoSearched(): void
    {
        $page = $this->client->request('GET', '/faq/?q=Refund+policy%21', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'Tester/1.0']);

        self::assertCount(0, $page->filter('details'));
        self::assertNull($page->filter('.phil-faq__none')->attr('hidden'));
        self::assertSame('/contact', $page->filter('.phil-faq__none a')->attr('href'));
        self::assertSame('Ask us your question', $page->filter('.phil-faq__none a')->text());
        self::assertSame([], $this->client->getResponse()->headers->getCookies());

        $this->client->request('GET', '/faq/?q=refund+POLICY');
        $this->client->request('GET', '/faq/?q=ab');

        $rows = $this->connection()->fetchAllAssociative('SELECT * FROM phil_faq_unanswered_search');
        self::assertCount(1, $rows);
        self::assertSame(['id', 'locale', 'terms', 'searches', 'last_searched_on'], array_keys($rows[0]));
        self::assertSame(['en', 'refund policy', 2], [$rows[0]['locale'], $rows[0]['terms'], self::integer($rows[0]['searches'])]);
        self::assertStringNotContainsString('203.0.113.7', json_encode($rows, \JSON_THROW_ON_ERROR));
    }

    public function testAPlainFormVotesOnceAndComesBack(): void
    {
        $page = $this->client->request('GET', '/faq/?q=card');
        $form = $page->filter('details#how-do-i-pay button[value=yes]')->form();

        $this->client->submit($form);
        self::assertResponseRedirects('/faq/?q=card&voted=how-do-i-pay#how-do-i-pay', 303);
        self::assertSame([], $this->client->getResponse()->headers->getCookies());

        $after = $this->client->followRedirect();
        self::assertSame('Thank you for your answer.', $after->filter('details#how-do-i-pay .phil-faq__vote [role=status]')->text());
        self::assertNotNull($after->filter('details#how-do-i-pay')->attr('open'));
        self::assertCount(1, $after->filter('details'));
        self::assertSame('/faq/?q=card', $after->filter('details#how-do-i-pay input[name=back]')->attr('value'));
        self::assertSame([1, 0], $this->votes('how-do-i-pay'));

        $this->client->submit($form);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('This page already voted for this question.', $this->client->getCrawler()->filter('[role=alert]')->text());
        self::assertSame('/faq/?q=card', $this->client->getCrawler()->filter('main a')->attr('href'));
        self::assertSame([1, 0], $this->votes('how-do-i-pay'));
    }

    public function testTheControllerVotesInJson(): void
    {
        $token = $this->voteToken();

        $this->vote('where-is-my-order', ['helpful' => 'no', 'vote_token' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame('{"yes":0,"no":1}', $this->client->getResponse()->getContent());

        $this->vote('where-is-my-order', ['helpful' => 'yes', 'vote_token' => $token]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('{"error":"already"}', $this->client->getResponse()->getContent());

        $this->vote('how-do-i-pay', ['helpful' => 'yes', 'vote_token' => $token]);
        self::assertSame('{"yes":1,"no":0}', $this->client->getResponse()->getContent());
    }

    public function testAVoteIsRefusedWithoutItsChecks(): void
    {
        $token = $this->voteToken();

        $this->vote('how-do-i-pay', ['helpful' => 'yes', 'vote_token' => $token], origin: null);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('{"error":"refused"}', $this->client->getResponse()->getContent());

        $this->vote('how-do-i-pay', ['helpful' => 'yes', 'vote_token' => $token], origin: 'https://evil.example');
        self::assertResponseStatusCodeSame(403);

        $this->vote('how-do-i-pay', ['helpful' => 'maybe', 'vote_token' => $token]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('{"error":"invalid"}', $this->client->getResponse()->getContent());

        $this->vote('no-such-question', ['helpful' => 'yes', 'vote_token' => $token]);
        self::assertResponseStatusCodeSame(404);

        $this->vote('how-do-i-pay', ['helpful' => 'yes', 'vote_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        self::assertSame([0, 0], $this->votes('how-do-i-pay'));
    }

    public function testAPlainFormNeverComesBackToAnotherSite(): void
    {
        $this->client->request('POST', '/faq/vote/how-do-i-pay', ['_token' => 'csrf-token', 'helpful' => 'yes', 'vote_token' => $this->voteToken(), 'back' => '//evil.example/'], server: ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects('/faq/?voted=how-do-i-pay#how-do-i-pay', 303);

        $this->client->request('POST', '/faq/vote/how-do-i-pay', ['_token' => 'csrf-token', 'helpful' => 'maybe', 'back' => 'https://evil.example/'], server: ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('A vote is yes or no.', $this->client->getCrawler()->filter('[role=alert]')->text());
        self::assertSame('/faq/', $this->client->getCrawler()->filter('main a')->attr('href'));
    }

    public function testTheControllerCountsASearchWithoutAnswer(): void
    {
        $this->client->request('POST', '/faq/unanswered', ['_token' => 'csrf-token', 'q' => 'Gift cards?'], server: ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(204);

        $this->client->restart();
        $this->client->request('POST', '/faq/unanswered', ['_token' => 'csrf-token', 'q' => 'Gift cards?'], server: ['HTTP_ORIGIN' => 'https://evil.example']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('{"error":"refused"}', $this->client->getResponse()->getContent());

        self::assertSame([['gift cards', 1]], array_map(static fn (array $row): array => [$row['terms'], self::integer($row['searches'])], $this->connection()->fetchAllAssociative('SELECT terms, searches FROM phil_faq_unanswered_search')));
    }

    public function testTheCsrfProtectionCannotBeTurnedOff(): void
    {
        self::assertTrue(self::getContainer()->has('security.csrf.token_manager'));
    }

    /**
     * @param array<string, string> $form
     */
    private function vote(string $anchor, array $form, ?string $origin = 'http://localhost'): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];

        if ('http://localhost' !== $origin) {
            // Without history, the browser sends no Referer of this site.
            $this->client->restart();
        }

        if (null !== $origin) {
            $server['HTTP_ORIGIN'] = $origin;
        }

        $this->client->request('POST', '/faq/vote/'.$anchor, $form + ['_token' => 'csrf-token'], server: $server);
    }

    private function voteToken(): string
    {
        return (string) $this->client->request('GET', '/faq/')->filter('input[name=vote_token]')->first()->attr('value');
    }

    /**
     * @return array{int, int}
     */
    private function votes(string $anchor): array
    {
        $row = $this->connection()->fetchAssociative('SELECT helpful_yes, helpful_no FROM phil_faq_question WHERE anchor = ?', [$anchor]);
        \assert(\is_array($row));

        return [self::integer($row['helpful_yes']), self::integer($row['helpful_no'])];
    }

    private function rows(string $table): int
    {
        return self::integer($this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table));
    }

    private static function integer(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : -1;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('test.entity_manager');
        $this->assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
