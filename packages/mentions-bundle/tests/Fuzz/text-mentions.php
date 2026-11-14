<?php

declare(strict_types=1);

/*
 * The extraction and the linking on texts nobody wrote: whatever they read, they return mentions and HTML, and never
 * throw. A mention found always starts with an `@` at its offset.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\Application\Extraction\TextMentions;
use Phil\MentionsBundle\Application\Member;
use Phil\MentionsBundle\Application\Rendering\MentionLinker;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use Phil\Resource\ResourceReference;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$text = new TextMentions();
$linker = new MentionLinker(new class implements MemberDirectory {
    public function resolve(array $handles): iterable
    {
        foreach ($handles as $handle) {
            yield new Member(ResourceReference::of('user', '1'), $handle, 'Someone', '/m/1');
        }
    }

    public function members(array $references): iterable
    {
        return [];
    }

    public function search(string $query, int $limit): iterable
    {
        return [];
    }

    public function maySee(ResourceReference $member, ResourceReference $content): bool
    {
        return true;
    }
}, MentionPattern::default(), 20);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($text, $linker): void {
    foreach ([false, true] as $markdown) {
        foreach ($text->find($input, $markdown) as $mention) {
            if ('@' !== mb_substr($input, (int) $mention->offset, 1)) {
                throw new LogicException(sprintf('No "@" at offset %d.', (int) $mention->offset));
            }
        }
    }

    $linker->link($input);
});
$config->setMaxLen(4096);
