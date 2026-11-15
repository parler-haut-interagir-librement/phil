<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use FilesystemIterator;
use Override;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsTables;
use Phil\TermsBundle\Infrastructure\Http\AcceptanceController;
use Phil\TermsBundle\Infrastructure\Http\Pages;
use Phil\TermsBundle\Infrastructure\Http\PolicyController;
use Phil\TermsBundle\Infrastructure\Http\TermsAdminController;
use Phil\TermsBundle\Infrastructure\Twig\KitComponents;
use Phil\TermsBundle\Infrastructure\Twig\TermsTwig;
use Phil\TermsBundle\Tests\Functional\App\TestKernel;
use Phil\TermsBundle\Tests\Unit\Fixture\KitRecipes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The public pages and the administration drawn by each kit an application may install: the PHIL UX kit, in a
 * subdirectory of the components or at their root, and the Shadcn kit of the Symfony UX Toolkit. Each page keeps the
 * same structure whichever kit draws it — headings, the label of each control, the header cells — and no prop of one
 * kit leaks into the markup of the other as an attribute. No template writes a word of its own.
 */
#[CoversClass(AcceptanceController::class)]
#[CoversClass(PolicyController::class)]
#[CoversClass(TermsAdminController::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(TermsTwig::class)]
final class KitRenderingTest extends KernelTestCase
{
    /** Props of one kit that are not attributes of HTML here, and would be written as such by the other. */
    private const array LEAKED_PROPS = ['as', 'level', 'invalid', 'variant', 'orientation'];

    private static string $kit = KitRecipes::PHIL;

    private static string $subdirectory = 'ui';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function kits(): iterable
    {
        yield 'PHIL UX in a subdirectory' => [KitRecipes::PHIL, 'ui'];
        yield 'PHIL UX' => [KitRecipes::PHIL, ''];
        yield 'Shadcn' => [KitRecipes::SHADCN, ''];
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true, self::$kit, self::$subdirectory);
    }

    #[DataProvider('kits')]
    public function testThePublicPagesKeepTheirStructureWithEachKit(string $kit, string $subdirectory): void
    {
        $this->boot($kit, $subdirectory);
        $this->terms()->publish('terms', ['en' => 'One rule.']);
        $this->terms()->publish('terms', ['en' => 'One new rule.'], material: false);
        $this->terms()->publish('privacy', ['en' => 'The privacy policy.'], effectiveAt: new DateTimeImmutable('+1 month'));

        $policy = $this->page('GET', '/terms/terms');
        self::assertSame('terms of use', $policy->filter('article h1')->text());
        self::assertStringStartsWith('In force since ', trim($policy->filter('article [data-slot="badge"]')->text()));
        self::assertSame(['to accept again'], $policy->filter('nav li [data-slot="badge"]')->each(static fn (Crawler $badge): string => trim($badge->text())));
        self::assertSame('Version 2', $policy->filter('nav a[aria-current="page"]')->text());
        $this->assertNoPropLeaks($policy);

        $changes = $this->page('GET', '/terms/terms/v2/changes/1');
        self::assertSame(['/terms/terms/v1', '/terms/terms/v2'], $changes->filter('a[data-slot="button"]')->each(static fn (Crawler $link): ?string => $link->attr('href')));
        self::assertCount(1, $changes->filter('.phil-terms-changes ins'));
        $this->assertNoPropLeaks($changes);

        $accept = $this->page('GET', '/terms/accept', ['X-Member' => '42']);
        self::assertSame(['terms of use'], $accept->filter('section h2')->each(static fn (Crawler $title): string => $title->text()));
        $box = $accept->filter('[data-slot="field"] input[type="checkbox"]#phil-terms-accept-terms');
        self::assertSame(['accept[terms]', '2', 'required'], [$box->attr('name'), $box->attr('value'), null === $box->attr('required') ? null : 'required']);
        self::assertSame('I have read and I accept version 2 of the terms of use', $accept->filter('label[for="phil-terms-accept-terms"]')->text());
        self::assertSame('submit', $accept->filter('button[data-slot="button"]')->attr('type'));
        self::assertCount(0, $accept->filter('[role="alert"]'));
        $this->assertNoPropLeaks($accept);

        $refused = $this->page('POST', '/terms/accept', ['X-Member' => '42'], ['_token' => $this->token()]);
        self::assertSame('Tick the box of each version to accept it.', trim($refused->filter('[data-slot="alert"][role="alert"]')->text()));
        $this->assertNoPropLeaks($refused);
    }

    #[DataProvider('kits')]
    public function testTheAdministrationKeepsItsStructureWithEachKit(string $kit, string $subdirectory): void
    {
        $this->boot($kit, $subdirectory);
        $this->terms()->publish('terms', ['en' => 'One rule.']);
        $admin = ['X-Roles' => 'ROLE_LEGAL'];

        $list = $this->page('GET', '/admin/terms/', $admin);
        self::assertSame(['Version', 'Published', 'Takes effect', 'Material', 'Locales'], $list->filter('[data-slot="table-container"] > table th[scope="col"]')->each(static fn (Crawler $cell): string => $cell->text()));
        self::assertSame('Moments in the time zone UTC.', $list->filter('table caption')->text());
        self::assertSame(['To accept to go on', 'To accept to go on'], $list->filter('section [data-slot="badge"]')->each(static fn (Crawler $badge): string => trim($badge->text())));
        self::assertSame(['/admin/terms/terms/publish', '/admin/terms/privacy/publish'], $list->filter('a[data-slot="button"]')->each(static fn (Crawler $link): ?string => $link->attr('href')));
        $this->assertNoPropLeaks($list);

        $form = $this->page('GET', '/admin/terms/terms/publish', $admin);
        self::assertSame(['Format', 'Text (en)', 'Text (fr)', 'Takes effect on', 'Material change: every member accepts it again'], $form->filter('[data-slot="field"] label')->each(static fn (Crawler $label): string => $label->text()));
        self::assertSame(['phil-terms-format', 'phil-terms-text-en', 'phil-terms-text-fr', 'phil-terms-effective-at', 'phil-terms-material'], $form->filter('[data-slot="field"] label')->each(static fn (Crawler $label): ?string => $label->attr('for')));
        self::assertSame(['text', 'ujo'], $form->filter('select#phil-terms-format option')->each(static fn (Crawler $option): ?string => $option->attr('value')));
        self::assertSame('text', $form->filter('select#phil-terms-format option[selected]')->attr('value'));
        self::assertSame(['texts[en]', 'texts[fr]'], $form->filter('textarea')->each(static fn (Crawler $text): ?string => $text->attr('name')));
        self::assertSame('datetime-local', $form->filter('input#phil-terms-effective-at')->attr('type'));
        self::assertSame('phil-terms-effective-at-help', $form->filter('input#phil-terms-effective-at')->attr('aria-describedby'));
        self::assertSame('material', $form->filter('input[type="checkbox"]#phil-terms-material')->attr('name'));
        self::assertSame('submit', $form->filter('button[data-slot="button"]')->attr('type'));
        $this->assertNoPropLeaks($form);

        $refused = $this->page('POST', '/admin/terms/terms/publish', $admin, ['texts' => ['en' => ''], 'format' => 'text', '_token' => $this->token()]);
        self::assertSame('Write the text of one locale at least.', trim($refused->filter('[data-slot="alert"][role="alert"]')->text()));
        $this->assertNoPropLeaks($refused);

        $this->request('POST', '/admin/terms/terms/publish', $admin, ['texts' => ['en' => 'Two rules.'], 'format' => 'text', '_token' => $this->token()]);
        $published = $this->page('GET', '/admin/terms/?done=published', $admin);
        self::assertSame(['alert'], $published->filter('[data-slot="alert"]')->each(static fn (Crawler $alert): ?string => $alert->attr('role')));
        self::assertSame('The version was published.', trim($published->filter('[data-slot="alert"]')->text()));
    }

    /**
     * Words live in the translation catalogues, never in the markup of a template: the application translates them.
     */
    public function testNoTemplateWritesAWordOfItsOwn(): void
    {
        $templates = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\dirname(__DIR__, 2).'/templates', FilesystemIterator::SKIP_DOTS)) as $file) {
            $this->assertInstanceOf(SplFileInfo::class, $file);
            $source = (string) preg_replace(['/\{#.*?#\}/s', '/\{%.*?%\}/s', '/\{\{.*?\}\}/s', '/"[^"]*"/s', '/<[^>]*>/s', '/&#?\w+;/'], '', (string) file_get_contents($file->getPathname()));

            self::assertDoesNotMatchRegularExpression('/\p{L}/u', $source, $file->getPathname());
            ++$templates;
        }

        self::assertSame(7, $templates);
    }

    private function boot(string $kit, string $subdirectory): void
    {
        if (KitRecipes::SHADCN === $kit && null === KitRecipes::shadcnKit()) {
            self::markTestSkipped('The Symfony UX Toolkit is not installed.');
        }

        self::$kit = $kit;
        self::$subdirectory = $subdirectory;
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $schemaManager = $connection->createSchemaManager();

        foreach ([TermsTables::VERSIONS, TermsTables::ACCEPTANCES] as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $schemaManager->dropTable($table);
            }
        }

        foreach (TermsTables::configure(Schema::editor()->create())->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
    }

    private function terms(): Terms
    {
        $terms = self::getContainer()->get(Terms::class);
        self::assertInstanceOf(Terms::class, $terms);

        return $terms;
    }

    private function token(): string
    {
        $csrf = self::getContainer()->get('security.csrf.token_manager');
        self::assertInstanceOf(CsrfTokenManagerInterface::class, $csrf);

        return $csrf->getToken(Pages::CSRF_TOKEN_ID)->getValue();
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $form
     */
    private function page(string $method, string $uri, array $headers = [], array $form = []): Crawler
    {
        $response = $this->request($method, $uri, $headers, $form);
        $html = (string) $response->getContent();

        self::assertContains($response->getStatusCode(), [200, 422], $html);
        self::assertDoesNotMatchRegularExpression('/<[^>]*\s(role|class|type|for|id)="[^"]*"[^>]*\s\1="/', $html, 'No element carries an attribute twice.');

        return new Crawler($html, 'http://localhost'.$uri);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $form
     */
    private function request(string $method, string $uri, array $headers = [], array $form = []): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);
        $request = Request::create($uri, $method, $form);
        $request->headers->add($headers);

        $response = $kernel->handle($request);
        $resetter = self::getContainer()->get('services_resetter');
        self::assertInstanceOf(ResetInterface::class, $resetter);
        $resetter->reset();

        return $response;
    }

    private function assertNoPropLeaks(Crawler $page): void
    {
        foreach (self::LEAKED_PROPS as $prop) {
            self::assertCount(0, $page->filter('['.$prop.']'), \sprintf('No element carries the prop "%s" as an attribute.', $prop));
        }
    }
}
