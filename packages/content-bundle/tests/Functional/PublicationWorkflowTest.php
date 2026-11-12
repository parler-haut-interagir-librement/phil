<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Application\Publication\PublicationWorkflow;
use Phil\ContentBundle\Infrastructure\Workflow\PublicationWorkflowPass;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Workflow\Registry;

/**
 * The publication workflow found in the application's registry, the way a scheduler applies it: by the subject,
 * without knowing the bundle.
 */
#[CoversClass(PublicationWorkflowPass::class)]
final class PublicationWorkflowTest extends FunctionalTestCase
{
    public function testTheRegistryPublishesAndTakesOfflineAContent(): void
    {
        $contents = $this->contents();
        $page = $contents->create('static_page', 'fr', ['slug' => 'a-propos', 'bodySource' => 'À propos']);
        $publication = Publication::of($page, 'fr');
        $registry = $this->service(Registry::class);

        self::assertTrue($registry->has($publication));
        $workflow = $registry->get($publication);
        self::assertSame(PublicationWorkflow::NAME, $workflow->getName());

        $workflow->apply($publication, 'publish');
        $this->entityManager()->clear();
        self::assertSame(200, $this->request('/fr/a-propos')->getStatusCode());

        $workflow->apply($publication, 'unpublish');
        $this->entityManager()->clear();
        self::assertSame(404, $this->request('/fr/a-propos')->getStatusCode());
    }
}
