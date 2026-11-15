<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Functional\App;

use Override;
use Phil\ModerationBundle\Application\Contract\ModerationTarget;
use Phil\ModerationBundle\Application\TargetView;
use Phil\ModerationBundle\Domain\Status\DecisionAction;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * The forum posts of the test application, registered by autoconfiguration: post 1 by `author`, the others gone.
 * The actions applied are kept in a file of the cache, so that a test reads them after a request.
 */
final readonly class Posts implements ModerationTarget
{
    public function __construct(
        private string $log,
    ) {
    }

    #[Override]
    public function type(): string
    {
        return 'forum.post';
    }

    #[Override]
    public function describe(string $id): ?TargetView
    {
        return '1' === $id ? new TargetView('Cheap watches', 'Buy <b>now</b>', '/forum/1', MemberId::of('author')) : null;
    }

    #[Override]
    public function apply(string $id, DecisionAction $action): void
    {
        file_put_contents($this->log, $id.' '.$action->value."\n", \FILE_APPEND);
    }
}
