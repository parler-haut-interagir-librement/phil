<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\TranslationBundle\Application\Glossaries;
use Phil\TranslationBundle\Application\Translations;
use Phil\TranslationBundle\PhilTranslationBundle;
use Phil\TranslationBundle\Tests\Functional\App\TestKernel;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\Xliff\XliffReader;
use Phil\TranslationMemory\Xliff\XliffUnit;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Twig\Environment;

/**
 * A translator on the screen, through the routes, Twig and the Doctrine stores on SQLite.
 */
#[CoversClass(PhilTranslationBundle::class)]
final class TranslationBundleTest extends WebTestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        $this->client = self::createClient();
        $this->client->disableReboot();

        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    public function testATranslatorTranslatesAPage(): void
    {
        $this->service(Glossaries::class, Glossaries::class)->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('door', ['porte']));

        $this->client->request('GET', '/translate/page:1/fr');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Translate Welcome');
        self::assertSelectorCount(2, 'tbody tr');
        self::assertSelectorTextContains('ul.terms', 'door: porte');

        self::assertSelectorCount(5, 'input[name="_csrf_token"][value="csrf-token"]');
        $this->client->request('POST', '/translate/page:1/fr/segment', ['key' => 'p1/text#1', 'action' => 'save', 'target' => 'Bonjour le monde.'], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        self::assertResponseStatusCodeSame(403, 'no token');

        $this->client->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));
        $this->client->request('POST', '/translate/page:1/fr/segment', ['key' => 'p1/text#1', 'action' => 'save', 'target' => 'Bonjour le monde.', '_csrf_token' => self::TOKEN], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        self::assertResponseRedirects('/translate/page:1/fr?done=saved#segment-'.hash('xxh3', 'p1/text#1'), 303);

        $this->client->followRedirect();
        self::assertSelectorTextContains('p.done', 'Segment saved.');
        self::assertSelectorTextContains('p.progress', '50 % usable');

        $this->client->request('GET', '/translate/page:1/fr/xliff');
        self::assertResponseHeaderSame('Content-Type', 'application/xliff+xml; charset=UTF-8');
        $xml = (string) $this->client->getResponse()->getContent();
        self::assertSame(['translated', 'untranslated'], array_map(static fn (XliffUnit $unit): string => $unit->state->value, new XliffReader()->read($xml)->units));

        $path = (string) tempnam(sys_get_temp_dir(), 'xlf');
        file_put_contents($path, str_replace('</source></segment></unit></file>', '</source><target>La porte <ph id="1" dataRef="d1"/>rouge<ph id="2" dataRef="d2"/>.</target></segment></unit></file>', $xml));
        $this->client->request('POST', '/translate/page:1/fr/xliff', ['_csrf_token' => 'csrf-token'], ['xliff' => new UploadedFile($path, 'page.xlf', null, null, true)], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        unlink($path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section.report', '1 segment updated, 1 unit skipped.');

        $translations = $this->service(Translations::class, Translations::class);
        self::assertSame(SegmentState::Suggested, $translations->segment('page:1', LanguageTag::of('fr'), 'p2/text#1')->state);
        self::assertSame('La porte <b>rouge</b>.', $translations->segment('page:1', LanguageTag::of('fr'), 'p2/text#1')->target);

        $this->client->request('POST', '/translate/page:1/fr/segment', ['key' => 'p1/text#1', 'action' => 'review', '_csrf_token' => 'csrf-token'], [], ['HTTP_ORIGIN' => 'https://evil.example']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/translate/page:9/fr');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnEditorKeepsTheGlossaryAndATranslatorComments(): void
    {
        $this->client->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));

        $this->client->request('GET', '/translate/glossary/en/fr');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Glossary');

        $this->client->request('POST', '/translate/glossary/en/fr', ['action' => 'save', 'term' => 'door', 'translations' => 'porte', '_csrf_token' => self::TOKEN], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        self::assertResponseRedirects('/translate/glossary/en/fr?done=saved', 303);
        self::assertSame(['porte'], $this->service(Glossaries::class, Glossaries::class)->glossary(LanguageTag::of('en'), LanguageTag::of('fr'))->terms[0]->translations);

        $this->client->request('GET', '/translate/glossary/en/fr/tbx');
        self::assertResponseHeaderSame('Content-Type', 'application/x-tbx+xml; charset=UTF-8');

        $this->client->request('POST', '/translate/page:1/fr/comment', ['key' => 'p1/text#1', 'action' => 'add', 'body' => 'Formal or not?', '_csrf_token' => self::TOKEN], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        self::assertResponseRedirects('/translate/page:1/fr?done=commented#segment-'.hash('xxh3', 'p1/text#1'), 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('article.comment', 'Formal or not?');

        $this->client->request('POST', '/translate/page:1/fr/machine', ['_csrf_token' => self::TOKEN], [], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        self::assertResponseStatusCodeSame(404, 'machine translation is off by default');

        $html = $this->service('twig', Environment::class)->render('@PhilTranslation/credits.html.twig', ['document' => 'page:1', 'language' => 'fr']);
        self::assertSame('', trim($html), 'no translator service, no credit');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
