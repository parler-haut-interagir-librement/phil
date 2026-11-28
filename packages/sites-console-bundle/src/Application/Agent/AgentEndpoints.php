<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Agent;

use Closure;
use Phil\Idempotency\Exception\IdempotencyException;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use Phil\SitesConsoleBundle\Application\Contract\ConfigurationApplier;
use Phil\SitesConsoleBundle\Application\Contract\InstalledPackages;
use Phil\SitesConsoleBundle\Application\Contract\SiteHealth;
use Phil\SitesConsoleBundle\Application\Contract\UpdateRunner;
use Phil\SitesConsoleBundle\Application\Exception\ActionUnavailable;
use Phil\SitesConsoleBundle\Application\Exception\ConfigurationRefused;
use Phil\SitesConsoleBundle\Application\Exception\KeyRefused;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Phil\SitesConsoleBundle\Domain\Status\KeyOperation;
use Phil\SitesConsoleBundle\Domain\ValueObject\ConfigurationRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\KeyRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateRequest;
use Phil\SitesConsoleBundle\Domain\Version\Packages;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Throwable;

/**
 * What the agent of a site does once a request is verified: report its versions and health, apply pushed settings
 * once per push, run an update once per step, stage or retire a console key once per step. A push, an update or a key
 * change delivered again gets the first outcome back, marked replayed; the idempotency comes from phil/idempotency.
 */
final readonly class AgentEndpoints
{
    public const string SCOPE_CONFIGURATION = 'phil_sites_console.configuration';

    public const string SCOPE_UPDATE = 'phil_sites_console.update';

    public const string SCOPE_KEYS = 'phil_sites_console.keys';

    /**
     * @param list<string> $packagePatterns the packages reported, such as `vendor/*`; every package when empty
     * @param list<string> $allowedKeys     the setting keys a push may carry, such as `mail.*`; none when empty
     */
    public function __construct(
        private string $siteId,
        private InstalledPackages $installed,
        private SiteHealth $health,
        private IdempotencyGuard $idempotency,
        private ClockInterface $clock,
        private array $packagePatterns = [],
        private array $allowedKeys = [],
        private ?ConfigurationApplier $applier = null,
        private ?UpdateRunner $runner = null,
        private ?LoggerInterface $logger = null,
        private ?ConsoleKeyRing $consoleKeys = null,
    ) {
    }

    public function status(): StatusDocument
    {
        $packages = [];

        foreach ($this->installed->versions() as $name => $version) {
            if ([] === $this->packagePatterns || Packages::matches($name, $this->packagePatterns)) {
                $packages[$name] = $version;
            }
        }

        ksort($packages, \SORT_STRING);

        return new StatusDocument($this->siteId, $this->clock->now(), \PHP_VERSION, Kernel::VERSION, $packages, $this->health->summary());
    }

    /**
     * @throws ActionUnavailable    without an applier
     * @throws ConfigurationRefused for a key the configuration does not allow
     * @throws MalformedDocument
     */
    public function configure(string $body, int $maxBytes): Outcome
    {
        $applier = $this->applier ?? throw ActionUnavailable::configuration();
        $request = ConfigurationRequest::fromJson($body, $maxBytes);
        $refused = array_values(array_filter($request->settings->keys(), fn (string $key): bool => !Packages::matches($key, $this->allowedKeys)));

        if ([] !== $refused) {
            throw ConfigurationRefused::keys($refused);
        }

        return $this->once(self::SCOPE_CONFIGURATION, $request->id, $body, static function () use ($applier, $request): Outcome {
            $applier->apply($request->settings);

            return Outcome::succeeded(\sprintf('%d settings applied.', \count($request->settings->values)));
        });
    }

    /**
     * @throws ActionUnavailable without a runner
     * @throws MalformedDocument
     */
    public function update(string $body, int $maxBytes): Outcome
    {
        $runner = $this->runner ?? throw ActionUnavailable::update();
        $request = UpdateRequest::fromJson($body, $maxBytes);

        return $this->once(self::SCOPE_UPDATE, $request->id, $body, static fn (): Outcome => $runner->run($request->package, $request->version));
    }

    /**
     * Stages a key as the next key of the console, by a request signed with the current key; or retires a key, by a
     * request signed with the key that remains. Retiring the current key makes the next one current; retiring the only
     * key is refused; a key already staged, already current or not held changes nothing and succeeds.
     *
     * @throws ActionUnavailable without a key ring
     * @throws KeyRefused        for a request signed by the wrong key, or one that would leave no key
     * @throws MalformedDocument
     */
    public function keys(string $body, VerifyingKey $signer, int $maxBytes): Outcome
    {
        $ring = $this->consoleKeys ?? throw ActionUnavailable::keys();
        $request = KeyRequest::fromJson($body, $maxBytes);
        $keys = $ring->keys();

        if (KeyOperation::Stage === $request->operation && !$keys->isCurrent($signer)) {
            throw KeyRefused::stageSignedByNext();
        }

        if (KeyOperation::Retire === $request->operation) {
            if ($keys->isCurrent($request->key) && !$keys->next instanceof VerifyingKey) {
                throw KeyRefused::lastKey();
            }

            if ($request->key->equals($signer)) {
                throw KeyRefused::retireSignedByRetired();
            }
        }

        $logger = $this->logger;

        return $this->once(self::SCOPE_KEYS, $request->id, $body, static function () use ($ring, $request, $logger): Outcome {
            $keys = $ring->keys();
            $held = $keys->isCurrent($request->key) || $keys->isNext($request->key);

            if (KeyOperation::Stage === $request->operation) {
                if ($held) {
                    return Outcome::succeeded($keys->isCurrent($request->key) ? 'The key is already the current key.' : 'The key is already staged.');
                }

                $ring->stage($request->key);
                $logger?->notice('The console staged its next key {key}.', ['key' => $request->key->toBase64()]);

                return Outcome::succeeded('The next key is staged.');
            }

            if (!$held) {
                return Outcome::succeeded('The key was not held.');
            }

            $ring->retire($request->key);
            $logger?->notice('The console retired its key {key}.', ['key' => $request->key->toBase64()]);

            return Outcome::succeeded('The key is retired.');
        });
    }

    /**
     * @param Closure(): Outcome $operation
     */
    private function once(string $scope, string $id, string $body, Closure $operation): Outcome
    {
        try {
            $run = $this->idempotency->run(
                IdempotencyScope::of($scope),
                IdempotencyKey::fromString($id),
                PayloadFingerprint::ofParts($scope, $body),
                static fn (): StoredResponse => StoredResponse::of(200, [], $operation()->toJson()),
            );
        } catch (Throwable $throwable) {
            if ($throwable instanceof IdempotencyException) {
                throw $throwable;
            }

            $this->logger?->error('The site could not perform the request {id} of the console.', ['id' => $id, 'exception' => $throwable]);

            return Outcome::failed('The site could not perform the request; see its logs.');
        }

        $outcome = Outcome::fromJson($run->response->body);

        return $run->replayed ? $outcome->replay() : $outcome;
    }
}
