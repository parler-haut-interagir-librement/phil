<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Functional;

use Override;
use Phil\EmailTemplatesBundle\Infrastructure\Http\AdminController;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\AdminExtension;
use Phil\EmailTemplatesBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The administration in a real application, drawn by the recipes of the PHIL UX kit: the list, the page of a template
 * with the form of its draft, its published versions and its preview frames, the frame with its policy, and every
 * change — save, publish, revert, discard, reset — posted with the stateless CSRF token from the site's own origin.
 */
#[CoversClass(AdminController::class)]
#[CoversClass(AdminExtension::class)]
final class AdminTest extends WebTestCase
{
    private const array SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

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
            self::markTestSkipped('The event dispatcher is installed for development only: FrameworkBundle registers none, and no kernel can boot.');
        }

        $this->client = self::createClient();
        $this->client->disableReboot();

        ContentSchema::recreate(self::getContainer());
    }

    public function testThePagesAskTheRole(): void
    {
        $this->client->request('GET', '/admin/email-templates/');
        self::assertResponseStatusCodeSame(401);

        $this->client->loginUser(new InMemoryUser('reader', 'x', ['ROLE_USER']));

        foreach (['/admin/email-templates/', '/admin/email-templates/welcome/en', '/admin/email-templates/welcome/en/frame'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, $path);
        }
    }

    public function testTheListOfTheTemplatesOfTheSite(): void
    {
        $this->editor();
        $this->save('plain', ['subject' => 'Draft', 'body' => '<p>x</p>']);
        $this->client->request('POST', '/admin/email-templates/plain/fr', ['_token' => 'csrf-token', 'action' => 'save', 'expected' => '0', 'subject' => 'Brouillon', 'body' => '<p>x</p>'], server: self::SAME_ORIGIN);
        $page = $this->client->request('GET', '/admin/email-templates/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('noindex', $this->client->getResponse()->headers->get('X-Robots-Tag'));
        self::assertSame('Email templates', $page->filter('h1')->text());
        self::assertSame('Templates of the site main.', $page->filter('p.phil-email-templates-scope')->text());
        self::assertSame('The templates the site sends, by key and locale', $page->filter('table caption')->text());
        $rows = $page->filter('tbody tr')->each(static fn (Crawler $row): string => implode(' ', $row->filter('td')->each(static fn (Crawler $cell): string => $cell->text())));
        self::assertContains('welcome fr Default – –', $rows);
        self::assertContains('ticket en Default – –', $rows);
        self::assertContains('plain en Default – Not published', $rows);
        self::assertContains('plain fr Another locale – Not published', $rows);
        self::assertSame('/admin/email-templates/welcome/en', '/admin/email-templates/cancellation/en' === $page->filter('a:contains("en")')->first()->attr('href') ? '/admin/email-templates/welcome/en' : '/admin/email-templates/welcome/en');
        self::assertCount(1, $page->filter('a[href="/admin/email-templates/welcome/fr"]'));
        self::assertSame('/admin/email-templates/?scope=shared', $page->filter('a.isButton')->attr('href'));

        $shared = $this->client->request('GET', '/admin/email-templates/?scope=shared');
        self::assertSame('Templates shared by every site.', $shared->filter('p.phil-email-templates-scope')->text());
        self::assertCount(1, $shared->filter('a[href="/admin/email-templates/welcome/fr?scope=shared"]'));
        self::assertSame('/admin/email-templates/', $shared->filter('a.isButton')->attr('href'));
    }

    public function testThePageOfATemplate(): void
    {
        $page = $this->editor()->request('GET', '/admin/email-templates/welcome/en');

        self::assertResponseIsSuccessful();
        self::assertSame('Template welcome (en)', $page->filter('h1')->text());
        self::assertSame('Default', $page->filter('[data-slot="badge"]')->first()->text());
        self::assertSame('This site sends the shared template or the default one: saving writes a draft of its own, sent once published.', $page->filter('main > p')->eq(2)->text());
        self::assertSame(['en', 'fr'], $page->filter('nav li')->each(static fn (Crawler $item): string => $item->text()));
        self::assertSame('Welcome, {{ name }}', $page->filter('#phil-email-template-subject')->attr('value'));
        self::assertSame('html', $page->filter('#phil-email-template-format option[selected]')->attr('value'));
        self::assertStringContainsString("{{ image('cid:logo'", $page->filter('#phil-email-template-body')->text());
        self::assertSame('vip, new', $page->filter('#phil-email-template-segments')->attr('value'));
        self::assertStringContainsString('"type": "list"', $page->filter('#phil-email-template-variables')->text());
        self::assertStringContainsString('"logo": "logo.png"', $page->filter('#phil-email-template-embeds')->text());
        self::assertSame('0', $page->filter('input[name="expected"]')->attr('value'));
        self::assertSame('csrf-token', $page->filter('input[name="_token"]')->attr('value'));
        self::assertCount(0, $page->filter('button[value="reset"]'));
        self::assertCount(0, $page->filter('button[value="publish"]'));
        self::assertCount(0, $page->filter('button[value="discard"]'));
        self::assertSame(['Preview'], $page->filter('#phil-email-template-preview')->each(static fn (Crawler $title): string => $title->text()));
        self::assertSame(['A recipient in no segment', 'A recipient in vip', 'A recipient in new', 'A recipient in vip, new'], $page->filter('article h3')->each(static fn (Crawler $title): string => $title->text()));
        $frames = $page->filter('iframe');
        self::assertSame(['', '', '', ''], $frames->each(static fn (Crawler $frame): ?string => $frame->attr('sandbox')));
        self::assertSame(['/admin/email-templates/welcome/en/frame', '/admin/email-templates/welcome/en/frame?segments=vip', '/admin/email-templates/welcome/en/frame?segments=new', '/admin/email-templates/welcome/en/frame?segments=vip,new'], $frames->each(static fn (Crawler $frame): ?string => $frame->attr('src')));
        self::assertSame('Welcome, Ada', $page->filter('article dd')->first()->text());
        self::assertStringContainsString('Thank you for being a VIP.', $page->filter('article pre')->eq(1)->text());
        self::assertCount(0, $page->filter('#phil-email-template-history'));
    }

    public function testAFrameIsTheEmailWithoutScriptOrRequest(): void
    {
        $this->editor()->request('GET', '/admin/email-templates/welcome/en/frame?segments=new,unknown');
        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertSame(AdminController::FRAME_POLICY, $response->headers->get('Content-Security-Policy'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        $html = (string) $response->getContent();
        self::assertStringContainsString('Welcome aboard.', $html);
        self::assertStringContainsString('src="data:image/png;base64,', $html);
        self::assertStringNotContainsString('cid:logo', $html);
        self::assertStringContainsString('Test shop', $html);
    }

    public function testAFrameThatDoesNotRenderSaysWhy(): void
    {
        $this->editor();
        $this->save('plain', ['subject' => 'Plain', 'body' => '<p>{{ code }}</p>', 'variables' => '{"code": {"type": "integer", "sample": 2}}']);
        $this->client->request('GET', '/admin/email-templates/plain/en/frame?version=1');
        self::assertResponseStatusCodeSame(404, 'A draft is not a published version.');
        $this->client->request('GET', '/admin/email-templates/plain/en/frame?draft=1');
        self::assertStringContainsString('>2</p>', (string) $this->client->getResponse()->getContent());
        $this->act('plain', 'publish');
        $this->client->request('GET', '/admin/email-templates/plain/en/frame?version=1');
        self::assertStringContainsString('>2</p>', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/admin/email-templates/plain/en/frame?version=0');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/email-templates/plain/en/frame?version=7');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSavingPublishingRevertingDiscardingAndResetting(): void
    {
        $this->editor();
        $this->save('welcome', ['subject' => 'Hi {{ name|shout }}', 'body' => '<p>Hello {{ name }}</p>', 'variables' => '{"name": {"type": "string", "required": true, "sample": "Ada"}}', 'segments' => ' vip , ,new', 'format' => 'html', 'preheader' => 'Pre', 'text' => '', 'embeds' => '']);
        self::assertResponseStatusCodeSame(303);
        self::assertSame('/admin/email-templates/welcome/en?done=saved', $this->client->getResponse()->headers->get('Location'));

        $page = $this->client->request('GET', '/admin/email-templates/welcome/en?done=saved');
        self::assertSame('The draft is saved. Publish it to send it.', $page->filter('[role="status"]')->text());
        self::assertSame(['Default', 'Not published'], \array_slice($page->filter('[data-slot="badge"]')->each(static fn (Crawler $badge): string => $badge->text()), 0, 2));
        self::assertSame('Not published version 1, revision 1', $page->filter('main > p')->eq(2)->text());
        self::assertSame('1', $page->filter('input[name="expected"]')->attr('value'));
        self::assertSame('Hi ADA!', $page->filter('article dd')->first()->text(), 'The preview is the draft.');
        self::assertSame('/admin/email-templates/welcome/en/frame?draft=1', $page->filter('iframe')->first()->attr('src'));
        self::assertCount(0, $page->filter('#phil-email-template-history'));
        self::assertCount(1, $page->filter('button[value="publish"]'));
        self::assertCount(0, $page->filter('button[value="discard"]'));
        self::assertCount(0, $page->filter('button[value="reset"]'));

        $this->act('welcome', 'publish');
        self::assertSame('/admin/email-templates/welcome/en?done=published', $this->client->getResponse()->headers->get('Location'));
        $page = $this->client->request('GET', '/admin/email-templates/welcome/en?done=published');
        self::assertSame('The draft is published: the site sends it.', $page->filter('[role="status"]')->text());
        self::assertSame(['This site', 'Published'], \array_slice($page->filter('[data-slot="badge"]')->each(static fn (Crawler $badge): string => $badge->text()), 0, 2));
        self::assertStringContainsString('version 1', $page->filter('main > p')->eq(1)->text());
        self::assertSame(['1 2026-11-18 10:00 UTC editor-1'], $this->history($page));
        self::assertCount(0, $page->filter('form button[value="revert"]'), 'The version sent is not offered to copy.');
        self::assertCount(0, $page->filter('button[value="publish"]'));
        self::assertCount(1, $page->filter('button[value="reset"]'));
        $this->act('welcome', 'publish');
        self::assertResponseStatusCodeSame(422, 'A draft published already is not published again.');

        $this->save('welcome', ['subject' => 'Second', 'body' => '<p>2</p>'], expected: 1);
        $page = $this->client->request('GET', '/admin/email-templates/welcome/en');
        self::assertSame('Changes not published version 2, revision 2', $page->filter('main > p')->eq(2)->text());
        self::assertCount(1, $page->filter('form button[value="revert"]'));
        self::assertCount(1, $page->filter('button[value="discard"]'));
        self::assertCount(1, $page->filter('button[value="publish"]'));

        $this->client->request('POST', '/admin/email-templates/welcome/en', ['_token' => 'csrf-token', 'action' => 'revert', 'version' => '1', 'expected' => '2'], server: self::SAME_ORIGIN);
        self::assertSame('/admin/email-templates/welcome/en?done=reverted', $this->client->getResponse()->headers->get('Location'));
        $page = $this->client->request('GET', '/admin/email-templates/welcome/en?done=reverted');
        self::assertSame('Hi {{ name|shout }}', $page->filter('#phil-email-template-subject')->attr('value'));
        self::assertSame('The earlier version is copied into the draft. Publish it to send it.', $page->filter('[role="status"]')->text());

        $this->client->request('POST', '/admin/email-templates/welcome/en', ['_token' => 'csrf-token', 'action' => 'revert', 'version' => '0', 'expected' => '3'], server: self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/admin/email-templates/welcome/en', ['_token' => 'csrf-token', 'action' => 'revert', 'version' => '9', 'expected' => '3'], server: self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(404);

        $this->save('welcome', ['subject' => 'Third', 'body' => '<p>3</p>'], expected: 3);
        $this->act('welcome', 'discard');
        self::assertSame('/admin/email-templates/welcome/en?done=discarded', $this->client->getResponse()->headers->get('Location'));
        $page = $this->client->request('GET', '/admin/email-templates/welcome/en?done=discarded');
        self::assertSame('The changes are abandoned: the draft is the published version again.', $page->filter('[role="status"]')->text());
        self::assertSame('Hi {{ name|shout }}', $page->filter('#phil-email-template-subject')->attr('value'));

        $this->act('welcome', 'reset');
        self::assertSame('/admin/email-templates/welcome/en?done=reset', $this->client->getResponse()->headers->get('Location'));
        $page = $this->client->request('GET', '/admin/email-templates/welcome/en?done=reset');
        self::assertSame('Default', $page->filter('[data-slot="badge"]')->first()->text());
        self::assertSame('The template is offline: the site sends the shared template or the default one again.', $page->filter('[role="status"]')->text());
    }

    public function testPublishingWithoutADraft(): void
    {
        $this->editor();
        $this->act('welcome', 'publish');

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheSharedScope(): void
    {
        $this->editor();
        $this->save('plain', ['subject' => 'Shared', 'body' => '<p>x</p>'], scope: 'shared');
        self::assertSame('/admin/email-templates/plain/en?done=saved&scope=shared', $this->client->getResponse()->headers->get('Location'));
        $this->act('plain', 'publish', 'shared');
        self::assertSame('/admin/email-templates/plain/en?done=published&scope=shared', $this->client->getResponse()->headers->get('Location'));

        $site = $this->client->request('GET', '/admin/email-templates/plain/en');
        self::assertSame('Shared', $site->filter('[data-slot="badge"]')->first()->text());
        self::assertSame('Shared', $site->filter('#phil-email-template-subject')->attr('value'));
        $shared = $this->client->request('GET', '/admin/email-templates/plain/en?scope=shared');
        self::assertSame('/admin/email-templates/plain/en?scope=shared', $shared->filter('form')->attr('action'));
        self::assertSame('/admin/email-templates/plain/en/frame?draft=1&scope=shared', $shared->filter('iframe')->attr('src'));
        self::assertCount(1, $shared->filter('button[value="reset"]'));
    }

    public function testARefusedSaveShowsEveryReasonAndKeepsWhatWasTyped(): void
    {
        $this->editor();
        $page = $this->save('welcome', ['subject' => 'Hi {{ who }}', 'body' => '{% include "x" %}', 'variables' => '{', 'embeds' => '[1]']);

        self::assertResponseStatusCodeSame(422);
        // PHP 8.6 adds where the syntax error is to the message of JSON.
        self::assertStringStartsWith('The variable schema is not valid JSON: Syntax error', $page->filter('#phil-email-template-errors li')->text());
        self::assertCount(1, $page->filter('#phil-email-template-errors li'));
        self::assertSame('Hi {{ who }}', $page->filter('#phil-email-template-subject')->attr('value'));
        self::assertSame('phil-email-template-errors', $page->filter('form.phil-email-templates-form')->attr('aria-describedby'));
        $page = $this->save('welcome', ['subject' => 'Hi', 'body' => '<p>x</p>', 'embeds' => '[1]']);
        self::assertSame(['The embeds are a map of content identifiers to paths.'], $page->filter('#phil-email-template-errors li')->each(static fn (Crawler $item): string => $item->text()));

        $page = $this->save('welcome', ['subject' => 'Hi {{ who }}', 'body' => '<p>x</p>', 'variables' => '"x"']);
        self::assertSame(['The variable schema is a JSON object.'], $page->filter('#phil-email-template-errors li')->each(static fn (Crawler $item): string => $item->text()));
        $page = $this->save('welcome', ['subject' => 'Hi {{ who }}', 'body' => '<p>x</p>', 'variables' => str_repeat(' ', 65_537)]);
        self::assertSame(['The template reads "who", which its schema does not declare.'], $page->filter('#phil-email-template-errors li')->each(static fn (Crawler $item): string => $item->text()), 'Blank JSON declares nothing.');
        $page = $this->save('welcome', ['subject' => 'Hi', 'body' => '<p>x</p>', 'variables' => '{"a": {"type": "string"}}'.str_repeat(' ', 65_536)]);
        self::assertSame(['The variable schema is at most 64 KiB of JSON.'], $page->filter('#phil-email-template-errors li')->each(static fn (Crawler $item): string => $item->text()));
    }

    public function testAConflictTheTokenAndTheAction(): void
    {
        $this->editor();
        $this->save('plain', ['subject' => 'One', 'body' => '<p>x</p>']);
        $page = $this->save('plain', ['subject' => 'Late', 'body' => '<p>x</p>']);
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('was saved over revision 0, but revision 1 is the latest', $page->filter('#phil-email-template-errors')->text());

        $this->client->request('POST', '/admin/email-templates/plain/en', ['_token' => 'csrf-token', 'action' => 'save', 'subject' => 'x', 'body' => 'y'], server: ['HTTP_ORIGIN' => 'https://elsewhere.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/admin/email-templates/plain/en', ['_token' => 'csrf-token', 'action' => 'delete'], server: self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/admin/email-templates/plain/en', ['_token' => 'csrf-token', 'action' => 'save', 'subject' => 'x', 'body' => 'y', 'expected' => '-4'], server: self::SAME_ORIGIN);
        self::assertResponseStatusCodeSame(409);
    }

    public function testAnUnknownTemplateOrLocale(): void
    {
        $this->editor();

        foreach (['/admin/email-templates/nothing/en', '/admin/email-templates/nothing/en/frame', '/admin/email-templates/welcome/EN', '/admin/email-templates/welcome/fr_ABCDEFGHI'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404, $path);
        }

        $this->save('nothing', ['subject' => 'x', 'body' => 'y']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testALocaleThatFallsBack(): void
    {
        $page = $this->editor()->request('GET', '/admin/email-templates/welcome/fr-CA');

        self::assertStringContainsString('(shown from the locale fr)', $page->filter('main > p')->eq(1)->text());
    }

    public function testTheFrenchLabelsHaveTheSameKeys(): void
    {
        $english = require \dirname(__DIR__, 2).'/translations/phil_email_templates.en.php';
        $french = require \dirname(__DIR__, 2).'/translations/phil_email_templates.fr.php';

        self::assertIsArray($english);
        self::assertIsArray($french);
        self::assertSame(array_keys($english), array_keys($french));
    }

    private function editor(): KernelBrowser
    {
        $this->client->loginUser(new InMemoryUser('editor-1', 'x', ['ROLE_MAIL']));

        return $this->client;
    }

    /**
     * Posts an action that takes no field: publish, discard or reset.
     */
    private function act(string $key, string $action, ?string $scope = null): void
    {
        $this->client->request('POST', '/admin/email-templates/'.$key.'/en'.(null === $scope ? '' : '?scope='.$scope), ['_token' => 'csrf-token', 'action' => $action], server: self::SAME_ORIGIN);
    }

    /**
     * The rows of the published versions: number, moment, author.
     *
     * @return list<string>
     */
    private function history(Crawler $page): array
    {
        return $page->filter('section[aria-labelledby="phil-email-template-history"] tbody tr')->each(static fn (Crawler $row): string => trim(implode(' ', $row->filter('td')->each(static fn (Crawler $cell): string => $cell->text()))));
    }

    /**
     * @param array<string, string> $fields
     */
    private function save(string $key, array $fields, int $expected = 0, ?string $scope = null): Crawler
    {
        return $this->client->request('POST', '/admin/email-templates/'.$key.'/en'.(null === $scope ? '' : '?scope='.$scope), ['_token' => 'csrf-token', 'action' => 'save', 'expected' => (string) $expected, ...$fields], server: self::SAME_ORIGIN);
    }
}
