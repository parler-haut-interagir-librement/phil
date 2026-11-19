<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Functional\App;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A BigBlueButton server played by the test, behind a `MockHttpClient`: the XML it answers to each call by name, and
 * every address it was called at.
 */
final class ScriptedServer implements ResetInterface
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, string> */
    public array $answers = [];

    public function __invoke(string $method, string $url): MockResponse
    {
        $this->calls[] = $url;
        $call = (string) preg_replace('#^.*/api/([A-Za-z]+)\?.*$#', '$1', $url);

        return new MockResponse($this->answers[$call] ?? '<response><returncode>SUCCESS</returncode><createTime>1700000000123</createTime></response>');
    }

    public function reset(): void
    {
        $this->calls = [];
        $this->answers = [];
    }
}
