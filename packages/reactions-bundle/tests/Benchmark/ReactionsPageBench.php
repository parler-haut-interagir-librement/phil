<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Benchmark;

use DateTimeImmutable;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\ReactionsBundle\Tests\Fixtures\Stage;
use Phil\ReactionsBundle\Tests\Functional\App\TestKernel;
use Phil\Resource\ResourceReference;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;

/**
 * The cost of the reactions of a page of 20 contents: read in one batch, and drawn by the whole component through a
 * booted kernel reset between two requests, as a worker draws them request after request.
 *
 * @Groups({"reactions"})
 */
final readonly class ReactionsPageBench
{
    private Stage $stage;

    /** @var list<ResourceReference> */
    private array $contents;

    private Environment $twig;

    private ResetInterface $resetter;

    public function __construct()
    {
        $this->stage = new Stage();
        $contents = [];

        for ($id = 1; $id <= 20; ++$id) {
            $contents[] = ResourceReference::of('article', (string) $id);

            for ($member = 1; $member <= 50; ++$member) {
                $this->stage->repository->add(Reaction::of($id.'-'.$member, $contents[$id - 1], 'm'.$member, 0 === $member % 2 ? 'like' : 'thanks', new DateTimeImmutable('2026-05-01')));
            }
        }

        $this->contents = $contents;

        $kernel = new TestKernel('test', false);
        $kernel->boot();
        $kernel->resetDatabase();

        $container = $kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        $requests = $container->get('request_stack');
        \assert($requests instanceof RequestStack);
        $request = Request::create('/article');
        $request->headers->set('X-Member', 'm1');

        $requests->push($request);
        $repository = $container->get(ReactionRepository::class);
        \assert($repository instanceof ReactionRepository);
        $repository->add(Reaction::of('seed', $contents[0], 'm2', 'like', new DateTimeImmutable('2026-05-01')));
        $twig = $container->get('twig');
        \assert($twig instanceof Environment);
        $this->twig = $twig;
        $resetter = $container->get('services_resetter');
        \assert($resetter instanceof ResetInterface);
        $this->resetter = $resetter;
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     */
    public function benchPanelsOfAPage(): void
    {
        $this->stage->board->reset();
        $this->stage->board->preload($this->contents);

        foreach ($this->contents as $content) {
            $this->stage->board->panel($content);
        }
    }

    /**
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchRenderOfAPageInAWorker(): void
    {
        $this->twig->render('page.html.twig');
        $this->resetter->reset();
    }
}
