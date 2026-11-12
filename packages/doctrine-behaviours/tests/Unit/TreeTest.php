<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Override;
use Phil\DoctrineBehaviours\Branch;
use Phil\DoctrineBehaviours\Contract\TreeNode;
use Phil\DoctrineBehaviours\InvalidTree;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Folder;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Note;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Topic;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\MutableBlame;
use Phil\DoctrineBehaviours\Tree;
use Phil\DoctrineBehaviours\TreeListener;
use Phil\DoctrineBehaviours\TreeNodeEntity;
use Phil\DoctrineBehaviours\TreePaths;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(TreeListener::class)]
#[CoversClass(Tree::class)]
#[CoversClass(TreePaths::class)]
#[CoversClass(Branch::class)]
#[CoversClass(InvalidTree::class)]
#[CoversTrait(TreeNodeEntity::class)]
final class TreeTest extends TestCase
{
    private EntityManager $manager;

    private Tree $tree;

    #[Override]
    protected function setUp(): void
    {
        $this->manager = Database::entityManager(new MockClock(new DateTimeImmutable('2026-11-04 12:00 UTC')), new MutableBlame());
        $this->tree = new Tree($this->manager);
    }

    public function testNodesArePlacedUnderTheirParent(): void
    {
        $games = new Folder('Jeux');
        $board = new Folder('Société', $games);
        $chess = new Folder('Échecs', $board);
        $books = new Folder('Livres');
        $cards = new Folder('Cartes', $games);
        array_map($this->manager->persist(...), [$games, $board, $chess, $books, $cards]);
        $this->manager->flush();
        self::assertSame(['/1/2/3/', 2], [$this->manager->getUnitOfWork()->getOriginalEntityData($chess)['treePath'] ?? null, $this->manager->getUnitOfWork()->getOriginalEntityData($chess)['treeDepth'] ?? null]);

        self::assertSame(['/1/', 0, '/1/2/', 1, '/1/2/3/', 2, '/4/', 0], [$games->treePath(), $games->treeDepth(), $board->treePath(), $board->treeDepth(), $chess->treePath(), $chess->treeDepth(), $books->treePath(), $books->treeDepth()]);
        $this->manager->clear();

        self::assertSame(['/1/2/3/', 2], $this->manager->getConnection()->fetchNumeric('SELECT tree_path, tree_depth FROM folder WHERE id = 3'));
        $games = $this->folder(1);
        self::assertSame(['Jeux', 'Livres'], $this->names($this->tree->roots(Folder::class)));
        self::assertSame(['Société', 'Cartes'], $this->names($this->tree->children($games)));
        self::assertSame(['Société', 'Échecs', 'Cartes'], $this->names($this->tree->descendants($games)));
        self::assertSame([], $this->tree->descendants(new Folder('Nouveau')));
        self::assertSame(['Jeux', 'Société'], $this->names($this->tree->ancestors($this->folder(3))));
        self::assertSame([], $this->tree->ancestors($games));
        self::assertSame([], $this->tree->children($this->folder(3)));
        self::assertSame(['treePath', 'treeDepth'], [TreeNode::PATH_FIELD, TreeNode::DEPTH_FIELD]);
    }

    public function testABranchMovesWithItsNode(): void
    {
        $games = new Folder('Jeux');
        $board = new Folder('Société', $games);
        $chess = new Folder('Échecs', $board);
        $go = new Folder('Go', $board);
        $books = new Folder('Livres');
        $novels = new Folder('Romans', $books);
        array_map($this->manager->persist(...), [$games, $board, $chess, $go, $books, $novels]);
        $this->manager->flush();
        $this->manager->clear();

        $chess = $this->folder(3);
        $board = $this->folder(2);
        $board->parent = $this->folder(6);

        $this->manager->flush();

        self::assertSame(['/5/6/2/', 2, '/5/6/2/3/', 3], [$board->treePath(), $board->treeDepth(), $chess->treePath(), $chess->treeDepth()]);
        $work = $this->manager->getUnitOfWork();
        self::assertSame(['/5/6/2/', '/5/6/2/3/', 3], [$work->getOriginalEntityData($board)['treePath'] ?? null, $work->getOriginalEntityData($chess)['treePath'] ?? null, $work->getOriginalEntityData($chess)['treeDepth'] ?? null]);
        self::assertSame([['/5/6/2/4/', 3]], $this->manager->getConnection()->fetchAllNumeric('SELECT tree_path, tree_depth FROM folder WHERE id = 4'));
        self::assertSame([['/1/', 0]], $this->manager->getConnection()->fetchAllNumeric('SELECT tree_path, tree_depth FROM folder WHERE id = 1'));
        $this->manager->flush();
        $this->manager->clear();

        self::assertSame([
            ['Jeux', []],
            ['Livres', [['Romans', [['Société', [['Échecs', []], ['Go', []]]]]]]],
        ], $this->shape($this->tree->hierarchy(Folder::class)));
        self::assertSame([['Société', [['Go', []], ['Échecs', []]]]], $this->shape($this->tree->hierarchy($this->folder(2), static fn (Folder $a, Folder $b): int => $a->name <=> $b->name)));

        $board = $this->folder(2);
        $go = $this->folder(4);
        $board->parent = null;
        $go->parent = $this->folder(1);
        $this->manager->flush();
        $this->manager->clear();
        self::assertSame([['/1/4/', 1], ['/2/3/', 1], ['/2/', 0]], $this->manager->getConnection()->fetchAllNumeric('SELECT tree_path, tree_depth FROM folder WHERE id IN (2, 3, 4) ORDER BY id DESC'));
    }

    public function testAnIdentifierOfTheCallerAndItsWildcards(): void
    {
        $note = new Note('n', 'a');
        $this->manager->persist($note);
        $this->manager->flush();

        $root = new Topic('a_b');
        $child = new Topic('c%', $root);
        $other = new Topic('a0b');
        $otherChild = new Topic('d', $other);
        array_map($this->manager->persist(...), [$root, $child, $other, $otherChild]);
        $this->manager->flush();
        self::assertSame(['a0b', 'a_b'], array_map(static fn (Topic $topic): string => $topic->id, $this->tree->roots(Topic::class)));

        self::assertSame('/a_b/c%/', $child->treePath());
        self::assertSame(['c%'], array_map(static fn (Topic $topic): string => $topic->id, $this->tree->descendants($root)));
        self::assertSame(['/a!_b/c!%/!!%', '/x/%'], [TreePaths::like('/a_b/c%/!'), TreePaths::like('/x/')]);

        $note->text = 'b';
        $root->parent = $other;
        $this->manager->flush();

        $this->manager->clear();
        self::assertSame([['/a0b/a_b/c%/', 2], ['/a0b/d/', 1]], $this->manager->getConnection()->fetchAllNumeric("SELECT tree_path, tree_depth FROM topic WHERE id IN ('c%', 'd') ORDER BY id"));

        $loose = new Topic('loose');
        $this->manager->persist($loose);
        $this->manager->flush();
        $this->manager->getConnection()->executeStatement("UPDATE topic SET tree_path = NULL, tree_depth = NULL WHERE id = 'loose'");
        $this->manager->clear();
        $loose = $this->manager->find(Topic::class, 'loose');
        self::assertInstanceOf(Topic::class, $loose);
        $moved = $this->manager->find(Topic::class, 'c%');
        self::assertInstanceOf(Topic::class, $moved);
        $loose->parent = $this->manager->find(Topic::class, 'a0b');
        $moved->parent = $this->manager->find(Topic::class, 'd');
        $this->manager->flush();
        self::assertNull($loose->treePath());
        self::assertSame('/a0b/d/c%/', $moved->treePath());
    }

    public function testTheChildrenAreReadWithoutTheRestOfTheBranch(): void
    {
        $games = new Folder('Jeux');
        $board = new Folder('Société', $games);
        $chess = new Folder('Échecs', $board);
        $cards = new Folder('Cartes', $games);
        array_map($this->manager->persist(...), [$games, $board, $chess, $cards]);
        $this->manager->flush();
        $this->manager->clear();

        self::assertSame([], $this->tree->children(new Folder('Nouveau')));
        self::assertSame([], $this->manager->getUnitOfWork()->getIdentityMap(), 'a node without a path reads nothing');

        $games = $this->folder(1);
        self::assertSame(['Société', 'Cartes'], $this->names($this->tree->children($games)));
        self::assertSame(['Jeux', 'Société', 'Cartes'], array_map(static fn (object $loaded): string => $loaded instanceof Folder ? $loaded->name : $loaded::class, array_values($this->manager->getUnitOfWork()->getIdentityMap()[Folder::class] ?? [])), 'the grandchild is never loaded');
        self::assertSame([], $this->tree->children(new Folder('Nouveau')));

        $root = new Topic('a_b');
        $other = new Topic('a0b');
        array_map($this->manager->persist(...), [$root, $other, new Topic('c', $root), new Topic('d', $other)]);
        $this->manager->flush();
        self::assertSame(['c'], array_map(static fn (Topic $topic): string => $topic->id, $this->tree->children($root)), 'an underscore is no wildcard');
    }

    public function testTheMovesOfAFailedFlushAreNeverApplied(): void
    {
        $games = new Folder('Jeux');
        $board = new Folder('Société', $games);
        $chess = new Folder('Échecs', $board);
        $books = new Folder('Livres');
        array_map($this->manager->persist(...), [$games, $board, $chess, $books]);
        $this->manager->flush();
        $this->manager->clear();

        $failing = new class {
            public bool $fail = true;

            public function onFlush(): void
            {
                if ($this->fail) {
                    $this->fail = false;

                    throw new RuntimeException('The flush fails.');
                }
            }
        };
        $this->manager->getEventManager()->addEventListener([Events::onFlush], $failing);

        $this->folder(2)->parent = $this->folder(4);

        try {
            $this->manager->flush();
            self::fail('The flush fails.');
        } catch (RuntimeException) {
        }

        // The change is given up, then an unrelated flush succeeds: the branch stays where the database has it.
        $this->manager->clear();
        $this->manager->persist(new Note('n', 'rien'));
        $this->manager->flush();

        self::assertSame([['/1/2/', 1], ['/1/2/3/', 2]], $this->manager->getConnection()->fetchAllNumeric('SELECT tree_path, tree_depth FROM folder WHERE id IN (2, 3) ORDER BY id'));
    }

    public function testAResetForgetsTheMovesWaiting(): void
    {
        $games = new Folder('Jeux');
        $board = new Folder('Société', $games);
        $chess = new Folder('Échecs', $board);
        $books = new Folder('Livres');
        array_map($this->manager->persist(...), [$games, $board, $chess, $books]);
        $this->manager->flush();
        $this->manager->clear();

        $listener = new TreeListener();
        $board = $this->folder(2);
        $board->parent = $this->folder(4);

        $work = $this->manager->getUnitOfWork();
        $work->computeChangeSets();

        $listener->onFlush(new OnFlushEventArgs($this->manager));
        $listener->reset();
        $listener->postFlush(new PostFlushEventArgs($this->manager));

        self::assertSame([['/1/2/3/', 2]], $this->manager->getConnection()->fetchAllNumeric('SELECT tree_path, tree_depth FROM folder WHERE id = 3'));
    }

    public function testANodeCannotTakeAnyPlace(): void
    {
        foreach ([
            [static fn (): Topic => new Topic('a/b'), 'A tree node is identified without a slash, and by something; "a/b" is not.'],
            [static fn (): Topic => new Topic(''), 'A tree node is identified without a slash, and by something; "" is not.'],
            [static fn (): Topic => new Topic('child', new Topic('parent')), 'The parent of the tree node "child" has no path: flush the parent first.'],
        ] as [$make, $message]) {
            try {
                $topic = $make();
                TreePaths::of($topic);
                self::fail($message);
            } catch (InvalidTree $invalidTree) {
                self::assertSame($message, $invalidTree->getMessage());
            }
        }

        $root = new Topic('root');
        $child = new Topic('child', $root);
        array_map($this->manager->persist(...), [$root, $child]);
        $this->manager->flush();
        $root->parent = $child;

        $this->expectException(InvalidTree::class);
        $this->expectExceptionMessageIsOrContains('The tree node "root" cannot move under itself or one of its descendants.');

        $this->manager->flush();
    }

    private function folder(int $id): Folder
    {
        $folder = $this->manager->find(Folder::class, $id);
        self::assertInstanceOf(Folder::class, $folder);

        return $folder;
    }

    /**
     * @param list<Folder> $folders
     *
     * @return list<string>
     */
    private function names(array $folders): array
    {
        return array_map(static fn (Folder $folder): string => $folder->name, $folders);
    }

    /**
     * @param list<Branch<Folder>> $branches
     *
     * @return list<array{string, list<mixed>}>
     */
    private function shape(array $branches): array
    {
        return array_map(fn (Branch $branch): array => [$branch->node->name, $this->shape($branch->children)], $branches);
    }
}
