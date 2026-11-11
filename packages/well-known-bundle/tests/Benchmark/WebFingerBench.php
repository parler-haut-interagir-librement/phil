<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Benchmark;

use Phil\WellKnownBundle\Application\StaticWebFingerResolver;
use Phil\WellKnownBundle\Application\WebFinger;
use Phil\WellKnownBundle\Infrastructure\Http\Document\StaticDocument;
use Phil\WellKnownBundle\Infrastructure\Http\Document\WebFingerDocument;
use Phil\WellKnownBundle\Infrastructure\Http\WellKnownController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;

/**
 * WebFinger is the document other servers ask for most, once per account they meet: the query read from the raw query
 * string, the resolvers asked, the descriptor written. Beside it, a fixed document served through the controller, with
 * its cache headers and its ETag.
 *
 * The resolver holds 1,000 accounts of the configuration, built once, as a container does in a worker.
 *
 * @Revs(1000)
 *
 * @Iterations(5)
 */
final readonly class WebFingerBench
{
    private WellKnownController $controller;

    public function __construct()
    {
        $resources = [];

        for ($index = 0; $index < 1_000; ++$index) {
            $resources[\sprintf('acct:user%d@example.com', $index)] = ['subject' => null, 'aliases' => [\sprintf('https://example.com/@user%d', $index)], 'properties' => [], 'links' => [
                ['rel' => 'self', 'type' => 'application/activity+json', 'href' => \sprintf('https://example.com/users/user%d', $index), 'template' => null, 'titles' => [], 'properties' => []],
                ['rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => \sprintf('https://example.com/@user%d', $index), 'template' => null, 'titles' => [], 'properties' => []],
            ]];
        }

        $webFinger = new WebFingerDocument(new WebFinger([new StaticWebFingerResolver($resources)]));
        $static = new StaticDocument('{"applinks":{"details":[{"appIDs":["ABCDE12345.com.example.app"],"components":[{"/":"/events/*"}]}]}}', 'application/json');

        $this->controller = new WellKnownController(new ServiceLocator(['webfinger' => static fn (): WebFingerDocument => $webFinger, 'static' => static fn (): StaticDocument => $static]), 3600);
    }

    public function benchAnAccount(): void
    {
        ($this->controller)($this->request('webfinger', '/.well-known/webfinger?resource=acct%3Auser500%40example.com&rel=self'));
    }

    public function benchAnUnknownAccount(): void
    {
        ($this->controller)($this->request('webfinger', '/.well-known/webfinger?resource=acct%3Anobody%40example.com'));
    }

    public function benchAFixedDocument(): void
    {
        ($this->controller)($this->request('static', '/.well-known/apple-app-site-association'));
    }

    private function request(string $document, string $uri): Request
    {
        $request = Request::create('https://example.com'.$uri);
        $request->attributes->set(WellKnownController::DOCUMENT, $document);

        return $request;
    }
}
