<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Agent;

use Phil\SitesConsoleBundle\Application\Contract\ConsoleKeyStore;
use Phil\SitesConsoleBundle\Application\Exception\StateNotKept;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Signature\ConsoleKeys;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Psr\Log\LoggerInterface;

/**
 * The console keys an agent accepts. The configuration gives the current key and, when set, the next one; a rotation
 * of the console stages and retires keys in the store. What the store holds counts only while the configuration is
 * the one it started from: once the keys of the configuration change, they win again, so that a deploy always
 * recovers a site.
 *
 * A change is read back once written: a store that did not keep it fails the rotation, so that a retired key is never
 * reported retired while the site still trusts it.
 */
final readonly class ConsoleKeyRing
{
    private ConsoleKeys $configured;

    private string $fingerprint;

    public function __construct(
        VerifyingKey $current,
        ?VerifyingKey $next,
        private ConsoleKeyStore $store,
        private ?LoggerInterface $logger = null,
    ) {
        $this->configured = new ConsoleKeys($current, $next);
        $this->fingerprint = hash('sha256', $this->configured->toJson());
    }

    public function keys(): ConsoleKeys
    {
        $state = $this->store->read();

        if (null === $state) {
            return $this->configured;
        }

        [$fingerprint, $keys] = explode("\n", $state, 2) + [1 => ''];

        if ($fingerprint !== $this->fingerprint) {
            return $this->configured;
        }

        try {
            return ConsoleKeys::fromJson($keys);
        } catch (MalformedDocument $malformedDocument) {
            $this->logger?->warning('The stored console keys are unreadable; the configured keys apply: {reason}', ['reason' => $malformedDocument->getMessage()]);

            return $this->configured;
        }
    }

    /**
     * Accepts the key as the next key of the console, in place of any other next key.
     *
     * @throws StateNotKept when the store did not keep the change
     */
    public function stage(VerifyingKey $key): ConsoleKeys
    {
        return $this->write($this->keys()->staging($key));
    }

    /**
     * Stops accepting the key; the next key becomes the current one when the current one is retired.
     *
     * @throws InvalidValue when the key is the only one accepted
     * @throws StateNotKept when the store did not keep the change
     */
    public function retire(VerifyingKey $key): ConsoleKeys
    {
        return $this->write($this->keys()->retiring($key));
    }

    private function write(ConsoleKeys $keys): ConsoleKeys
    {
        $state = $this->fingerprint."\n".$keys->toJson();
        $this->store->write($state);

        if ($this->store->read() !== $state) {
            throw StateNotKept::consoleKeys();
        }

        return $keys;
    }
}
