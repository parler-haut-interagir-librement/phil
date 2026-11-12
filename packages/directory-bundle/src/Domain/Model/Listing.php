<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;
use Phil\DirectoryBundle\Domain\Exception\ListingRefused;
use Phil\DirectoryBundle\Domain\ValueObject\ListingId;
use Phil\IndexingConsent\Contract\HasIndexingConsent;
use Phil\IndexingConsent\Indexing;
use Phil\IndexingConsent\Reader;

/**
 * A listing of the directory: a content of content-bundle, whose versions keep its history, in a genre — a podcast,
 * an artist, a club —, written by its author and by the editors the author names. What a listing says lives in its
 * rows; who may change it, and who finds it, live here, outside the versions.
 *
 * Who finds it is the choice of its author, as phil/indexing-consent names it: listed everywhere, kept out of search
 * engines, or reachable only by its address. The choice takes effect at once, without waiting for a publication; it is
 * kept as its value and as whether the site lists the listing, the two fields a query of published rows filters on.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_directory_listing')]
#[ORM\Index(name: 'phil_directory_listing_genre', columns: ['genre'])]
final class Listing extends AbstractContentRoot implements HasIndexingConsent
{
    public const int MAX_EDITORS = 50;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'phil_directory_listing_id')]
    private ListingId $id;

    #[ORM\Column(name: 'genre', length: 64, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(name: 'author', length: 255, nullable: true)]
    private ?string $author = null;

    /** @var list<string> */
    #[ORM\Column(name: 'editors', type: 'json')]
    private array $editors = [];

    #[ORM\Column(name: 'indexing', length: 16, enumType: Indexing::class, options: ['default' => 'listed'])]
    private Indexing $indexing = Indexing::Listed;

    #[ORM\Column(name: 'site_listed', options: ['default' => true])]
    private bool $siteListed = true;

    public function __construct()
    {
        $this->id = ListingId::generate();
    }

    #[Override]
    public static function dimensionContentClass(): string
    {
        return ListingDimension::class;
    }

    public function id(): ListingId
    {
        return $this->id;
    }

    /**
     * Puts a new listing in its genre and names its author, once.
     */
    public function assign(string $genre, string $author): void
    {
        if (null !== $this->genre) {
            throw ListingRefused::because('A listing is put in its genre once.');
        }

        $this->genre = self::genreKey($genre);
        $this->author = $this->user($author);
    }

    #[Override]
    public function indexing(): Indexing
    {
        return $this->indexing;
    }

    /**
     * Changes who finds the listing: the choice of its author, or of an administrator.
     */
    public function changeIndexing(Indexing $indexing): void
    {
        $this->indexing = $indexing;
        $this->siteListed = $indexing->allows(Reader::SiteListings);
    }

    /**
     * Whether the site lists the listing: in the list of its genre, among the related listings of another.
     */
    public function isSiteListed(): bool
    {
        return $this->siteListed;
    }

    public function genre(): ?string
    {
        return $this->genre;
    }

    public function author(): ?string
    {
        return $this->author;
    }

    /**
     * @return list<string>
     */
    public function editors(): array
    {
        return $this->editors;
    }

    /**
     * Whether a user may change what the listing says: its author, or an editor the author named.
     */
    public function mayEdit(?string $user): bool
    {
        return null !== $user && ($user === $this->author || \in_array($user, $this->editors, true));
    }

    public function isAuthor(?string $user): bool
    {
        return null !== $user && $user === $this->author;
    }

    public function addEditor(string $user): void
    {
        $user = $this->user($user);

        if ($this->mayEdit($user)) {
            return;
        }

        if (\count($this->editors) >= self::MAX_EDITORS) {
            throw ListingRefused::because(\sprintf('A listing has %d editors at most.', self::MAX_EDITORS));
        }

        $this->editors[] = $user;
    }

    public function removeEditor(string $user): void
    {
        $this->editors = array_values(array_filter($this->editors, static fn (string $editor): bool => $editor !== $user));
    }

    /**
     * Forgets a user who is erased: no longer the author — the listing then has none, and only the administrators
     * name its editors —, no longer an editor. Whether the listing changed.
     */
    public function forget(string $user): bool
    {
        $changed = false;

        if ($user === $this->author) {
            $this->author = null;
            $changed = true;
        }

        if (\in_array($user, $this->editors, true)) {
            $this->removeEditor($user);
            $changed = true;
        }

        return $changed;
    }

    public static function genreKey(string $genre): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $genre)) {
            throw ListingRefused::because(\sprintf('A genre is a lowercase letter followed by up to 63 lowercase letters, digits or underscores; "%s" is not.', $genre));
        }

        return $genre;
    }

    private function user(string $user): string
    {
        if ('' === trim($user) || \strlen($user) > 255) {
            throw ListingRefused::because('A user is named by an identifier of 1 to 255 bytes.');
        }

        return $user;
    }
}
