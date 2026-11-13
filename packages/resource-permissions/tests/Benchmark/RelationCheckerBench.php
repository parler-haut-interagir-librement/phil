<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Benchmark;

use Generator;
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Symfony\Component\Clock\MockClock;

/**
 * A check runs on every item a page shows, so its cost must grow with the depth of the tree and of the groups, never
 * with the number of tuples. A document sits at the bottom of a chain of folders, each with a few direct viewers, and
 * the member reaches the root through nested groups; the listing gathers the documents of a member.
 *
 * @BeforeMethods("setUp")
 */
final class RelationCheckerBench
{
    private ?RelationChecker $checker = null;

    private readonly ResourceReference $member;

    private readonly ResourceReference $document;

    public function __construct()
    {
        $this->member = ResourceReference::of('member', 'ann');
        $this->document = ResourceReference::of('doc', 'leaf');
    }

    /**
     * @param array{depth: int} $params
     */
    public function setUp(array $params): void
    {
        $model = new Model(
            TypeDefinition::of('member'),
            TypeDefinition::of('group', ['member' => Rewrite::direct('member', 'group#member')]),
            TypeDefinition::of('folder', [
                'parent' => Rewrite::direct('folder'),
                'viewer' => Rewrite::union(Rewrite::direct('member', 'group#member'), Rewrite::fromRelated('parent', 'viewer')),
            ]),
            TypeDefinition::of('doc', [
                'parent' => Rewrite::direct('folder'),
                'viewer' => Rewrite::union(Rewrite::direct('member'), Rewrite::fromRelated('parent', 'viewer')),
            ]),
        );
        $tuples = [new RelationTuple($this->document, 'parent', Subject::of(ResourceReference::of('folder', 'f'.$params['depth'])))];

        for ($level = $params['depth']; $level > 0; --$level) {
            $folder = ResourceReference::of('folder', 'f'.$level);

            if ($level > 1) {
                $tuples[] = new RelationTuple($folder, 'parent', Subject::of(ResourceReference::of('folder', 'f'.($level - 1))));
            }

            foreach (range(1, 5) as $other) {
                $tuples[] = new RelationTuple($folder, 'viewer', Subject::of(ResourceReference::of('member', 'm'.$level.'-'.$other)));
            }
        }

        $tuples[] = new RelationTuple(ResourceReference::of('folder', 'f1'), 'viewer', Subject::set(ResourceReference::of('group', 'outer'), 'member'));
        $tuples[] = new RelationTuple(ResourceReference::of('group', 'outer'), 'member', Subject::set(ResourceReference::of('group', 'inner'), 'member'));
        $tuples[] = new RelationTuple(ResourceReference::of('group', 'inner'), 'member', Subject::of($this->member));

        foreach (range(1, 200) as $i) {
            $tuples[] = new RelationTuple(ResourceReference::of('doc', 'd'.$i), 'parent', Subject::of(ResourceReference::of('folder', 'f'.(1 + $i % $params['depth']))));
        }

        $store = new InMemoryTupleStore(new MockClock());
        $store->update([], $tuples);

        $this->checker = new RelationChecker($model, $store);
    }

    /**
     * @return Generator<string, array{depth: int}>
     */
    public function depths(): Generator
    {
        foreach ([4, 8, 16] as $depth) {
            yield $depth.' folders' => ['depth' => $depth];
        }
    }

    /**
     * @ParamProviders("depths")
     *
     * @Revs(200)
     *
     * @Iterations(5)
     */
    public function benchCheck(): void
    {
        $this->checker?->check($this->member, 'viewer', $this->document);
    }

    /**
     * @ParamProviders("depths")
     *
     * @Revs(10)
     *
     * @Iterations(5)
     */
    public function benchList(): void
    {
        $this->checker?->objects($this->member, 'viewer', 'doc', 100);
    }
}
