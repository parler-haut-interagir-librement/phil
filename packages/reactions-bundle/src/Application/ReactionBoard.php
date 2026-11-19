<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Override;
use Phil\ReactionsBundle\Application\Contract\CurrentMember;
use Phil\ReactionsBundle\Application\Contract\MemberNames;
use Phil\ReactionsBundle\Application\Contract\ReactionAccess;
use Phil\ReactionsBundle\Application\Contract\VisibilityPolicy;
use Phil\ReactionsBundle\Application\View\PickerGroup;
use Phil\ReactionsBundle\Application\View\ReactionItem;
use Phil\ReactionsBundle\Application\View\ReactionPanel;
use Phil\ReactionsBundle\Application\View\ReactorsLine;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\Resource\ResourceReference;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a page shows of the reactions of its contents. `preload()` reads every content of a page in one query for each
 * thing to know, however many components the page draws; what it read is kept for this request only.
 *
 * The private reactions leave this class without a counter or a name. A member who gave a content as many reactions as
 * the site allows sees the others without being able to press them, until they take one back.
 */
final class ReactionBoard implements ResetInterface
{
    public const string DOMAIN = ReactionDefinition::DEFAULT_DOMAIN;

    /** How many contents one query asks about. */
    public const int CHUNK = 200;

    /** How many names are shown for a reaction. */
    public const int NAMES_SHOWN = 10;

    /** @var array<string, array<string, int>> */
    private array $counts = [];

    /** @var array<string, list<string>> */
    private array $chosen = [];

    /** @var array<string, array<string, list<string>>> */
    private array $reactors = [];

    /** @var array<string, string> */
    private array $names = [];

    public function __construct(
        private readonly ReactionRepository $repository,
        private readonly ReactionCatalog $catalog,
        private readonly ReactionAccess $access,
        private readonly CurrentMember $member,
        private readonly VisibilityPolicy $visibility,
        private readonly MemberNames $memberNames,
        private readonly TranslatorInterface $translator,
        private readonly ReactionsPerMember $perMember = new ReactionsPerMember(),
    ) {
    }

    /**
     * Reads the reactions of all these contents, the ones read already left out.
     *
     * @param iterable<ResourceReference|string> $contents references, or references written out
     */
    public function preload(iterable $contents): void
    {
        $missing = [];

        foreach ($contents as $content) {
            $reference = $content instanceof ResourceReference ? $content : ResourceReference::fromString($content);

            if (!\array_key_exists($reference->toString(), $this->counts)) {
                $missing[$reference->toString()] = $reference;
            }
        }

        foreach (array_chunk($missing, self::CHUNK, true) as $chunk) {
            $this->load($chunk);
        }
    }

    /**
     * Forgets a content whose reactions have just changed.
     */
    public function forget(ResourceReference $content): void
    {
        $key = $content->toString();
        unset($this->counts[$key], $this->chosen[$key], $this->reactors[$key]);
    }

    public function panel(ResourceReference $content): ReactionPanel
    {
        $member = $this->member->current();
        $visibility = $this->visibility->of($content);

        if (!$visibility->panel->admits(null !== $member)) {
            return new ReactionPanel(false);
        }

        $this->preload([$content]);
        $key = $content->toString();
        $showsCounts = $visibility->counts->admits(null !== $member);
        $chosen = $this->chosen[$key];
        $full = !$this->perMember->replaces() && !$this->perMember->admitsAnother(\count($chosen));
        $bar = [];
        $picker = [];
        $reactors = [];

        foreach ($this->catalog->groups() as $group) {
            $others = [];

            foreach ($group->definitions as $definition) {
                $count = $this->counts[$key][$definition->code] ?? 0;
                $shown = $showsCounts && !$definition->isPrivate ? $count : null;
                $pressed = \in_array($definition->code, $chosen, true);
                $allowed = $pressed || (!$full && $this->allowed($content, $member, $definition));
                $item = $this->item($definition, $shown, $pressed, $allowed);

                if ($pressed || ($definition->quick && $allowed) || (null !== $shown && $shown > 0)) {
                    $bar[] = $item;
                } elseif ($allowed) {
                    $others[] = $item;
                }

                if (!$definition->isPrivate) {
                    $reactors = [...$reactors, ...$this->reactorsLine($key, $definition, $item, $count)];
                }
            }

            if ([] !== $others) {
                $picker[] = new PickerGroup($this->translator->trans($group->label, [], $group->domain), $others);
            }
        }

        return new ReactionPanel(true, $bar, $picker, $reactors, $full);
    }

    #[Override]
    public function reset(): void
    {
        $this->counts = [];
        $this->chosen = [];
        $this->reactors = [];
        $this->names = [];
    }

    /**
     * @param array<string, ResourceReference> $contents
     */
    private function load(array $contents): void
    {
        $keys = array_keys($contents);
        $member = $this->member->current();
        $chosen = null === $member ? [] : $this->repository->chosenBy($member, $keys);
        $this->counts += array_replace(array_fill_keys($keys, []), $this->repository->counts($keys));
        $this->chosen += array_replace(array_fill_keys($keys, []), $chosen);

        $named = array_keys(array_filter($contents, fn (ResourceReference $content): bool => $this->visibility->of($content)->names->admits(null !== $member)));

        if ([] === $named) {
            return;
        }

        $reactors = $this->repository->reactors($named, self::NAMES_SHOWN);
        $this->reactors += $reactors;
        $members = [];

        foreach ($reactors as $codes) {
            foreach ($codes as $ids) {
                $members = [...$members, ...$ids];
            }
        }

        if ([] !== $members) {
            $this->names += $this->memberNames->names(array_values(array_unique($members)));
        }
    }

    private function allowed(ResourceReference $content, ?string $member, ReactionDefinition $definition): bool
    {
        return null !== $member && $this->access->canReact(new ReactionSubject($content, $member, $definition));
    }

    private function item(ReactionDefinition $definition, ?int $shown, bool $pressed, bool $allowed): ReactionItem
    {
        return new ReactionItem(
            $definition->code,
            $this->translator->trans($definition->label, [], $definition->domain),
            $definition->emoji,
            $definition->image,
            null === $definition->imageAlt ? null : $this->translator->trans($definition->imageAlt, [], $definition->domain),
            $shown,
            null === $shown ? null : $this->translator->trans('reactions.count', ['%count%' => $shown], self::DOMAIN),
            $pressed,
            $allowed,
        );
    }

    /**
     * @return list<ReactorsLine>
     */
    private function reactorsLine(string $key, ReactionDefinition $definition, ReactionItem $item, int $count): array
    {
        $ids = $this->reactors[$key][$definition->code] ?? [];
        $names = array_values(array_filter(array_map(fn (string $id): ?string => $this->names[$id] ?? null, $ids), \is_string(...)));

        if ([] === $names) {
            return [];
        }

        return [new ReactorsLine($item->label, $names, null === $item->count ? 0 : $count - \count($ids))];
    }
}
