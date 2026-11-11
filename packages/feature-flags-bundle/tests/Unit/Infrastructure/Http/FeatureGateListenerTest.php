<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Infrastructure\Http;

use ErrorException;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Infrastructure\Http\FeatureGateListener;
use Phil\FeatureFlagsBundle\Infrastructure\Http\IsFeatureEnabled;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CountingResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(FeatureGateListener::class)]
final class FeatureGateListenerTest extends TestCase
{
    /**
     * A flag without an experiment has no variant: a controller behind one of its variants is not found, without
     * a warning.
     */
    public function testAVariantOfAFlagWithoutAnExperimentIsNotFound(): void
    {
        $listener = new FeatureGateListener(new FlagEvaluator(
            [ConfiguredFlagProvider::fromConfiguration(['everyone' => ['active' => true]])],
            new CountingResolver(EvaluationContext::anonymous()),
        ));
        $controller = new class {
            #[IsFeatureEnabled('everyone', variant: 'a')]
            public function __invoke(): Response
            {
                return new Response();
            }
        };
        $event = new ControllerEvent(self::createStub(HttpKernelInterface::class), $controller, Request::create('/'), HttpKernelInterface::MAIN_REQUEST);
        set_error_handler(static function (int $level, string $message): never {
            throw new ErrorException($message, 0, $level);
        });

        try {
            $listener->onKernelController($event);
            self::fail('The controller was reached.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame('', $notFoundHttpException->getMessage());
        } finally {
            restore_error_handler();
        }
    }
}
