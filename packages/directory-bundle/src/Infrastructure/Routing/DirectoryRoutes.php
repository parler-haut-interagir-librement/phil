<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Routing;

use Override;
use Phil\DirectoryBundle\Application\Genre;
use Phil\DirectoryBundle\Application\Genres;
use Phil\DirectoryBundle\Infrastructure\Http\DirectoryController;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes of every genre, loaded by `type: phil_directory`: `/{path}` lists the genre, `/{path}/{slug}` shows a
 * listing, and `/{path}/{new}`, `/{path}/{id}/{edit}`, `/{path}/{id}/{editors}`, `/{path}/{id}/{history}` and
 * `/{path}/{id}/{publish}` write it, each word as the configuration names it.
 */
final class DirectoryRoutes implements LoaderInterface
{
    public const string TYPE = 'phil_directory';

    private const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    private ?LoaderResolverInterface $resolver = null;

    /**
     * @param array{new: string, edit: string, editors: string, history: string, publish: string} $words
     */
    public function __construct(
        private readonly Genres $genres,
        private readonly array $words,
    ) {
    }

    public static function name(string $genre, string $action): string
    {
        return 'phil_directory_'.$genre.'_'.$action;
    }

    #[Override]
    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();

        foreach ($this->genres->all() as $genre) {
            $path = $genre->path;
            $this->add($routes, $genre, 'index', $path, ['GET']);
            $this->add($routes, $genre, 'new', $path.'/'.$this->words['new'], ['GET', 'POST']);
            $this->add($routes, $genre, 'show', $path.'/{slug}', ['GET'], ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*']);

            foreach (['edit' => ['GET', 'POST'], 'editors' => ['GET', 'POST'], 'history' => ['GET'], 'publish' => ['POST']] as $action => $methods) {
                $this->add($routes, $genre, $action, $path.'/{id}/'.$this->words[$action], $methods, ['id' => self::UUID]);
            }
        }

        return $routes;
    }

    /**
     * @param list<string>          $methods
     * @param array<string, string> $requirements
     */
    private function add(RouteCollection $routes, Genre $genre, string $action, string $url, array $methods, array $requirements = []): void
    {
        $routes->add(self::name($genre->key, $action), new Route($url, ['_controller' => [DirectoryController::class, $action], 'genre' => $genre->key], $requirements, [], '', [], $methods));
    }

    #[Override]
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }

    #[Override]
    public function getResolver(): LoaderResolverInterface
    {
        return $this->resolver ?? new LoaderResolver([$this]);
    }

    #[Override]
    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
    }
}
