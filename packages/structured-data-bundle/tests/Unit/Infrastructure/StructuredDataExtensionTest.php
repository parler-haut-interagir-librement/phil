<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Unit\Infrastructure;

use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\IndexingConsent\Contract\HasIndexingConsent;
use Phil\IndexingConsent\Indexing;
use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\Contract\ScriptNonceProvider;
use Phil\StructuredDataBundle\Application\PageStructuredData;
use Phil\StructuredDataBundle\Infrastructure\Http\ContentSecurityPolicyNonce;
use Phil\StructuredDataBundle\Infrastructure\Http\RequestAttributeNonce;
use Phil\StructuredDataBundle\Infrastructure\Twig\StructuredDataExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(StructuredDataExtension::class)]
#[CoversClass(RequestAttributeNonce::class)]
#[CoversClass(ContentSecurityPolicyNonce::class)]
final class StructuredDataExtensionTest extends TestCase
{
    public function testTemplatesAddNodesAndPrintThemOnce(): void
    {
        $page = new PageStructuredData();
        $extension = new StructuredDataExtension($page);

        self::assertSame('', $extension->render());
        self::assertSame('', $extension->add(Node::of('Thing', ['name' => 'a']), ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => 'Ada </script>']));
        self::assertSame('<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"Thing","name":"a"},{"@type":"Person","name":"Ada \u003C/script\u003E"}]}</script>', $extension->render());
        self::assertStringStartsWith('<script type="application/ld+json" nonce="abc">', $extension->render('abc'));
    }

    public function testAnEmptyGraphAsksForNoNonce(): void
    {
        $nonces = new class implements ScriptNonceProvider {
            public int $asked = 0;

            public function scriptNonce(): string
            {
                ++$this->asked;

                return 'abc';
            }
        };

        self::assertSame('', new StructuredDataExtension(new PageStructuredData(), [$nonces])->render());
        self::assertSame(0, $nonces->asked);
    }

    public function testTheNonceComesFromTheFirstProviderThatHasOne(): void
    {
        $page = new PageStructuredData();
        $page->add(Node::of('Thing'));

        $none = new class implements ScriptNonceProvider {
            public function scriptNonce(): ?string
            {
                return null;
            }
        };
        $request = Request::create('https://example.com/');
        $requests = new RequestStack([$request]);
        $request->attributes->set('script_nonce', 'bm9uY2U=');
        $other = new class implements ScriptNonceProvider {
            public function scriptNonce(): string
            {
                return 'b3RoZXI=';
            }
        };

        self::assertStringStartsWith('<script type="application/ld+json">', new StructuredDataExtension($page, [$none])->render());
        self::assertStringStartsWith('<script type="application/ld+json" nonce="bm9uY2U=">', new StructuredDataExtension($page, [$none, new RequestAttributeNonce($requests, 'script_nonce'), $other])->render());
        self::assertStringStartsWith('<script type="application/ld+json" nonce="given">', new StructuredDataExtension($page, [$other])->render('given'));

        $request->attributes->set('script_nonce', '');
        self::assertNull(new RequestAttributeNonce($requests, 'script_nonce')->scriptNonce());
        $request->attributes->set('script_nonce', 42);
        self::assertNull(new RequestAttributeNonce($requests, 'script_nonce')->scriptNonce());
        self::assertNull(new RequestAttributeNonce(new RequestStack(), 'script_nonce')->scriptNonce());
    }

    public function testAPageThatShowsAWithdrawnRecordPrintsNothingAndAsksForNoNonce(): void
    {
        $nonces = new class implements ScriptNonceProvider {
            public int $asked = 0;

            public function scriptNonce(): string
            {
                ++$this->asked;

                return 'abc';
            }
        };
        $record = new class implements HasIndexingConsent {
            public Indexing $choice = Indexing::Listed;

            public function indexing(): Indexing
            {
                return $this->choice;
            }
        };
        $page = new PageStructuredData();
        $page->add(Node::of('Person', ['name' => 'Ada']));

        $extension = new StructuredDataExtension($page, [$nonces]);

        self::assertSame('', $extension->about($record));
        self::assertStringStartsWith('<script type="application/ld+json" nonce="abc">', $extension->render());
        $record->choice = Indexing::NotIndexed;
        self::assertSame('', $extension->about($record));
        self::assertSame('', $extension->render());
        self::assertSame('', $extension->render('given'));
        self::assertSame(1, $nonces->asked);
    }

    public function testTheNonceOfTheContentSecurityPolicyIsTheOneItSends(): void
    {
        $request = Request::create('https://example.com/');
        $policy = new ContentSecurityPolicyContext(new RequestStack([$request]));
        $nonce = new ContentSecurityPolicyNonce($policy)->scriptNonce();

        self::assertSame($policy->nonce(InlineKind::Script), $nonce);
        self::assertSame(['script' => $nonce], RequestState::of($request)->nonces());
    }
}
