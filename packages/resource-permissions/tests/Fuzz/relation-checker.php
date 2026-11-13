<?php

declare(strict_types=1);

/*
 * The relation checker on graphs nobody wrote: each pair of bytes of the input is a tuple among a few objects —
 * nested and cyclic groups, folders under folders or under themselves, every member, exclusions —, then every check
 * gives a yes or a no, or refuses with TraversalBudgetExceeded, never anything else; an explanation exists exactly when
 * the check says yes, and starts on the object; a listing holds only objects the check accepts, and every one of them
 * when it says it is complete.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\Resource\ResourceReference;
use Phil\ResourcePermissions\Budget;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Phil\ResourcePermissions\Explanation;
use Phil\ResourcePermissions\InMemoryTupleStore;
use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\Model\Rewrite;
use Phil\ResourcePermissions\Model\TypeDefinition;
use Phil\ResourcePermissions\RelationChecker;
use Phil\ResourcePermissions\RelationTuple;
use Phil\ResourcePermissions\Subject;
use Symfony\Component\Clock\MockClock;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$model = new Model(
    TypeDefinition::of('member'),
    TypeDefinition::of('group', ['member' => Rewrite::direct('member', 'member:*', 'group#member')]),
    TypeDefinition::of('folder', [
        'parent' => Rewrite::direct('folder'),
        'blocked' => Rewrite::direct('member', 'group#member'),
        'editor' => Rewrite::direct('member', 'group#member'),
        'viewer' => Rewrite::exclusion(
            Rewrite::union(Rewrite::direct('member', 'member:*', 'group#member'), Rewrite::computed('editor'), Rewrite::fromRelated('parent', 'viewer')),
            Rewrite::computed('blocked'),
        ),
        'both' => Rewrite::intersection(Rewrite::computed('editor'), Rewrite::computed('viewer')),
    ]),
);
$members = array_map(static fn (int $i): Subject => Subject::of(ResourceReference::of('member', 'm'.$i)), range(0, 3));
$groups = array_map(static fn (int $i): ResourceReference => ResourceReference::of('group', 'g'.$i), range(0, 3));
$folders = array_map(static fn (int $i): ResourceReference => ResourceReference::of('folder', 'f'.$i), range(0, 3));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($model, $members, $groups, $folders): void {
    $store = new InMemoryTupleStore(new MockClock('2026-11-13 10:00:00 UTC'));
    $tuples = [];

    foreach (str_split(substr($input, 0, 128), 2) as $pair) {
        $a = ord($pair[0]);
        $b = ord(($pair[1] ?? "\0")[0]);
        $subject = match ($b >> 6) {
            0 => $members[$b & 3],
            1 => Subject::set($groups[$b & 3], 'member'),
            2 => Subject::every('member'),
            default => Subject::of($folders[$b & 3]),
        };
        $relation = ['member', 'parent', 'blocked', 'editor', 'viewer', 'viewer', 'editor', 'member'][$a >> 5];
        $object = 'member' === $relation ? $groups[$a & 3] : $folders[$a & 3];

        if ($model->accepts($object->key, $relation, $subject)) {
            $tuples[] = new RelationTuple($object, $relation, $subject);
        }
    }

    $store->update([], $tuples);
    $checker = new RelationChecker($model, $store, new Budget(maxDepth: 12, maxReads: 200, maxFanOut: 8, maxCandidates: 16));

    foreach ($members as $member) {
        foreach (['viewer', 'both', 'editor'] as $relation) {
            $accepted = [];

            try {
                foreach ($folders as $folder) {
                    $explanation = $checker->explain($member, $relation, $folder);

                    if ($checker->check($member, $relation, $folder) !== ($explanation instanceof Explanation)) {
                        throw new LogicException('A check and its explanation disagree.');
                    }

                    if ($explanation instanceof Explanation) {
                        if ([] === $explanation->tuples || !$explanation->tuples[0]->object->equals($folder)) {
                            throw new LogicException('An explanation does not start on its object.');
                        }

                        $accepted[] = $folder->id;
                    }
                }

                $list = $checker->objects($member, $relation, 'folder', 10);
            } catch (TraversalBudgetExceeded) {
                continue;
            }

            if ([] !== array_diff($list->ids(), $accepted)) {
                throw new LogicException('A listing holds an object the check refuses.');
            }

            if ($list->complete && $list->ids() !== $accepted) {
                throw new LogicException('A complete listing misses an object the check accepts.');
            }
        }
    }
});
$config->setMaxLen(128);
