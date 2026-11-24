<?php

declare(strict_types=1);

/*
 * A file of questions nobody wrote, read as JSON and imported into a store that keeps nothing: whatever it holds, the
 * import ends in its counts or in an exception of the bundle — never another error.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Application\FaqTransfer;
use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Exception\FaqException;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\QuestionTranslation;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\Model\SectionTranslation;
use Phil\FaqBundle\Domain\ValueObject\VoteCount;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\Slug\TransliteratingSlugger;
use Symfony\Component\Clock\MockClock;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$repository = new class implements FaqRepository {
    public function sections(): array
    {
        return [];
    }

    public function section(string $id): ?Section
    {
        return null;
    }

    public function sectionByKey(string $key): ?Section
    {
        return null;
    }

    public function questionsOf(Section $section): array
    {
        return [];
    }

    public function questions(): array
    {
        return [];
    }

    public function question(string $id): ?Question
    {
        return null;
    }

    public function questionByAnchor(string $anchor): ?Question
    {
        return null;
    }

    public function sectionTranslations(): array
    {
        return [];
    }

    public function questionTranslations(bool $publishedOnly): array
    {
        return [];
    }

    public function sectionTranslation(Section $section, string $locale): ?SectionTranslation
    {
        return null;
    }

    public function questionTranslation(Question $question, string $locale): ?QuestionTranslation
    {
        return null;
    }

    public function save(Section|SectionTranslation|Question|QuestionTranslation ...$objects): void
    {
    }

    public function remove(Section|Question $object): void
    {
    }

    public function countVote(string $questionId, bool $helpful): ?VoteCount
    {
        return null;
    }

    public function transactional(callable $work): mixed
    {
        return $work();
    }
};
$editor = new FaqEditor($repository, new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies()), new TransliteratingSlugger(), new MockClock());
$transfer = new FaqTransfer($repository, $editor);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($transfer): void {
    try {
        $transfer->import(json_decode($input, true, 16));
    } catch (FaqException) {
        // A file refused: what the bundle promises.
    }
});
$config->setMaxLen(4096);
