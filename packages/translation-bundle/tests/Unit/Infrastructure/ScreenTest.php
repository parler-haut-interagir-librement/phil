<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Infrastructure;

use Phil\TranslationBundle\Infrastructure\Http\SameOriginGuard;
use Phil\TranslationBundle\Infrastructure\Http\TranslationController;
use Phil\TranslationBundle\PhilTranslationBundle;
use Phil\TranslationBundle\Tests\Unit\Fixture\Machine;
use Phil\TranslationBundle\Tests\Unit\Fixture\Policy;
use Phil\TranslationBundle\Tests\Unit\Fixture\Router;
use Phil\TranslationBundle\Tests\Unit\Fixture\Sources;
use Phil\TranslationBundle\Tests\Unit\Fixture\Stores;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

#[CoversClass(TranslationController::class)]
final class ScreenTest extends TestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private Policy $policy;

    private RequestStack $requests;

    private SameOriginGuard $guard;

    protected function setUp(): void
    {
        $this->policy = new Policy();
        $this->requests = new RequestStack();
        $this->guard = new SameOriginGuard(new SameOriginCsrfTokenManager($this->requests, null, null, [SameOriginGuard::TOKEN_ID]));
    }

    public function testShowsTheChecksAndDismissesOne(): void
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', new Sources()->documents['page:1']->content, LanguageTag::of('en'), LanguageTag::of('fr'));
        $stores->translations->translate('page:1', LanguageTag::of('fr'), 'p1/text', 'Hello world.');

        $html = $this->html($stores);

        self::assertStringContainsString('The target is the same as the source.', $html);
        self::assertStringContainsString('<input type="hidden" name="check" value="same">', $html);
        self::assertStringContainsString('<button type="submit" name="action" value="dismiss">Dismiss “same”</button>', $html);
        self::assertStringNotContainsString('<p class="flags">', $html);

        $response = $this->controller($stores)->save($this->post(['key' => 'p1/text', 'action' => 'dismiss', 'check' => 'same']), 'page:1', 'fr');
        self::assertSame('/_t/phil_translation_page?document=page%3A1&language=fr&done=checked#segment-'.hash('xxh3', 'p1/text'), $response->headers->get('Location'));

        $html = $this->html($stores, new Request(['done' => 'checked']));
        self::assertStringNotContainsString('The target is the same as the source.', $html);
        self::assertStringContainsString('<p class="flags">Flags: <code>ignore-same</code></p>', $html);
        self::assertStringContainsString('<p class="done" role="status">Segment checked.</p>', $html);
    }

    public function testShowsWhyACheckCannotBeDismissed(): void
    {
        $stores = new Stores();
        $response = $this->controller($stores)->save($this->post(['key' => 'p1/text', 'action' => 'dismiss', 'check' => 'Not one']), 'page:1', 'fr');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('&quot;ignore-Not one&quot; is not a flag', (string) $response->getContent());
    }

    public function testShowsTheTargetAVariantInherits(): void
    {
        $stores = new Stores();
        $content = new Sources()->documents['page:1']->content;
        $stores->translations->prepare('page:1', $content, LanguageTag::of('en'), LanguageTag::of('fr'));
        $stores->translations->translate('page:1', LanguageTag::of('fr'), 'p1/text', 'Bonjour le monde.');
        $stores->translations->suggest('page:1', LanguageTag::of('fr'), 'p2/text', 'La porte rouge.');

        $html = $this->html($stores, language: 'fr-CA');

        self::assertStringContainsString('<p class="variant">fr-CA inherits from fr: a segment without a target of its own is published as in fr.</p>', $html);
        self::assertSame(1, substr_count($html, '<p class="inherited">'));
        self::assertStringContainsString('<p class="inherited">From fr: <code>Bonjour le monde.</code></p>', $html);

        $stores->translations->prepare('page:1', $content, LanguageTag::of('en'), LanguageTag::of('fr-CA'));
        $stores->translations->translate('page:1', LanguageTag::of('fr-CA'), 'p1/text', 'Allô le monde.');
        self::assertStringNotContainsString('<p class="inherited">', $this->html($stores, language: 'fr-CA'));

        $stores->translations->prepare('page:1', Sources::page('Hello world!', 'The <b>red</b> door.'), LanguageTag::of('en'), LanguageTag::of('fr'));
        $stores->translations->clear('page:1', LanguageTag::of('fr-CA'), 'p1/text');
        self::assertStringNotContainsString('<p class="inherited">', $this->html($stores, language: 'fr-CA'), 'a parent target of another source is not inherited');

        $html = $this->html($stores);
        self::assertStringNotContainsString('<p class="variant">', $html);
        self::assertStringNotContainsString('<p class="inherited">', $html);
    }

    public function testCommentsASegmentAndRemovesAComment(): void
    {
        $stores = new Stores();
        $stores->translators->current = 'ana';

        $anchor = '#segment-'.hash('xxh3', 'p2/text');

        $response = $this->controller($stores)->comment($this->post(['key' => 'p2/text', 'action' => 'add', 'body' => "Why\n“vous”?"]), 'page:1', 'fr');
        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertSame('/_t/phil_translation_page?document=page%3A1&language=fr&done=commented'.$anchor, $response->headers->get('Location'));

        $html = $this->html($stores, new Request(['done' => 'commented']));
        self::assertStringContainsString('<p class="done" role="status">Comment added.</p>', $html);
        self::assertStringContainsString("<p>Why<br />\n“vous”?</p>", $html);
        self::assertStringContainsString('<small>ana, 2026-11-06 10:00</small>', $html);
        self::assertStringContainsString('<input type="hidden" name="comment" value="1">', $html);

        $this->policy->review = false;
        self::assertStringNotContainsString('name="comment"', $this->html($stores));

        try {
            $this->controller($stores)->comment($this->post(['key' => 'p2/text', 'action' => 'remove', 'comment' => '1']), 'page:1', 'fr');
            self::fail('Only a reviewer removes a comment.');
        } catch (AccessDeniedHttpException $accessDeniedHttpException) {
            self::assertSame('Removing a comment is not allowed here.', $accessDeniedHttpException->getMessage());
        }

        $this->policy->review = true;
        $response = $this->controller($stores)->comment($this->post(['key' => 'p2/text', 'action' => 'remove', 'comment' => '1']), 'page:1', 'fr');
        self::assertSame('/_t/phil_translation_page?document=page%3A1&language=fr&done=uncommented'.$anchor, $response->headers->get('Location'));
        self::assertSame([], $stores->comments->forDocument('page:1', LanguageTag::of('fr')));
        self::assertStringContainsString('<p class="done" role="status">Comment removed.</p>', $this->html($stores, new Request(['done' => 'uncommented'])));
        self::assertStringContainsString('<small>Someone, ', $this->html($this->commented(), new Request()));
    }

    public function testRefusesACommentOnASegmentTheDocumentDoesNotHave(): void
    {
        $stores = new Stores();

        try {
            $this->controller($stores)->comment($this->post(['key' => 'p9/text', 'body' => 'Hm.']), 'page:1', 'fr');
            self::fail('An unknown segment is refused.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame('Document "page:1" has no segment "p9/text" in "fr".', $notFoundHttpException->getMessage());
        }

        self::assertSame([], $stores->comments->forDocument('page:1', LanguageTag::of('fr')));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('There is no comment 4.');
        $this->controller($stores)->comment($this->post(['key' => 'p1/text', 'action' => 'remove', 'comment' => '4']), 'page:1', 'fr');
    }

    public function testShowsWhyACommentIsRefused(): void
    {
        $response = $this->controller(new Stores())->comment($this->post(['key' => 'p1/text', 'body' => '  ']), 'page:1', 'fr');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertStringContainsString('A comment is 1 to 4000 characters of UTF-8.', (string) $response->getContent());
    }

    public function testACommentNeedsTheToken(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIsOrContains('The form was not posted from this site.');

        $this->controller(new Stores())->comment($this->post(['key' => 'p1/text', 'body' => 'Hm.'], ['Sec-Fetch-Site' => 'cross-site']), 'page:1', 'fr');
    }

    public function testProposesMachineTranslations(): void
    {
        $stores = new Stores(machine: Machine::shouting());
        $html = $this->html($stores);
        self::assertStringContainsString('<button type="submit">Propose machine translations for untranslated segments</button>', $html);
        self::assertStringContainsString('action="/_t/phil_translation_machine?document=page%3A1&amp;language=fr"', $html);

        $response = $this->controller($stores)->machine($this->post([]), 'page:1', 'fr');
        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertSame('/_t/phil_translation_page?document=page%3A1&language=fr&done=machine-translated&count=2', $response->headers->get('Location'));
        self::assertSame(['HELLO WORLD.', 'THE <B>RED</B> DOOR.'], array_map(static fn (Segment $segment): ?string => $segment->target, $stores->translations->segments('page:1', LanguageTag::of('fr'))));

        $html = $this->html($stores, new Request(['done' => 'machine-translated', 'count' => '2']));
        self::assertStringContainsString('<p class="done" role="status">2 segments proposed by machine translation.</p>', $html);
        self::assertSame(2, substr_count($html, '<span class="machine">machine translation</span>'));
        self::assertStringContainsString('<p class="done" role="status">1 segment proposed by machine translation.</p>', $this->html($stores, new Request(['done' => 'machine-translated', 'count' => '1'])));
        self::assertStringContainsString('<p class="done" role="status">0 segments proposed by machine translation.</p>', $this->html($stores, new Request(['done' => 'machine-translated', 'count' => '-3'])));

        $stores->translations->translate('page:1', LanguageTag::of('fr'), 'p1/text', 'Bonjour.');
        self::assertSame(1, substr_count($this->html($stores), '<span class="machine">machine translation</span>'));
        $stores->translations->clear('page:1', LanguageTag::of('fr'), 'p2/text');
        $stores->translations->records('page:1', LanguageTag::of('fr'))['p2/text']->attribute(null, null, true);
        self::assertSame(0, substr_count($this->html($stores), '<span class="machine">machine translation</span>'), 'no target, no mark');
    }

    public function testMachineTranslationIsOffUnlessConfigured(): void
    {
        $stores = new Stores();
        self::assertStringNotContainsString('Propose machine translations', $this->html($stores));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('Machine translation is off.');
        $this->controller($stores)->machine($this->post([]), 'page:1', 'fr');
    }

    public function testKeepsWhatTheMachineTranslatorAnsweredOutOfThePage(): void
    {
        $stores = new Stores(machine: new Machine(static fn (array $sources): array => throw new RuntimeException('quota exceeded')));
        $response = $this->controller($stores)->machine($this->post([]), 'page:1', 'fr');

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertStringContainsString('Machine translation failed: nothing was proposed. Try again later.', (string) $response->getContent());
        self::assertStringNotContainsString('quota exceeded', (string) $response->getContent(), 'what the service answered stays out of the page');
        self::assertStringNotContainsString('test-machine', (string) $response->getContent());
    }

    public function testMachineTranslationNeedsTheToken(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageIsOrContains('The form was not posted from this site.');

        $this->controller(new Stores(machine: Machine::shouting()))->machine($this->post([], ['Origin' => 'https://evil.example']), 'page:1', 'fr');
    }

    public function testMachineTranslationNeedsATranslator(): void
    {
        $this->policy->translate = false;

        $this->expectException(AccessDeniedHttpException::class);
        $this->controller(new Stores(machine: Machine::shouting()))->machine($this->post([]), 'page:1', 'fr');
    }

    private function commented(): Stores
    {
        $stores = new Stores();
        $stores->comments->add('page:1', LanguageTag::of('fr'), 'p1/text', 'Anonymous.');

        return $stores;
    }

    private function html(Stores $stores, Request $request = new Request(), string $language = 'fr'): string
    {
        return (string) $this->controller($stores)->page($request, 'page:1', $language)->getContent();
    }

    private function controller(Stores $stores): TranslationController
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilTranslation');

        return new TranslationController($stores->translations, $stores->comments, new Environment($loader, ['strict_variables' => true]), new Router(), $this->guard, PhilTranslationBundle::DEFAULT_LAYOUT, new Sources(), $this->policy);
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $headers
     */
    private function post(array $fields, array $headers = ['Sec-Fetch-Site' => 'same-origin']): Request
    {
        $request = Request::create('http://localhost/_t/x', 'POST', [SameOriginGuard::FIELD => self::TOKEN, ...$fields], ['csrf-token_'.self::TOKEN => 'csrf-token']);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $this->requests->push($request);

        return $request;
    }
}
