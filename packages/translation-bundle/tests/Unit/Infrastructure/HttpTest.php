<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Infrastructure;

use Phil\TranslationBundle\Application\SourceDocument;
use Phil\TranslationBundle\Infrastructure\Http\Routes;
use Phil\TranslationBundle\Infrastructure\Http\SameOriginGuard;
use Phil\TranslationBundle\Infrastructure\Http\TranslationController;
use Phil\TranslationBundle\PhilTranslationBundle;
use Phil\TranslationBundle\Tests\Unit\Fixture\Policy;
use Phil\TranslationBundle\Tests\Unit\Fixture\Router;
use Phil\TranslationBundle\Tests\Unit\Fixture\Sources;
use Phil\TranslationBundle\Tests\Unit\Fixture\Stores;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\Xliff\XliffFile;
use Phil\TranslationMemory\Xliff\XliffReader;
use Phil\TranslationMemory\Xliff\XliffUnit;
use Phil\TranslationMemory\Xliff\XliffWriter;
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
use Twig\Loader\FilesystemLoader;

#[CoversClass(TranslationController::class)]
#[CoversClass(SameOriginGuard::class)]
#[CoversClass(Routes::class)]
final class HttpTest extends TestCase
{
    private Stores $stores;

    private Policy $policy;

    private const string TOKEN = 'a-token-of-24-characters';

    private string $upload = '';

    private RequestStack $requests;

    private SameOriginGuard $guard;

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

    public function testThePageShowsTheSegmentsOfTheDocument(): void
    {
        $this->stores->memory->add(new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), 'The <b>red</b> doors.', 'Les portes <b>rouges</b>.'));
        $this->stores->glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('door', ['porte'], ['huis'], note: 'Never “huis”.'));
        $this->stores->glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('world'));

        $response = $this->controller()->page(new Request(), 'page:1', 'fr');
        $html = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('<title>Translate Welcome into fr</title>', $html);
        self::assertStringContainsString('<small>en → fr</small>', $html);
        self::assertStringContainsString('0 % usable — 0 of 2 segments:', $html);
        self::assertStringContainsString('untranslated 2, suggested 0, translated 0, reviewed 0, stale 0', $html);
        self::assertStringContainsString('<code>The &lt;b&gt;red&lt;/b&gt; door.</code>', $html);
        self::assertStringContainsString('<tr id="segment-'.hash('xxh3', 'p2/text').'">', $html);
        self::assertStringContainsString('action="/_t/phil_translation_save?document=page%3A1&amp;language=fr"', $html);
        self::assertStringContainsString('href="/_t/phil_translation_export?document=page%3A1&amp;language=fr"', $html);
        self::assertStringContainsString('action="/_t/phil_translation_import?document=page%3A1&amp;language=fr"', $html);
        self::assertStringContainsString('<input type="hidden" name="target" value="Les portes &lt;b&gt;rouges&lt;/b&gt;.">', $html);
        self::assertStringContainsString('92 % <code>Les portes &lt;b&gt;rouges&lt;/b&gt;.</code>', $html);
        self::assertStringContainsString('<li><b>door</b>: porte; not huis — Never “huis”.</li>', $html);
        self::assertStringContainsString('<li><b>world</b>: world (kept)</li>', $html);
        self::assertStringNotContainsString('value="review"', $html);
        self::assertStringNotContainsString('role="status"', $html);
        self::assertStringNotContainsString('role="alert"', $html);
    }

    public function testThePageAndTheExportStoreNothing(): void
    {
        self::assertSame(200, $this->controller()->page(new Request(), 'page:1', 'fr')->getStatusCode());
        self::assertSame(200, $this->controller()->export('page:1', 'fr')->getStatusCode());
        self::assertSame([], $this->stores->translations->segments('page:1', LanguageTag::of('fr')));
    }

    public function testThePageShowsTargetsIssuesAndChanges(): void
    {
        $this->stores->translations->prepare('page:1', new Sources()->documents['page:1']->content, LanguageTag::of('en'), LanguageTag::of('fr'));
        $this->stores->glossaries->put(LanguageTag::of('en'), LanguageTag::of('fr'), new Term('door', ['porte'], ['huis']));
        $this->stores->translations->translate('page:1', LanguageTag::of('fr'), 'p1/text', 'Bonjour le monde.');
        $this->stores->translations->translate('page:1', LanguageTag::of('fr'), 'p2/text', 'Le huis <b>rouge</b>.');

        $sources = new Sources(['page:1' => new SourceDocument(Sources::page('Hello world.', 'The <b>blue</b> door.'), LanguageTag::of('en'))]);
        $html = (string) $this->controller($sources)->page(new Request(['done' => 'saved']), 'page:1', 'fr')->getContent();

        self::assertStringContainsString('<title>Translate page:1 into fr</title>', $html);
        self::assertStringContainsString('50 % usable — 1 of 2 segments:', $html);
        self::assertStringContainsString('<p class="done" role="status">Segment saved.</p>', $html);
        self::assertStringContainsString('Source changed: The <del>red</del><ins>blue</ins> door.', $html);
        self::assertStringContainsString('lang="fr">Le huis &lt;b&gt;rouge&lt;/b&gt;.</textarea>', $html);
        self::assertStringContainsString('“door”: porte expected 1 time, found 0', $html);
        self::assertStringContainsString('“huis” is not a translation of “door”', $html);
        self::assertStringContainsString('value="review"', $html);
        self::assertStringContainsString('value="confirm"', $html);
        self::assertSame(1, substr_count($html, 'value="review"'));
        self::assertSame(2, substr_count($html, 'value="clear"'));
        self::assertSame(1, substr_count($html, 'class="suggestions"'));
        self::assertStringContainsString('71 % <code>Le huis &lt;b&gt;rouge&lt;/b&gt;.</code>', $html);
    }

    public function testThePageIgnoresAnOutcomeItDoesNotKnow(): void
    {
        self::assertStringNotContainsString('role="status"', (string) $this->controller()->page(new Request(['done' => '<script>']), 'page:1', 'fr')->getContent());
    }

    public function testThePageOfADocumentWithoutTextSaysSo(): void
    {
        $sources = new Sources(['empty' => new SourceDocument(Sources::page(), LanguageTag::of('en'))]);

        self::assertStringContainsString('This document has nothing to translate.', (string) $this->controller($sources)->page(new Request(), 'empty', 'fr')->getContent());
    }

    public function testTheScreenNeedsItsDocumentsAndItsPolicy(): void
    {
        foreach ([[null, $this->policy, 'page:1'], [new Sources(), null, 'page:1'], [new Sources(), $this->policy, 'page:9']] as [$sources, $policy, $document]) {
            try {
                new TranslationController($this->stores->translations, $this->stores->comments, $this->twig(), new Router(), $this->guard, PhilTranslationBundle::DEFAULT_LAYOUT, $sources, $policy)->page(new Request(), $document, 'fr');
                self::fail('The page was shown.');
            } catch (NotFoundHttpException $notFoundHttpException) {
                self::assertSame(\sprintf('There is no document "%s" to translate.', $document), $notFoundHttpException->getMessage());
            }
        }
    }

    public function testRefusesALanguageThatIsNotOne(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('"f_r" is not a language tag');

        $this->controller()->page(new Request(), 'page:1', 'f_r');
    }

    public function testRefusesTheLanguageOfTheSourceAndAUserWhoMayNotTranslate(): void
    {
        try {
            $this->controller()->page(new Request(), 'page:1', 'EN');
            self::fail('The source language was accepted.');
        } catch (AccessDeniedHttpException $accessDeniedHttpException) {
            self::assertSame('Translating "page:1" into "en" is not allowed here.', $accessDeniedHttpException->getMessage());
        }

        $this->policy->translate = false;
        $this->expectException(AccessDeniedHttpException::class);

        $this->controller()->page(new Request(), 'page:1', 'fr');
    }

    public function testSavesEachActionAndGoesBackToTheSegment(): void
    {
        $this->controller()->page(new Request(), 'page:1', 'fr');
        $anchor = '#segment-'.hash('xxh3', 'p1/text');

        foreach ([['save', 'Bonjour.', 'translated', 'saved'], ['review', '', 'reviewed', 'reviewed'], ['suggest', 'Salut.', 'suggested', 'suggested'], ['clear', '', 'untranslated', 'cleared']] as [$action, $target, $state, $done]) {
            $response = $this->controller()->save($this->post(['key' => 'p1/text', 'action' => $action, 'target' => $target]), 'page:1', 'fr');

            self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
            self::assertSame('/_t/phil_translation_page?document=page%3A1&language=fr&done='.$done.$anchor, $response->headers->get('Location'));
            self::assertSame($state, $this->stores->translations->segment('page:1', LanguageTag::of('fr'), 'p1/text')->state->value);
        }

        $this->stores->translations->translate('page:1', LanguageTag::of('fr'), 'p1/text', 'Bonjour.');
        $this->controller(new Sources(['page:1' => new SourceDocument(Sources::page('Hello world!'), LanguageTag::of('en'))]))->save($this->post(['key' => 'p1/text', 'action' => 'confirm']), 'page:1', 'fr');
        self::assertSame(SegmentState::Translated, $this->stores->translations->segment('page:1', LanguageTag::of('fr'), 'p1/text')->state);
        self::assertSame('Hello world!', $this->stores->translations->segment('page:1', LanguageTag::of('fr'), 'p1/text')->translatedSource);
    }

    public function testRefusesAnActionItDoesNotKnow(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessageIsOrContains('"publish" is not an action of the translation screen.');

        $this->controller()->save($this->post(['key' => 'p1/text', 'action' => 'publish']), 'page:1', 'fr');
    }

    public function testRefusesAReviewToWhoMayNotReview(): void
    {
        $this->policy->review = false;
        $this->stores->translations->prepare('page:1', new Sources()->documents['page:1']->content, LanguageTag::of('en'), LanguageTag::of('fr'));
        $this->stores->translations->translate('page:1', LanguageTag::of('fr'), 'p1/text', 'Bonjour.');

        try {
            $this->controller()->save($this->post(['key' => 'p1/text', 'action' => 'review']), 'page:1', 'fr');
            self::fail('The review was accepted.');
        } catch (AccessDeniedHttpException $accessDeniedHttpException) {
            self::assertSame('Reviewing is not allowed here.', $accessDeniedHttpException->getMessage());
        }

        self::assertSame(Response::HTTP_SEE_OTHER, $this->controller()->save($this->post(['key' => 'p1/text', 'action' => 'save', 'target' => 'Salut.']), 'page:1', 'fr')->getStatusCode());
    }

    public function testShowsWhyASegmentCannotTakeTheChange(): void
    {
        $response = $this->controller()->save($this->post(['key' => 'p1/text', 'action' => 'review']), 'page:1', 'fr');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('<p class="error" role="alert">Segment &quot;p1/text&quot; is untranslated: only a target written for its source can be reviewed.</p>', (string) $response->getContent());
    }

    public function testShowsWhyTheFlagsOfASegmentCannotBeKept(): void
    {
        $this->stores->translations->prepare('page:1', new Sources()->documents['page:1']->content, LanguageTag::of('en'), LanguageTag::of('fr'));
        $this->stores->translations->flag('page:1', LanguageTag::of('fr'), 'p1/text', new Flags([...array_map(static fn (int $index): string => 'f'.$index.str_repeat('x', 28).':'.str_repeat('1', 32), range(10, 24)), 'g'.str_repeat('x', 20).':'.str_repeat('1', 8)]));

        $response = $this->controller()->save($this->post(['key' => 'p1/text', 'action' => 'dismiss', 'check' => 'tags']), 'page:1', 'fr');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('The flags of a segment are at most 1024 bytes.', (string) $response->getContent());
    }

    public function testRefusesASegmentTheDocumentDoesNotHave(): void
    {
        $this->controller()->page(new Request(), 'page:1', 'fr');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('Document "page:1" has no segment "p9/text" in "fr".');

        $this->controller()->save($this->post(['key' => 'p9/text', 'action' => 'clear']), 'page:1', 'fr');
    }

    public function testRefusesAFormFromAnotherSite(): void
    {
        foreach ([['Sec-Fetch-Site' => 'cross-site'], ['Origin' => 'https://evil.example'], []] as $headers) {
            try {
                $this->controller()->save($this->post(['key' => 'p1/text', 'action' => 'clear'], $headers), 'page:1', 'fr');
                self::fail('The form was accepted: '.json_encode($headers));
            } catch (AccessDeniedHttpException $accessDeniedHttpException) {
                self::assertSame('The form was not posted from this site.', $accessDeniedHttpException->getMessage());
            }
        }

        $this->guard->assert($this->post([], ['Sec-Fetch-Site' => 'same-origin', 'Origin' => 'https://evil.example']));
        $this->guard->assert($this->post([], ['Origin' => 'http://localhost']));
        $this->guard->assert($this->post([SameOriginGuard::FIELD => 'csrf-token'], ['Sec-Fetch-Site' => 'same-origin'], cookies: []));
        self::assertSame(['phil_translation', '_csrf_token', 'csrf-token'], [SameOriginGuard::TOKEN_ID, SameOriginGuard::FIELD, $this->guard->cookieName()]);

        foreach ([
            'another site of the same domain' => $this->post([], ['Sec-Fetch-Site' => 'same-site', 'Origin' => 'http://localhost']),
            'no token' => $this->post([SameOriginGuard::FIELD => ''], ['Sec-Fetch-Site' => 'same-origin']),
            'a token that is none' => $this->post([SameOriginGuard::FIELD => 'forged'], ['Sec-Fetch-Site' => 'same-origin']),
            'a token without its cookie nor the origin' => $this->post([], [], cookies: []),
        ] as $case => $request) {
            $this->requests->push($request);

            try {
                $this->guard->assert($request);
                self::fail($case);
            } catch (AccessDeniedHttpException $accessDeniedHttpException) {
                self::assertSame('The form was not posted from this site.', $accessDeniedHttpException->getMessage());
            }
        }
    }

    public function testTheFormsCarryTheFieldOfTheToken(): void
    {
        $this->stores->memory->add(new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), 'The <b>red</b> doors.', 'Les portes <b>rouges</b>.'));
        $html = (string) $this->controller()->page(new Request(), 'page:1', 'fr')->getContent();

        self::assertSame(6, substr_count($html, '<input type="hidden" name="_csrf_token" value="csrf-token">'), 'the import, two segments, a suggestion and two comment forms');
        self::assertStringContainsString('<main class="phil-translation" data-controller="phil--translation-bundle--csrf" data-action="submit->phil--translation-bundle--csrf#fill">', $html);
    }

    public function testRefusesAnImportFromAnotherSite(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->controller()->import($this->post([], ['Sec-Fetch-Site' => 'cross-site']), 'page:1', 'fr');
    }

    public function testExportsTheSegmentsAsAnXliffFile(): void
    {
        $this->stores->translations->prepare('page:1', new Sources()->documents['page:1']->content, LanguageTag::of('en'), LanguageTag::of('fr-CA'));
        $this->stores->translations->translate('page:1', LanguageTag::of('fr-CA'), 'p1/text', 'Bonjour le monde.');

        $response = $this->controller()->export('page:1', 'fr-ca');

        self::assertSame('application/xliff+xml; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=page-1.fr-CA.xlf', $response->headers->get('Content-Disposition'));
        $file = new XliffReader()->read((string) $response->getContent());
        self::assertSame(['p1/text', 'p2/text'], array_map(static fn (XliffUnit $unit): string => $unit->key, $file->units));
        self::assertSame(['Bonjour le monde.', null], array_map(static fn (XliffUnit $unit): ?string => $unit->target, $file->units));
        self::assertSame(['en', 'fr-CA', 'page:1'], [$file->sourceLanguage->value, $file->targetLanguage?->value, $file->original]);
    }

    public function testImportsAnXliffFileAndReportsWhatItDid(): void
    {
        $xml = new XliffWriter()->write(new XliffFile(LanguageTag::of('en'), LanguageTag::of('fr'), 'page:1', [
            new XliffUnit('p1/text', 'Hello world.', 'Bonjour le monde.', SegmentState::Translated),
            new XliffUnit('p9/text', 'Gone.', 'Parti.', SegmentState::Translated),
            new XliffUnit('p8/text', 'Gone too.', 'Parti aussi.', SegmentState::Translated),
        ]));
        $response = $this->controller()->import($this->upload($xml), 'page:1', 'fr');
        $html = (string) $response->getContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('1 segment updated, 2 units skipped.', $html);
        self::assertStringContainsString('<li><code>p9/text</code>: unknown-key</li>', $html);
        self::assertSame('Bonjour le monde.', $this->stores->translations->segment('page:1', LanguageTag::of('fr'), 'p1/text')->target);

        $single = new XliffWriter()->write(new XliffFile(LanguageTag::of('en'), LanguageTag::of('fr'), 'page:1', [new XliffUnit('p2/text', 'The <b>red</b> door.', 'La porte.', SegmentState::Translated)]));
        self::assertStringContainsString('1 segment updated, 0 units skipped.', (string) $this->controller()->import($this->upload($single), 'page:1', 'fr')->getContent());

        $none = new XliffWriter()->write(new XliffFile(LanguageTag::of('en'), LanguageTag::of('fr'), 'page:1', [new XliffUnit('p2/text', 'The <b>red</b> door.', 'La porte.', SegmentState::Translated), new XliffUnit('p1/text', 'Other.', 'Autre.', SegmentState::Translated)]));
        $html = (string) $this->controller()->import($this->upload($none), 'page:1', 'fr')->getContent();
        self::assertStringContainsString('0 segments updated, 2 units skipped.', $html);
    }

    public function testAnImportMarksReviewedOnlyForAReviewer(): void
    {
        $xml = new XliffWriter()->write(new XliffFile(LanguageTag::of('en'), LanguageTag::of('fr'), 'page:1', [
            new XliffUnit('p1/text', 'Hello world.', 'Bonjour le monde.', SegmentState::Reviewed),
        ]));

        $this->policy->review = false;
        $this->controller()->import($this->upload($xml), 'page:1', 'fr');
        self::assertSame(SegmentState::Translated, $this->stores->translations->segment('page:1', LanguageTag::of('fr'), 'p1/text')->state);

        $this->policy->review = true;
        $this->controller()->import($this->upload($xml), 'page:1', 'fr');
        self::assertSame(SegmentState::Reviewed, $this->stores->translations->segment('page:1', LanguageTag::of('fr'), 'p1/text')->state);
    }

    public function testRefusesAnImportWithoutAnUsableFile(): void
    {
        $missing = $this->controller()->import($this->post([]), 'page:1', 'fr');
        $failed = $this->controller()->import($this->post([], files: ['xliff' => new UploadedFile(__FILE__, 'a.xlf', null, \UPLOAD_ERR_PARTIAL, true)]), 'page:1', 'fr');

        foreach ([$missing, $failed] as $response) {
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
            self::assertStringContainsString('Choose an XLIFF file of at most 10 MiB.', (string) $response->getContent());
        }

        $this->upload = (string) tempnam(sys_get_temp_dir(), 'xlf');
        file_put_contents($this->upload, str_repeat(' ', TranslationController::MAX_UPLOAD));
        self::assertStringContainsString('it is not well-formed XML.', (string) $this->controller()->import($this->post([], files: ['xliff' => new UploadedFile($this->upload, 'a.xlf', null, null, true)]), 'page:1', 'fr')->getContent());
        file_put_contents($this->upload, ' ', \FILE_APPEND);
        self::assertStringContainsString('Choose an XLIFF file', (string) $this->controller()->import($this->post([], files: ['xliff' => new UploadedFile($this->upload, 'a.xlf', null, null, true)]), 'page:1', 'fr')->getContent());
    }

    public function testShowsWhyAFileCannotBeImported(): void
    {
        $other = new XliffWriter()->write(new XliffFile(LanguageTag::of('de'), LanguageTag::of('fr'), 'page:1'));

        foreach (['<!DOCTYPE x><x/>' => 'a document type declaration is not allowed.', $other => 'The source language is &quot;en&quot;, but &quot;de&quot; was given.'] as $xml => $message) {
            $response = $this->controller()->import($this->upload($xml), 'page:1', 'fr');

            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
            self::assertStringContainsString($message, (string) $response->getContent());
        }
    }

    private function controller(?Sources $sources = null): TranslationController
    {
        return new TranslationController($this->stores->translations, $this->stores->comments, $this->twig(), new Router(), $this->guard, PhilTranslationBundle::DEFAULT_LAYOUT, $sources ?? new Sources(), $this->policy);
    }

    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilTranslation');

        return new Environment($loader, ['strict_variables' => true]);
    }

    /**
     * @param array<string, string>       $fields
     * @param array<string, string>       $headers
     * @param array<string, UploadedFile> $files
     * @param array<string, string>       $cookies
     */
    private function post(array $fields, array $headers = ['Sec-Fetch-Site' => 'same-origin'], array $files = [], array $cookies = ['csrf-token_'.self::TOKEN => 'csrf-token']): Request
    {
        $request = Request::create('http://localhost/_t/x', 'POST', [SameOriginGuard::FIELD => self::TOKEN, ...$fields], $cookies, $files);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $this->requests->push($request);

        return $request;
    }

    private function upload(string $xml): Request
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'xlf');
        file_put_contents($path, $xml);
        $this->upload = $path;

        return $this->post([], files: ['xliff' => new UploadedFile($path, 'page.xlf', 'application/xliff+xml', null, true)]);
    }
}
