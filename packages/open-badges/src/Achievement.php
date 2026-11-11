<?php

declare(strict_types=1);

namespace Phil\OpenBadges;

use Phil\OpenBadges\Exception\InvalidBadge;

/**
 * What a credential recognizes, as Open Badges 3.0 describes it: its identifier an absolute URL, its name and
 * description in the recipient's language, the narrative of its criteria, its image, and the endorsements third
 * parties signed for it.
 */
final readonly class Achievement
{
    /**
     * @param list<array<string, mixed>|string> $endorsements signed `EndorsementCredential`s, documents or tokens
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $criteria,
        public ?string $image = null,
        public array $endorsements = [],
    ) {
        self::checkUrl($id, 'achievement');

        if (null !== $image) {
            self::checkUrl($image, "achievement's image");
        }

        if ('' === trim($name) || '' === trim($criteria)) {
            throw InvalidBadge::because(\sprintf('The achievement "%s" has a name and the narrative of its criteria.', $id));
        }

        Endorsements::fields($endorsements);
    }

    /**
     * @internal
     */
    public static function checkUrl(string $url, string $what): void
    {
        if (1 !== preg_match('#^https?://[^\s/?\#<>"{}|\\\\^`]+[^\s<>"{}|\\\\^`]*$#D', $url)) {
            throw InvalidBadge::because(\sprintf('The identifier of the %s is an absolute HTTP URL; "%s" is not.', $what, $url));
        }
    }

    /**
     * The achievement as an Open Badges `Achievement`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $achievement = [
            'id' => $this->id,
            'type' => ['Achievement'],
            'name' => $this->name,
            'description' => $this->description,
            'criteria' => ['narrative' => $this->criteria],
        ];

        if (null !== $this->image) {
            $achievement['image'] = ['id' => $this->image, 'type' => 'Image'];
        }

        return [...$achievement, ...Endorsements::fields($this->endorsements)];
    }
}
