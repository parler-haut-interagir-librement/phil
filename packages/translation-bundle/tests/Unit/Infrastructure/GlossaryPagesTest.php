<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Infrastructure;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use Phil\TranslationBundle\Application\Credit;
use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationBundle\Infrastructure\EasyAdmin\TranslationAdmin;
use Phil\TranslationBundle\Infrastructure\Http\GlossaryController;
use Phil\TranslationBundle\Infrastructure\Http\Routes;
use Phil\TranslationBundle\Infrastructure\Http\SameOriginGuard;
use Phil\TranslationBundle\Infrastructure\Twig\CreditsExtension;
use Phil\TranslationBundle\PhilTranslationBundle;
use Phil\TranslationBundle\Tests\Unit\Fixture\Policy;
use Phil\TranslationBundle\Tests\Unit\Fixture\Router;
use Phil\TranslationBundle\Tests\Unit\Fixture\Sources;
use Phil\TranslationBundle\Tests\Unit\Fixture\Stores;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Tbx\TbxReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(GlossaryController::class)]
#[CoversClass(CreditsExtension::class)]
#[CoversClass(TranslationAdmin::class)]
final class GlossaryPagesTest extends TestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private Stores $stores;

    private Policy $policy;

    private RequestStack $requests;

    private SameOriginGuard $guard;

    private string $upload = '';

    protected function setUp(): void
    {
        $this->stores = new Stores();
        $this->policy = new Policy();
        $this->requests = new RequestStack();
        $this->guard = new SameOriginGuard(new SameOriginCsrfTokenManager($this->requests, null, null, [SameOriginGuard::TOKEN_ID]));
    }

    protected function tearDown(): void
    {
        if ('' !== $this->upload && is_file($this->upload)) {
            unlink($this->upload);
        }
    }

    public function testThePageShowsTheTermsAndTheForms(): void
    {
        $this->stores->glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr-CA'), new Term('hit points', ['points de vie'], ['vie'], true, 'Health.', ['hit point'], ['point de vie']));
        $this->stores->glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr-CA'), new Term('Gnome'));

        $response = $this->controller()->page(new Request(), 'en', 'fr-ca');
        $html = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('<title>Glossary en → fr-CA</title>', $html);
        self::assertStringContainsString('<td><b>Gnome</b></td>', $html);
        self::assertStringContainsString('<td>kept as it is</td>', $html);
        self::assertStringContainsString('<td><b>hit points</b> <small>(hit point)</small> <small>case-sensitive</small></td>', $html);
        self::assertStringContainsString('<td>points de vie <small>(point de vie)</small></td>', $html);
        self::assertStringContainsString('<td>vie</td>', $html);
        self::assertStringContainsString('<td>Health.</td>', $html);
        self::assertStringContainsString('<input type="hidden" name="term" value="hit points">', $html);
        self::assertStringContainsString('action="/_t/phil_translation_glossary_save?source=en&amp;target=fr-CA"', $html);
        self::assertStringContainsString('href="/_t/phil_translation_glossary_export?source=en&amp;target=fr-CA"', $html);
        self::assertStringContainsString('action="/_t/phil_translation_glossary_import?source=en&amp;target=fr-CA"', $html);
        self::assertSame(4, substr_count($html, '<input type="hidden" name="_csrf_token" value="csrf-token">'), 'the import, two removals and the term form');
        self::assertStringNotContainsString('role="status"', $html);
        self::assertStringContainsString('This glossary has no terms yet.', (string) $this->controller()->page(new Request(), 'en', 'de')->getContent());
    }

    public function testThePageReportsWhatWasDone(): void
    {
        self::assertStringContainsString('<p class="done" role="status">Term saved.</p>', (string) $this->controller()->page(new Request(['done' => 'saved']), 'en', 'fr')->getContent());
        self::assertStringContainsString('<p class="done" role="status">Term removed.</p>', (string) $this->controller()->page(new Request(['done' => 'removed']), 'en', 'fr')->getContent());
        self::assertStringContainsString('<p class="done" role="status">3 terms imported.</p>', (string) $this->controller()->page(new Request(['done' => 'imported', 'count' => '3']), 'en', 'fr')->getContent());
        self::assertStringContainsString('<p class="done" role="status">1 term imported.</p>', (string) $this->controller()->page(new Request(['done' => 'imported', 'count' => '1']), 'en', 'fr')->getContent());
        self::assertStringContainsString('<p class="done" role="status">0 terms imported.</p>', (string) $this->controller()->page(new Request(['done' => 'imported', 'count' => '-1']), 'en', 'fr')->getContent());
        self::assertStringNotContainsString('role="status"', (string) $this->controller()->page(new Request(['done' => 'published']), 'en', 'fr')->getContent());
    }

    public function testSavesATermThroughTheGlossaries(): void
    {
        $response = $this->controller()->save($this->post([
            'action' => 'save',
            'term' => ' hit points ',
            'translations' => "points de vie\r\n\r\n PV \n",
            'forbidden' => 'vie',
            'forms' => 'hit point',
            'translation_forms' => 'point de vie',
            'case_sensitive' => '1',
            'note' => '  Health.  ',
        ]), 'en', 'fr');

        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertSame('/_t/phil_translation_glossary?source=en&target=fr&done=saved', $response->headers->get('Location'));
        self::assertEquals([new Term('hit points', ['points de vie', 'PV'], ['vie'], true, 'Health.', ['hit point'], ['point de vie'])], $this->terms('fr'));

        $this->controller()->save($this->post(['action' => 'save', 'term' => 'hit points', 'note' => ' ']), 'en', 'fr');
        self::assertEquals([new Term('hit points')], $this->terms('fr'));
    }

    public function testRemovesATerm(): void
    {
        $this->stores->glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('dragon'));
        $response = $this->controller()->save($this->post(['action' => 'remove', 'term' => 'dragon']), 'en', 'fr');

        self::assertSame('/_t/phil_translation_glossary?source=en&target=fr&done=removed', $response->headers->get('Location'));
        self::assertSame([], $this->terms('fr'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('The glossary from "en" to "fr" has no term "dragon".');
        $this->controller()->save($this->post(['action' => 'remove', 'term' => 'dragon']), 'en', 'fr');
    }

    public function testShowsWhyATermIsRefused(): void
    {
        $response = $this->controller()->save($this->post(['action' => 'save', 'term' => '  ']), 'en', 'fr');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('<p class="error" role="alert">A glossary term, its translations and their forms are texts of UTF-8 that hold words.</p>', (string) $response->getContent());

        $response = $this->controller()->save($this->post(['action' => 'save', 'term' => str_repeat('t', 256)]), 'en', 'fr');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('A glossary term is at most 255 bytes.', (string) $response->getContent());
    }

    public function testRefusesAnActionItDoesNotKnow(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageIsOrContains('"rename" is not an action of the glossary.');

        $this->controller()->save($this->post(['action' => 'rename']), 'en', 'fr');
    }

    public function testRefusesAFormFromAnotherSite(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIsOrContains('The form was not posted from this site.');

        $this->controller()->save($this->post(['action' => 'save', 'term' => 'x'], ['Sec-Fetch-Site' => 'cross-site']), 'en', 'fr');
    }

    public function testThePagesNeedThePolicyAndItsConsent(): void
    {
        try {
            $this->controller(null)->page(new Request(), 'en', 'fr');
            self::fail('No policy, no glossary.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame('There is no glossary to edit.', $notFoundHttpException->getMessage());
        }

        try {
            $this->controller()->page(new Request(), 'en', 'e');
            self::fail('A language that is not one.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertStringContainsString('"e" is not a language tag', $notFoundHttpException->getMessage());
        }

        try {
            $this->controller()->page(new Request(), 'x', 'fr');
            self::fail('A source language that is not one.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertStringContainsString('"x" is not a language tag', $notFoundHttpException->getMessage());
        }

        try {
            $this->controller()->page(new Request(), 'fr', 'FR');
            self::fail('The same language twice.');
        } catch (AccessDeniedHttpException $accessDeniedHttpException) {
            self::assertSame('Editing the glossary from "fr" to "fr" is not allowed here.', $accessDeniedHttpException->getMessage());
        }

        $this->policy->glossary = false;
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIsOrContains('Editing the glossary from "en" to "fr" is not allowed here.');
        $this->controller()->export('en', 'fr');
    }

    public function testExportsTheGlossaryAsTbx(): void
    {
        $this->stores->glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('dragon', ['dragon']));
        $response = $this->controller()->export('en', 'fr');

        self::assertSame('application/x-tbx+xml; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=glossary.en.fr.tbx', $response->headers->get('Content-Disposition'));
        self::assertSame(['dragon'], array_map(static fn (Term $term): string => $term->source, new TbxReader()->read((string) $response->getContent(), LanguageTag::of('en'), LanguageTag::of('fr'))->terms));
    }

    public function testImportsATbxFile(): void
    {
        $other = new Stores();
        $other->glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('dragon', ['dragon']));
        $other->glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('hit points', ['points de vie']));

        $response = $this->controller()->import($this->upload($other->glossaries->export(LanguageTag::of('en'), LanguageTag::of('fr'))), 'en', 'fr');

        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertSame('/_t/phil_translation_glossary?source=en&target=fr&done=imported&count=2', $response->headers->get('Location'));
        self::assertSame(['dragon', 'hit points'], array_map(static fn (Term $term): string => $term->source, $this->terms('fr')));
    }

    public function testRefusesAnImportWithoutAnUsableFile(): void
    {
        $response = $this->controller()->import($this->post([]), 'en', 'fr');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('Choose a TBX file of at most 10 MiB.', (string) $response->getContent());

        $response = $this->controller()->import($this->upload('<xliff/>'), 'en', 'fr');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('The TBX file cannot be read', (string) $response->getContent());

        $response = $this->controller()->import($this->upload('<tbx><text><body><conceptEntry><langSec xml:lang="en"><termSec><term>'.str_repeat('t', 256).'</term></termSec></langSec><langSec xml:lang="fr"><termSec><term>t</term></termSec></langSec></conceptEntry></body></text></tbx>'), 'en', 'fr');
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('A glossary term is at most 255 bytes.', (string) $response->getContent());
        self::assertSame(10_485_760, GlossaryController::MAX_UPLOAD);
    }

    public function testCreditsTheTranslatorsInTwig(): void
    {
        $this->stores->translations->prepare('page:1', new Sources()->documents['page:1']->content, LanguageTag::of('en'), LanguageTag::of('fr-CA'));
        $this->stores->translators->names = ['ana' => 'Ana', 'ben' => 'Ben', 'cleo' => 'Cléo'];

        foreach (['ana' => 'p1/text', 'ben' => 'p2/text'] as $person => $key) {
            $this->stores->translators->current = $person;
            $this->stores->translations->translate('page:1', LanguageTag::of('fr-CA'), $key, 'Texte.');
        }

        $extension = new CreditsExtension($this->stores->translations);
        self::assertEquals([new Credit('Ana', 1), new Credit('Ben', 1)], $extension->credits('page:1', 'fr_CA'));
        self::assertSame([], $extension->credits('page:1', 'not a tag'));

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilTranslation');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new AttributeExtension(CreditsExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([CreditsExtension::class => static fn (): CreditsExtension => $extension]));

        self::assertSame('<p class="phil-translation-credits">Translated by Ana and Ben. Thank you!</p>', trim($twig->render('@PhilTranslation/credits.html.twig', ['document' => 'page:1', 'language' => 'fr-CA'])));
        self::assertSame('', trim($twig->render('@PhilTranslation/credits.html.twig', ['document' => 'page:2', 'language' => 'fr-CA'])));

        $this->stores->translators->current = 'cleo';
        $this->stores->translations->review('page:1', LanguageTag::of('fr-CA'), 'p1/text');
        self::assertSame('<p class="phil-translation-credits">Translated by Ana, Ben and Cléo. Thank you!</p>', trim($twig->render('@PhilTranslation/credits.html.twig', ['document' => 'page:1', 'language' => 'fr-CA'])));
    }

    public function testLinksEasyAdminToTheGlossaries(): void
    {
        $items = TranslationAdmin::menuItems([['en', 'fr'], ['EN', 'fr-ca']]);

        self::assertCount(2, $items);
        self::assertContainsOnlyInstancesOf(RouteMenuItem::class, $items);
        $dto = $items[1]->getAsDto();
        self::assertSame(['Glossary en → fr-CA', 'fa fa-language', Routes::GLOSSARY, ['source' => 'en', 'target' => 'fr-CA']], [$dto->getLabel(), $dto->getIcon(), $dto->getRouteName(), $dto->getRouteParameters()]);

        $dto = TranslationAdmin::menuItems([['en', 'de']], 'Glossaire %s–%s', 'fa fa-book')[0]->getAsDto();
        self::assertSame(['Glossaire en–de', 'fa fa-book'], [$dto->getLabel(), $dto->getIcon()]);
        self::assertSame([], TranslationAdmin::menuItems([]));
    }

    public function testAnImportFromAnotherSiteIsRefused(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIsOrContains('The form was not posted from this site.');

        $this->controller()->import($this->post([], ['Sec-Fetch-Site' => 'cross-site']), 'en', 'fr');
    }

    public function testAFileThatDidNotUploadIsRefusedWhateverItsSize(): void
    {
        $partial = new UploadedFile(__FILE__, 'glossary.tbx', null, \UPLOAD_ERR_PARTIAL, true);

        $response = $this->controller()->import($this->post([], files: ['tbx' => $partial]), 'en', 'fr');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('Choose a TBX file of at most 10 MiB.', (string) $response->getContent());
    }

    public function testAFileOfTheMaximumSizeIsReadAndOneByteMoreIsNot(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tbx');
        $this->upload = $path;
        $handle = fopen($path, 'w');
        self::assertIsResource($handle);
        ftruncate($handle, GlossaryController::MAX_UPLOAD);
        fclose($handle);

        $read = $this->controller()->import($this->post([], files: ['tbx' => new UploadedFile($path, 'glossary.tbx', null, null, true)]), 'en', 'fr');
        self::assertStringContainsString('The TBX file cannot be read', (string) $read->getContent());
        self::assertStringNotContainsString('Choose a TBX file', (string) $read->getContent());

        $handle = fopen($path, 'a');
        self::assertIsResource($handle);
        fwrite($handle, ' ');
        fclose($handle);

        $refused = $this->controller()->import($this->post([], files: ['tbx' => new UploadedFile($path, 'glossary.tbx', null, null, true)]), 'en', 'fr');
        self::assertStringContainsString('Choose a TBX file of at most 10 MiB.', (string) $refused->getContent());
    }

    public function testTheLinesOfAFieldAreTrimmedAndTheEmptyOnesLeftOut(): void
    {
        $this->controller()->save($this->post([
            'action' => 'save',
            'term' => 'door',
            'translations' => "  porte  \n \t \n\n  huis\t",
            'note' => " \t ",
        ]), 'en', 'fr');

        self::assertSame(['porte', 'huis'], $this->terms('fr')[0]->translations);
        self::assertNull($this->terms('fr')[0]->note);
    }

    /**
     * @return list<Term>
     */
    private function terms(string $target): array
    {
        return array_map(static fn (TermRecord $record): Term => $record->term(), $this->stores->glossaries->terms(LanguageTag::of('en'), LanguageTag::of($target)));
    }

    private function controller(?Policy $policy = new Policy()): GlossaryController
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilTranslation');

        return new GlossaryController($this->stores->glossaries, new Environment($loader, ['strict_variables' => true]), new Router(), $this->guard, PhilTranslationBundle::DEFAULT_LAYOUT, $policy instanceof Policy ? $this->policy : null);
    }

    /**
     * @param array<string, string>       $fields
     * @param array<string, string>       $headers
     * @param array<string, UploadedFile> $files
     */
    private function post(array $fields, array $headers = ['Sec-Fetch-Site' => 'same-origin'], array $files = []): Request
    {
        $request = Request::create('http://localhost/_t/x', 'POST', [SameOriginGuard::FIELD => self::TOKEN, ...$fields], ['csrf-token_'.self::TOKEN => 'csrf-token'], $files);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $this->requests->push($request);

        return $request;
    }

    private function upload(string $xml): Request
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'tbx');
        file_put_contents($path, $xml);
        $this->upload = $path;

        return $this->post([], files: ['tbx' => new UploadedFile($path, 'glossary.tbx', 'application/x-tbx+xml', null, true)]);
    }
}
