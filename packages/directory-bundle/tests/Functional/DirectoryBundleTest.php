<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\DirectoryBundle\Application\Actor;
use Phil\DirectoryBundle\Application\Directory;
use Phil\DirectoryBundle\Application\ListingVersion;
use Phil\DirectoryBundle\Application\RelatedListings;
use Phil\DirectoryBundle\Application\Relation;
use Phil\DirectoryBundle\Domain\Exception\ListingRefused;
use Phil\DirectoryBundle\Infrastructure\Doctrine\DoctrineRelatedLookup;
use Phil\DirectoryBundle\Infrastructure\Sitemap\DirectoryUrlProvider;
use Phil\DirectoryBundle\PhilDirectoryBundle;
use Phil\DirectoryBundle\Tests\Functional\App\TestKernel;
use Phil\IndexingConsent\Indexing;
use Phil\IndexingConsent\IndexingPolicy;
use Phil\IndexingConsent\Reader;
use Phil\Sitemap\Sitemap;
use Phil\Sitemap\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(PhilDirectoryBundle::class)]
final class DirectoryBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private function signIn(?string $user, string $role = 'ROLE_USER'): void
    {
        $tokens = $this->service('security.token_storage');
        self::assertInstanceOf(TokenStorage::class, $tokens);
        $tokens->setToken(null === $user ? null : new UsernamePasswordToken(new InMemoryUser($user, null, [$role]), 'main', [$role]));
    }

    public function testAListingIsWrittenEditedAndPublished(): void
    {
        $this->signIn(null);
        $this->client->request('GET', '/balados');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Balados');
        self::assertSelectorTextContains('ul.phil-directory', 'Aucune fiche');
        $this->client->request('GET', '/balados/ajouter');
        self::assertResponseStatusCodeSame(403);

        $this->signIn('ada');
        $this->client->request('GET', '/balados/ajouter');
        $this->client->submitForm('Enregistrer', [
            'listing[excerptTitle]' => 'Les Rôlistes',
            'listing[slug]' => 'les-rolistes',
            'listing[excerptDescription]' => 'Un balado de jeu de rôle.',
            'listing[bodySource]' => 'Tous les **mardis**.',
            'listing[contact][name]' => 'Ada',
            'listing[location][latitude]' => '45.5',
            'listing[location][longitude]' => '-73.5',
        ]);
        self::assertResponseRedirects();
        $edit = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/balados/[0-9a-f-]{36}/modifier$#', $edit);
        $id = explode('/', $edit)[2];

        $this->client->request('GET', '/balados/les-rolistes');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/balados');
        self::assertSelectorTextContains('ul.phil-directory', 'Aucune fiche');

        $this->client->request('GET', $edit);
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('listing[excerptTitle]', 'Les Rôlistes');
        self::assertInputValueSame('listing[location][latitude]', '45.5000000');
        $this->client->request('GET', '/balados/'.$id.'/historique');
        self::assertSelectorTextContains('table.phil-directory-history', 'ada');
        self::assertSelectorTextContains('table.phil-directory-history', 'Brouillon');
        self::assertSelectorNotExists('button:contains("Publier")');

        $this->client->request('GET', '/balados/'.$id.'/redacteurs');
        $this->client->submitForm('Enregistrer', ['editors[add]' => 'grace']);
        self::assertResponseRedirects('/balados/'.$id.'/redacteurs');

        $this->signIn('grace');
        $this->client->request('GET', $edit);
        $this->client->submitForm('Enregistrer', ['listing[excerptTitle]' => 'Les Rôlistes du mardi']);
        self::assertResponseRedirects($edit);
        $this->client->request('GET', '/balados/'.$id.'/redacteurs');
        self::assertResponseStatusCodeSame(403);

        $this->signIn('admin', 'ROLE_ADMIN');
        $this->client->request('GET', '/balados/'.$id.'/historique');
        self::assertSelectorTextContains('table.phil-directory-history', 'grace');
        $this->client->submitForm('Publier la dernière version');
        self::assertResponseRedirects('/balados/'.$id.'/historique');

        $this->signIn(null);
        $this->client->request('GET', '/balados');
        self::assertSelectorTextContains('ul.phil-directory', 'Les Rôlistes du mardi');
        $this->client->request('GET', '/balados/les-rolistes');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Les Rôlistes du mardi');
        self::assertSelectorTextContains('article', 'mardis');
        self::assertSelectorNotExists('article a');

        $this->signIn('grace');
        $this->client->request('GET', $edit);
        $this->client->submitForm('Enregistrer', ['listing[excerptTitle]' => 'Les Rôlistes du jeudi', 'listing[slug]' => 'les-rolistes-du-jeudi']);
        $this->client->request('GET', '/balados/les-rolistes');
        self::assertSelectorTextContains('h1', 'Les Rôlistes du mardi');
        self::assertSelectorExists('article a');

        $this->signIn('admin', 'ROLE_ADMIN');
        $this->client->request('GET', $edit);
        $this->client->submitForm('Enregistrer', ['listing[excerptDescription]' => 'Le jeudi soir.']);
        $this->signIn(null);
        $this->client->request('GET', '/balados/les-rolistes');
        self::assertResponseRedirects('/balados/les-rolistes-du-jeudi', 301);
        $this->client->request('GET', '/balados/les-rolistes-du-jeudi');
        self::assertSelectorTextContains('h1', 'Les Rôlistes du jeudi');

        $this->signIn('admin', 'ROLE_ADMIN');
        $this->client->request('GET', '/balados/'.$id.'/historique');
        self::assertCount(2, $this->client->getCrawler()->filter('table.phil-directory-history tr'));

        $this->signIn('bob');
        foreach (['modifier', 'redacteurs', 'historique'] as $page) {
            $this->client->request('GET', '/balados/'.$id.'/'.$page);
            self::assertResponseStatusCodeSame(403);
        }

        $this->client->request('POST', '/balados/'.$id.'/publier', ['publish' => ['confirm' => '1']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/balados/00000000-0000-7000-8000-000000000000/modifier');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheSitemapAndTheRefusals(): void
    {
        $directory = $this->service(Directory::class);
        self::assertInstanceOf(Directory::class, $directory);
        $admin = new Actor('admin', true);
        $first = $directory->create('podcast', 'fr', ['excerptTitle' => 'Zèbre', 'slug' => 'zebre'], $admin);
        $directory->create('podcast', 'fr', ['excerptTitle' => 'Abeille', 'slug' => 'abeille', 'seoHideInSitemap' => true], $admin);
        $directory->create('podcast', 'fr', ['excerptTitle' => 'Brouillon', 'slug' => 'brouillon'], new Actor('ada'));

        $this->client->request('GET', '/balados');
        self::assertSame(['Abeille', 'Zèbre'], $this->client->getCrawler()->filter('ul.phil-directory li a')->each(static fn (Crawler $link): string => $link->text()));

        $provider = $this->service('test.directory_urls');
        self::assertInstanceOf(DirectoryUrlProvider::class, $provider);
        $sitemap = new Sitemap();
        $provider->provide($sitemap, null);
        self::assertSame(['http://localhost/balados', 'http://localhost/balados/zebre'], array_map(static fn (Url $url): string => $url->loc, $sitemap->sections()['podcast'] ?? []));
        self::assertNotNull(($sitemap->sections()['podcast'] ?? [])[1]->lastmod ?? null);
        self::assertSame(['http://localhost/saison-2'], array_map(static fn (Url $url): string => $url->loc, $sitemap->sections()['season-2'] ?? []), 'A genre whose key ends in digits has a section all the same.');
        $other = new Sitemap();
        $provider->provide($other, 'artist');
        self::assertSame([], $other->sections());
        $season = new Sitemap('season-2');
        $provider->provide($season, 'season-2');
        self::assertSame(['season-2'], array_keys($season->sections()));

        $this->client->request('GET', '/balados/'.$first->resourceId.'/modifier');
        self::assertResponseStatusCodeSame(403);
        $this->signIn('ada');
        $this->client->request('GET', '/balados/ajouter');
        $this->client->submitForm('Enregistrer', ['listing[excerptTitle]' => 'Doublon', 'listing[slug]' => 'zebre']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('form ul li');
    }

    /**
     * A genre is listed a page at a time; a listing published without a slug has no page, and is listed nowhere.
     */
    public function testAGenreIsListedByPagesAndAListingWithoutSlugIsLeftOut(): void
    {
        $directory = $this->service(Directory::class);
        self::assertInstanceOf(Directory::class, $directory);
        $admin = new Actor('admin', true);

        foreach (['Cigale' => 'cigale', 'Abeille' => 'abeille', 'Bourdon' => 'bourdon', 'Sans adresse' => null] as $title => $slug) {
            $directory->create('podcast', 'fr', ['excerptTitle' => $title, 'slug' => $slug], $admin);
        }

        $site = $directory->create('larp_site', 'fr', ['excerptTitle' => 'Nulle part'], $admin);
        $directory->create('larp', 'fr', ['excerptTitle' => 'La Quête', 'slug' => 'la-quete', 'related' => ['played_at' => [$site->resourceId]]], $admin);

        $this->client->request('GET', '/balados');
        self::assertResponseIsSuccessful();
        self::assertSame(['Abeille', 'Bourdon'], $this->client->getCrawler()->filter('ul.phil-directory li a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSame(['/balados?page=2'], $this->client->getCrawler()->filter('nav.phil-directory-pages a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));

        $this->client->request('GET', '/balados?page=2');
        self::assertSame(['Cigale'], $this->client->getCrawler()->filter('ul.phil-directory li a')->each(static fn (Crawler $link): string => $link->text()));
        self::assertSame(['/balados'], $this->client->getCrawler()->filter('nav.phil-directory-pages a[rel="prev"]')->each(static fn (Crawler $link): string => (string) $link->attr('href')));
        self::assertSelectorNotExists('nav.phil-directory-pages a[rel="next"]');

        $this->client->request('GET', '/balados?page=3');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/balados?page=nope');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/balados?page=0');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/terrains');
        self::assertSelectorTextContains('ul.phil-directory', 'Aucune fiche');

        $this->client->request('GET', '/gn/la-quete');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('section[data-relation="played_at"]');
        self::assertSame(['Abeille', 'Bourdon', 'Cigale'], array_map(static fn (PublishedContent $content): string => $content->title(), $directory->published('podcast', 'fr')));
        self::assertSame(['Bourdon'], array_map(static fn (PublishedContent $content): string => $content->title(), $directory->published('podcast', 'fr', 1, 1)));
    }

    /**
     * An erased account leaves the listings it wrote or edited and every version it wrote; the listings stay.
     */
    public function testAnErasedUserLeavesTheDirectory(): void
    {
        $directory = $this->service(Directory::class);
        self::assertInstanceOf(Directory::class, $directory);
        $admin = new Actor('admin', true);
        $listing = $directory->create('podcast', 'fr', ['excerptTitle' => 'Les Rôlistes', 'slug' => 'les-rolistes'], new Actor('ada'));
        $directory->addEditor($listing, 'grace', new Actor('ada'));
        $directory->publish($listing, 'fr', $admin);
        $directory->edit($listing, 'fr', ['excerptTitle' => 'Les Rôlistes du mardi'], new Actor('grace'));
        $directory->publish($listing, 'fr', $admin);
        $directory->edit($listing, 'fr', ['excerptTitle' => 'Les Rôlistes du jeudi'], new Actor('ada'));

        self::assertSame([3, 2, 1], array_map(static fn (ListingVersion $version): int => $version->number, $directory->history($listing, 'fr')));
        self::assertSame(['ada', 'grace', 'ada'], array_map(static fn (ListingVersion $version): ?string => $version->editedBy, $directory->history($listing, 'fr')));

        self::assertSame(3, $directory->eraseUser('grace'), 'Its draft and live rows of version 2, and the listing it edits.');
        self::assertSame(['ada', null, 'ada'], array_map(static fn (ListingVersion $version): ?string => $version->editedBy, $directory->history($listing, 'fr')));
        self::assertSame([], $directory->listing($listing)->editors());

        self::assertSame(3, $directory->eraseUser('ada'));
        self::assertNull($directory->listing($listing)->author());
        self::assertSame([null, null, null], array_map(static fn (ListingVersion $version): ?string => $version->editedBy, $directory->history($listing, 'fr')));
        self::assertSame(0, $directory->eraseUser('ada'));
        self::assertSame('Les Rôlistes du jeudi', $directory->draft($listing, 'fr')['excerptTitle']);
    }

    /**
     * The listings that may point at one are found among the live rows of their genre: a draft is not, and a listing
     * published since without the relation no longer is.
     */
    public function testTheListingsThatPointAtOneAreLookedUpAmongTheLiveRows(): void
    {
        $directory = $this->service(Directory::class);
        $lookup = $this->service(DoctrineRelatedLookup::class);
        self::assertInstanceOf(Directory::class, $directory);
        self::assertInstanceOf(DoctrineRelatedLookup::class, $lookup);
        $admin = new Actor('admin', true);
        $forest = $directory->create('larp_site', 'fr', ['excerptTitle' => 'La Forêt', 'slug' => 'la-foret'], $admin);
        $castle = $directory->create('larp_site', 'fr', ['excerptTitle' => 'Le Château', 'slug' => 'le-chateau'], $admin);
        $podcast = $directory->create('podcast', 'fr', ['excerptTitle' => 'Les Rôlistes', 'slug' => 'les-rolistes'], $admin);
        $saga = $directory->create('larp', 'fr', ['excerptTitle' => 'La Saga', 'slug' => 'la-saga', 'related' => ['played_at' => [$castle->resourceId, $forest->resourceId], 'heard_on' => [$podcast->resourceId]]], $admin);
        $moved = $directory->create('larp', 'fr', ['excerptTitle' => 'Brume', 'slug' => 'brume', 'related' => ['played_at' => [$forest->resourceId]]], $admin);
        $directory->create('larp', 'fr', ['excerptTitle' => 'Brouillon', 'slug' => 'brouillon', 'related' => ['played_at' => [$forest->resourceId]]], new Actor('ada'));
        $directory->create('larp', 'fr', ['excerptTitle' => 'Seul', 'slug' => 'seul'], $admin);

        $found = $lookup->pointingAt('larp', 'played_at', $forest->resourceId);
        sort($found);
        $expected = [$saga->resourceId, $moved->resourceId];
        sort($expected);
        self::assertSame($expected, $found, 'the draft is left out');
        self::assertSame([$saga->resourceId], $lookup->pointingAt('larp', 'heard_on', $podcast->resourceId));
        self::assertSame([], $lookup->pointingAt('larp', 'heard_on', $forest->resourceId), 'under another relation');
        self::assertSame([], $lookup->pointingAt('larp_site', 'played_at', $forest->resourceId), 'of another genre');

        // Brume moves to the castle, published at once.
        $directory->edit($moved, 'fr', ['related' => ['played_at' => [$castle->resourceId]]], $admin);
        self::assertSame([$saga->resourceId], $lookup->pointingAt('larp', 'played_at', $forest->resourceId));
        self::assertSame([['GN joués ici', 'larp', ['La Saga']]], $this->relatedTitles($directory->related($forest, 'fr')));
        self::assertSame([['GN joués ici', 'larp', ['Brume', 'La Saga']]], $this->relatedTitles($directory->related($castle, 'fr')));
    }

    /**
     * Larps played at larp sites, several at one, and heard on one podcast: written on the larp, shown on both.
     */
    public function testListingsAreRelatedAsTheConfigurationSays(): void
    {
        $directory = $this->service(Directory::class);
        self::assertInstanceOf(Directory::class, $directory);
        $admin = new Actor('admin', true);
        $forest = $directory->create('larp_site', 'fr', ['excerptTitle' => 'La Forêt', 'slug' => 'la-foret'], $admin);
        $castle = $directory->create('larp_site', 'fr', ['excerptTitle' => 'Le Château', 'slug' => 'le-chateau'], $admin);
        $podcast = $directory->create('podcast', 'fr', ['excerptTitle' => 'Les Rôlistes', 'slug' => 'les-rolistes'], $admin);
        $saga = $directory->create('larp', 'fr', ['excerptTitle' => 'La Saga', 'slug' => 'la-saga', 'related' => ['played_at' => [$forest->resourceId, $castle->resourceId], 'heard_on' => [$podcast->resourceId], 'gone' => ['x']]], $admin);
        $directory->create('larp', 'fr', ['excerptTitle' => 'Brume', 'slug' => 'brume', 'related' => ['played_at' => [$forest->resourceId]]], $admin);
        $directory->create('larp', 'fr', ['excerptTitle' => 'Brouillon', 'slug' => 'brouillon', 'related' => ['played_at' => [$forest->resourceId]]], new Actor('ada'));

        self::assertSame(['played_at' => [$forest->resourceId, $castle->resourceId], 'heard_on' => [$podcast->resourceId]], $directory->draft($saga, 'fr')['related']);
        self::assertSame([['Joué à', 'larp_site', ['La Forêt', 'Le Château']], ['Entendu sur', 'podcast', ['Les Rôlistes']]], $this->relatedTitles($directory->related($saga, 'fr')));
        self::assertSame([['GN joués ici', 'larp', ['Brume', 'La Saga']]], $this->relatedTitles($directory->related($forest, 'fr')), 'the draft is not shown');
        self::assertSame([['GN entendus ici', 'larp', ['La Saga']]], $this->relatedTitles($directory->related($podcast, 'fr')));
        self::assertSame([], $directory->related($saga, 'en'));
        self::assertSame(['played_at', 'heard_on'], array_map(static fn (Relation $relation): string => $relation->key, $directory->relationsFrom('larp')));

        $this->client->request('GET', '/terrains/la-foret');
        self::assertSelectorTextContains('section[data-relation="played_at"] h2', 'GN joués ici');
        self::assertSame(['/gn/brume', '/gn/la-saga'], $this->client->getCrawler()->filter('section[data-relation="played_at"] a')->each(static fn (Crawler $link): string => (string) $link->attr('href')));

        $this->signIn('admin', 'ROLE_ADMIN');
        $this->client->request('GET', '/gn/'.$saga->resourceId.'/modifier');
        self::assertSame(['La Forêt', 'Le Château'], $this->client->getCrawler()->filter('select[name="listing[related][played_at][]"] option[selected]')->each(static fn (Crawler $option): string => $option->text()));
        self::assertSame(['', 'Les Rôlistes'], $this->client->getCrawler()->filter('select[name="listing[related][heard_on]"] option')->each(static fn (Crawler $option): string => $option->text()));
        $this->client->submitForm('Enregistrer', ['listing[related][played_at]' => [$castle->resourceId], 'listing[related][heard_on]' => '']);
        self::assertResponseRedirects();
        self::assertSame(['played_at' => [$castle->resourceId]], $directory->draft($saga, 'fr')['related']);

        // A site still in draft, and another of the same name: each stays a choice of its own, and saving keeps them.
        $hidden = $directory->create('larp_site', 'fr', ['excerptTitle' => 'Le Château', 'slug' => 'le-chateau-2'], new Actor('ada'));
        $directory->edit($saga, 'fr', ['related' => ['played_at' => [$castle->resourceId, $hidden->resourceId]]], $admin);
        $this->client->request('GET', '/gn/'.$saga->resourceId.'/modifier');
        self::assertSame([$castle->resourceId, $hidden->resourceId], $this->client->getCrawler()->filter('select[name="listing[related][played_at][]"] option[selected]')->each(static fn (Crawler $option): string => (string) $option->attr('value')));
        self::assertSame(['Le Château', 'Le Château'], $this->client->getCrawler()->filter('select[name="listing[related][played_at][]"] option[selected]')->each(static fn (Crawler $option): string => $option->text()));
        $this->client->submitForm('Enregistrer');
        self::assertSame(['played_at' => [$castle->resourceId, $hidden->resourceId]], $directory->draft($saga, 'fr')['related']);
        self::assertSame([['Joué à', 'larp_site', ['Le Château']]], $this->relatedTitles($directory->related($saga, 'fr')), 'the draft site is not shown');
        $directory->edit($saga, 'fr', ['related' => ['played_at' => [$castle->resourceId]]], $admin);

        $this->client->request('GET', '/terrains/ajouter');
        self::assertSelectorNotExists('select[name^="listing[related]"]');

        foreach ([
            [['heard_on' => [$podcast->resourceId, $podcast->resourceId.'x']], 'one listing at most'],
            [['played_at' => [$podcast->resourceId]], 'relates listings of the genre "larp_site"'],
            [['played_at' => [$saga->resourceId]], 'not related to itself'],
            [['played_at' => ['00000000-0000-7000-8000-000000000000']], 'has no listing 00000000-0000-7000-8000-000000000000'],
            [['played_at' => ['no such']], 'has no listing no such'],
            ['nope', 'lists of identifiers by relation'],
        ] as [$related, $message]) {
            try {
                $directory->edit($saga, 'fr', ['related' => $related], $admin);
                self::fail($message);
            } catch (ListingRefused $refused) {
                self::assertStringContainsString($message, $refused->getMessage());
            }
        }

        try {
            $directory->create('larp_site', 'fr', ['excerptTitle' => 'Plaine', 'related' => ['played_at' => [$forest->resourceId]]], $admin);
            self::fail('A relation of another genre.');
        } catch (ListingRefused $listingRefused) {
            self::assertSame('The relation "played_at" is not written on a listing of the genre "larp_site".', $listingRefused->getMessage());
        }

        $directory->edit($saga, 'fr', ['related' => null, 'excerptTitle' => 'La Saga'], $admin);
        self::assertSame([], $directory->draft($saga, 'fr')['related']);
    }

    /**
     * The author decides who finds a listing: a listing kept out of search engines leaves the sitemap and tells robots
     * so; an unlisted one also leaves the list of its genre and the related listings of others, and stays at its
     * address.
     */
    public function testAListingIsFoundAsItsAuthorDecides(): void
    {
        $directory = $this->service(Directory::class);
        $provider = $this->service('test.directory_urls');
        self::assertInstanceOf(Directory::class, $directory);
        self::assertInstanceOf(DirectoryUrlProvider::class, $provider);
        $admin = new Actor('admin', true);
        $site = $directory->create('larp_site', 'fr', ['excerptTitle' => 'La Forêt', 'slug' => 'la-foret'], $admin);
        $hive = $directory->create('podcast', 'fr', ['excerptTitle' => 'Abeille', 'slug' => 'abeille'], new Actor('ada'));
        $directory->publish($hive, 'fr', $admin);
        $directory->create('podcast', 'fr', ['excerptTitle' => 'Bourdon', 'slug' => 'bourdon'], $admin);

        $saga = $directory->create('larp', 'fr', ['excerptTitle' => 'La Saga', 'slug' => 'la-saga', 'related' => ['played_at' => [$site->resourceId], 'heard_on' => [$hive->resourceId]]], $admin);

        $titles = static fn (string $genre, Reader $reader = Reader::SiteListings): array => array_map(static fn (PublishedContent $content): string => $content->title(), $directory->published($genre, 'fr', reader: $reader));
        $sitemap = static function () use ($provider): array {
            $sitemap = new Sitemap();
            $provider->provide($sitemap, 'podcast');

            return array_map(static fn (Url $url): string => $url->loc, $sitemap->sections()['podcast'] ?? []);
        };

        self::assertSame(Indexing::Listed, $directory->listing($hive)->indexing());
        self::assertSame(['http://localhost/balados', 'http://localhost/balados/abeille', 'http://localhost/balados/bourdon'], $sitemap());
        $this->client->request('GET', '/balados/abeille');
        self::assertNull($this->client->getResponse()->headers->get('X-Robots-Tag'));
        self::assertSelectorNotExists('meta[name="robots"]');
        self::assertSame(Indexing::Listed, $this->carriedIndexing());

        // Ada keeps her podcast out of search engines, from its page of editors.
        $this->signIn('ada');
        $this->client->request('GET', '/balados/'.$hive->resourceId.'/redacteurs');
        self::assertSame(['listed'], $this->client->getCrawler()->filter('input[name="editors[indexing]"][checked]')->each(static fn (Crawler $input): string => (string) $input->attr('value')));
        $this->client->submitForm('Enregistrer', ['editors[indexing]' => 'not_indexed']);
        self::assertResponseRedirects('/balados/'.$hive->resourceId.'/redacteurs');
        self::assertSame(Indexing::NotIndexed, $directory->listing($hive)->indexing());
        self::assertTrue($directory->listing($hive)->isSiteListed());

        $this->signIn(null);
        self::assertSame(['http://localhost/balados', 'http://localhost/balados/bourdon'], $sitemap());
        self::assertSame(['Abeille', 'Bourdon'], $titles('podcast'));
        self::assertSame(['Bourdon'], $titles('podcast', Reader::Sitemap));
        $this->client->request('GET', '/balados/abeille');
        self::assertResponseIsSuccessful();
        self::assertSame('noindex, noarchive', $this->client->getResponse()->headers->get('X-Robots-Tag'));
        self::assertSame('noindex, noarchive', $this->client->getCrawler()->filter('meta[name="robots"]')->attr('content'));
        self::assertSame(Indexing::NotIndexed, $this->carriedIndexing());
        self::assertSame([['Joué à', 'larp_site', ['La Forêt']], ['Entendu sur', 'podcast', ['Abeille']]], $this->relatedTitles($directory->related($saga, 'fr')));

        // Then unlists it: only its address leads to it.
        $directory->changeIndexing($hive, Indexing::Unlisted, new Actor('ada'));
        self::assertFalse($directory->listing($hive)->isSiteListed());
        self::assertSame(['Bourdon'], $titles('podcast'));
        self::assertSame([['Joué à', 'larp_site', ['La Forêt']]], $this->relatedTitles($directory->related($saga, 'fr')));
        $this->client->request('GET', '/balados');
        self::assertSame(['Bourdon'], $this->client->getCrawler()->filter('ul.phil-directory li a')->each(static fn (Crawler $link): string => $link->text()));
        $this->client->request('GET', '/balados/abeille');
        self::assertResponseIsSuccessful();
        self::assertSame('noindex, noarchive', $this->client->getResponse()->headers->get('X-Robots-Tag'));

        // A site that is unlisted leaves the larps that name it, and the larp leaves the site's page if it is.
        $directory->changeIndexing($saga, Indexing::Unlisted, $admin);
        self::assertSame([], $directory->related($site, 'fr'));

        foreach ([new Actor('grace'), new Actor(null)] as $stranger) {
            try {
                $directory->changeIndexing($hive, Indexing::Listed, $stranger);
                self::fail('Only the author and the administrators decide.');
            } catch (ListingRefused $refused) {
                self::assertSame('Only the author of a listing and the administrators decide who finds it.', $refused->getMessage());
            }
        }

        $directory->addEditor($hive, 'grace', new Actor('ada'));
        $this->signIn('grace');
        $this->client->request('GET', '/balados/'.$hive->resourceId.'/redacteurs');
        self::assertResponseStatusCodeSame(403);
        $directory->changeIndexing($hive, Indexing::Listed, $admin);
        self::assertSame(['Abeille', 'Bourdon'], $titles('podcast'));
    }

    /**
     * The choice of the listing the last page carried on its request, read again after each request.
     *
     * @phpstan-impure
     */
    private function carriedIndexing(): mixed
    {
        return $this->client->getRequest()->attributes->get(IndexingPolicy::REQUEST_ATTRIBUTE);
    }

    /**
     * @param list<RelatedListings> $related
     *
     * @return list<array{string, string, list<string>}>
     */
    private function relatedTitles(array $related): array
    {
        return array_map(static fn (RelatedListings $group): array => [$group->title(), $group->genre->key, array_map(static fn (PublishedContent $content): string => $content->title(), $group->listings)], $related);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
