# Listings

A listing is a content root of `phil/content-bundle` with one row per version, stage and locale. Its fields are the
capabilities of content-bundle — title and description of the excerpt, slug, Markdown body, workflow, SEO — and five
of the directory:

| Capability | Key of the data | Column |
|---|---|---|
| Contact | `contact`: the array of `ContactCardArray` | `contact` (`phil_contact`) |
| Address | `address`: the array of `AddressArray` | `address` (`phil_address`) |
| Location | `location`: `"45.5,-73.6"` | `location_latitude`, `location_longitude` |
| Related | `related`: listing identifiers by relation, `{"played_at": ["0190…"]}` | `related` (JSON) |
| Edited by | `editedBy`: the user identifier | `edited_by` |

## Who may do what

| Action | Who | Voter attribute |
|---|---|---|
| Write a listing | anyone signed in, who becomes its author | — |
| Change it | its author, its editors, the administrators | `phil_directory.edit` |
| Name or remove editors | its author, the administrators | `phil_directory.editors` |
| Decide who finds it | its author, the administrators | `phil_directory.editors` |
| Publish it | the administrators | `phil_directory.publish` |
| Read it | everyone, once published | — |

An editor is a user identifier of 1 to 255 bytes; a listing has up to 50. Naming the author or an editor twice changes
nothing.

```php
use Phil\DirectoryBundle\Application\Actor;
use Phil\DirectoryBundle\Application\Directory;

$reference = $directory->create('podcast', 'fr', ['excerptTitle' => 'Le Gnome', 'slug' => 'le-gnome'], new Actor('ada'));
$directory->addEditor($reference, 'grace', new Actor('ada'));
$directory->edit($reference, 'fr', ['excerptDescription' => 'Un balado.'], new Actor('grace'));
$directory->publish($reference, 'fr', new Actor('admin', admin: true));
```

A refused action throws `ListingRefused`, a `DomainError`; content-bundle's own refusals, such as a slug already
taken, throw `InvalidContent`. The controller shows both as form errors.

## Related listings

`related` holds the listings a listing names, by the relations of its genre ([configuration](configuration.md)). It
replaces the whole set when given, `null` clears it, and its absence keeps it. `Directory` checks it before writing:

- each relation is written on the listing's genre — another is a `ListingRefused`;
- each listing exists and is of the relation's `to` genre, and is not the listing itself;
- one listing at most for a relation that is not `multiple`;
- a relation the configuration no longer declares is dropped.

```php
$directory->create('larp', 'fr', ['excerptTitle' => 'La Saga', 'related' => ['played_at' => [$site->resourceId]]], $admin);

foreach ($directory->related($site, 'fr') as $group) {
    $group->title();     // "Larps played here": the inverse title, on the larp site
    $group->listings;    // the published larps that name the site, by slug
}
```

`related()` gives the published listings a listing names, then those that name it, a `RelatedListings` per relation
and way, those with none left out. The listing's own relations are read as its page is served, along the locale's
fallback chain; the listings shown are those published in the locale with a slug, as in the list of a genre, read by
their identifiers in one query. Those that name it are looked up first (`RelatedLookup`, whose Doctrine adapter is
`DoctrineRelatedLookup`): the identifier and the `related` column of the live rows of the other genre that have some,
read as arrays without hydrating an entity, their JSON searched in PHP — no portable query searches a JSON column.
Only those listings are then read as published listings, by their identifiers, and kept when their published row names
the listing: the listings hydrated follow the listings related, not the size of the genre.
`relationsFrom($genre)` gives the relations a genre's form offers.

## The list of a genre

`Directory::published($genre, $locale, $offset = 0, $limit = null, $reader = Reader::SiteListings)` gives the
listings of a genre published in a locale, by slug, in one read of content-bundle's published rows
(`PublishedRowQuery`, filtered on the genre column and on who finds the listing for the `Reader` given), maybe a page
of them. A listing published without a slug has no page to link to, and is left out of the list, of the
related listings and of the sitemap.

## Who finds a listing

The author of a listing decides who finds it, with the `Indexing` of phil/indexing-consent: `Listing` implements
`HasIndexingConsent`. The choice is on the page of its editors, and takes effect at once, without waiting for a
publication: it is the listing's, not a version's.

| Choice | The list of its genre, the related listings | The sitemap | Its page |
|---|---|---|---|
| `Listed`, by default | shown | listed | indexed |
| `NotIndexed` | shown | left out | `noindex, noarchive` |
| `Unlisted` | left out | left out | `noindex, noarchive` |

The page of a listing that is not listed tells robots `noindex, noarchive`, in its `X-Robots-Tag` header and its
`<meta name="robots">`; it stays reachable at its address, so that robots read the directive. The page carries the
choice on its request under `IndexingPolicy::REQUEST_ATTRIBUTE`, and phil/structured-data-bundle then prints no
JSON-LD for it.

```php
use Phil\IndexingConsent\Indexing;

$directory->changeIndexing($reference, Indexing::NotIndexed, new Actor('ada'));
$directory->listing($reference)->indexing();       // Indexing::NotIndexed
$directory->listing($reference)->isSiteListed();   // true
```

Only the author and the administrators change it; anyone else is refused with a `ListingRefused`. The choice is kept
in two columns of the listing, its value and whether the site lists it, which the queries of published rows filter
on: the list of a genre and the related listings keep what `Reader::SiteListings` allows, the sitemap what
`Reader::Sitemap` allows, in the same single query.

## Publishing and history

Every change is a version, exactly as for any content: changing a published version starts a new draft, and the
published one stays online until an administrator publishes the next. Each version records who changed it, in
`editedBy`.

By default the change of an author or an editor stays a draft; an administrator's change is published at once. With
`publish_every_change`, every change is published at once.

`Directory::history()` lists the versions of a listing in a locale, the latest first, each with its number, its title,
who changed it and when it was published, read in one query. `Directory::find()` reads the published listing of a
slug; an old slug gives the page with `movedTo`, which the controller answers with a 301.

## User identifiers and their erasure

A listing keeps the identifier of its author and of its editors, and each version the identifier of who wrote it
(`editedBy`): the string the actor carries, which `Actors` takes from the security token's `getUserIdentifier()`. The
editors page shows the author to those who manage the editors, and the history shows who wrote each version to the
listing's author, editors and administrators. When that identifier is an e-mail address, those people see each
other's addresses: an application that does not want that gives its users an opaque identifier, or overrides the
templates `editors` and `history` to show a display name instead.

The identifiers are kept as long as the listing and its versions are, which is for life: the history is the listing's.
When an account is erased, the application erases its identifier from the directory as well, as Law 25 and the GDPR
require:

```php
$directory->eraseUser('ada@example.com');   // how many listings and versions changed
```

`eraseUser()` takes the identifier off the listings it wrote — they keep no author, and the administrators name their
editors — and off those it edits, and empties the `editedBy` of every version it wrote, draft and published, in one
transaction (`UserErasure`, whose Doctrine adapter is `DoctrineUserErasure`). The listings and their versions stay,
with what they say.
