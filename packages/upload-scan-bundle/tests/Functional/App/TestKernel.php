<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Functional\App;

use Override;
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFile;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle with the validator and CSRF protection, TwigBundle,
 * Symfony Security with an administrator and a reader signing in by HTTP Basic, an upload page that validates its file
 * with `#[CleanFile]`, and the quarantine's administration under `/admin/quarantine`. The scanner is clamd at the
 * address of `PHIL_UPLOAD_SCAN_CLAMD` when the variable names one, the fake scanner otherwise.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        string $environment,
        bool $debug,
        private readonly ?string $clamd = null,
    ) {
        parent::__construct($environment, $debug);
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    public function quarantineDirectory(): string
    {
        return $this->root().'/quarantine';
    }

    /**
     * Validates the file sent as `file` against `#[CleanFile]`: 201 when it is clean, 422 with the messages otherwise.
     */
    public function upload(Request $request, ValidatorInterface $validator): Response
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return new Response('No file.', Response::HTTP_BAD_REQUEST);
        }

        $violations = $validator->validate($file, new CleanFile(context: 'manuscript'));

        if (0 === \count($violations)) {
            return new Response('Accepted.', Response::HTTP_CREATED);
        }

        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return new Response(implode("\n", $messages), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function root(): string
    {
        return sys_get_temp_dir().'/phil-upload-scan-bundle-test/'.hash('xxh128', __DIR__.($this->clamd ?? 'fake'));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
            'csrf_protection' => true,
            'validation' => ['enabled' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $container->services()->set(self::class)->synthetic()->public();

        $container->extension('twig', ['strict_variables' => true]);

        $container->extension('security', [
            'password_hashers' => [InMemoryUser::class => 'plaintext'],
            'providers' => ['people' => ['memory' => ['users' => [
                'admin' => ['password' => 'admin', 'roles' => ['ROLE_ADMIN']],
                'reader' => ['password' => 'reader', 'roles' => ['ROLE_USER']],
            ]]]],
            'firewalls' => ['main' => ['http_basic' => null, 'provider' => 'people']],
        ]);

        $container->extension('phil_upload_scan', [
            'scanner' => null === $this->clamd ? 'fake' : 'clamd',
            'clamd' => ['address' => $this->clamd ?? 'tcp://127.0.0.1:3310', 'timeout' => 10.0],
            'quarantine' => ['directory' => $this->quarantineDirectory()],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@PhilUploadScanBundle/config/admin_routes.php')->prefix('/admin/quarantine');
        $routes->add('upload', '/upload')->controller([self::class, 'upload'])->methods(['POST']);
    }
}
