<?php

declare(strict_types=1);

namespace Phil\StructuredData;

use DateTimeInterface;
use Phil\StructuredData\Exception\InvalidStructuredData;
use Phil\StructuredData\Type\Article;
use Phil\StructuredData\Type\BreadcrumbList;
use Phil\StructuredData\Type\Event;
use Phil\StructuredData\Type\EventAttendanceMode;
use Phil\StructuredData\Type\EventStatus;
use Phil\StructuredData\Type\ImageObject;
use Phil\StructuredData\Type\ItemAvailability;
use Phil\StructuredData\Type\Offer;
use Phil\StructuredData\Type\Organization;
use Phil\StructuredData\Type\Person;
use Phil\StructuredData\Type\Place;
use Phil\StructuredData\Type\PostalAddress;
use Phil\StructuredData\Type\Product;
use Phil\StructuredData\Type\WebPage;
use Phil\StructuredData\Type\WebSite;
use Phil\StructuredData\Validation\Issue;
use Phil\StructuredData\Validation\Validator;

/**
 * The types the sites of an application give most, each built by its factory of `Type\` and checked at once: a node
 * that lacks what its type needs, or holds a value in a form nobody reads — a date that is not ISO 8601, an address
 * that is not absolute, a currency that is not ISO 4217 —, is refused here rather than reported by the validator
 * later. The warnings of the validator — a recommended property missing, a reference to a node elsewhere — are left
 * to it. Any other type is built with `Node::of()` or `Type\Thing::of()`.
 *
 * The validator holds no state of a request: it is built once and shared. An address on `localhost` or under
 * `.localhost` is refused, as the validator refuses it, unless `allowLocalhost(true)` was called, as an application
 * does once in development and in its tests.
 */
final class Schema
{
    private static ?Validator $validator = null;

    /**
     * Whether the factories take an address on `localhost` or under `.localhost`, for every node built after: `true` in
     * development and tests only. An IP address stays refused.
     */
    public static function allowLocalhost(bool $allow): void
    {
        self::$validator = new Validator(allowLocalhost: $allow);
    }

    /**
     * An `Article`, or one of its kinds — `NewsArticle`, `BlogPosting`, `TechArticle` —, with its headline.
     *
     * @param Node|Reference|list<Node|Reference>|null               $author
     * @param string|Node|Reference|list<string|Node|Reference>|null $image
     * @param array<string, mixed>                                   $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function article(
        string|LanguageString $headline,
        ?string $id = null,
        Node|Reference|array|null $author = null,
        DateTimeInterface|string|null $datePublished = null,
        DateTimeInterface|string|null $dateModified = null,
        string|Node|Reference|array|null $image = null,
        Node|Reference|null $publisher = null,
        ?string $url = null,
        string|LanguageString|null $description = null,
        ?string $inLanguage = null,
        Node|Reference|null $mainEntityOfPage = null,
        string $type = 'Article',
        array $properties = [],
    ): Node {
        return self::validated(Article::of(headline: $headline, id: $id, author: $author, datePublished: $datePublished, dateModified: $dateModified, image: $image, publisher: $publisher, url: $url, description: $description, inLanguage: $inLanguage, mainEntityOfPage: $mainEntityOfPage, type: $type, properties: $properties));
    }

    /**
     * A `Product`, with its name and at least one offer, review or aggregate rating.
     *
     * @param Node|Reference|list<Node|Reference>|null $offers
     * @param array<string, mixed>                     $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function product(
        string|LanguageString $name,
        ?string $id = null,
        string|LanguageString|null $description = null,
        string|Node|Reference|null $image = null,
        ?string $sku = null,
        ?string $gtin = null,
        string|Node|Reference|null $brand = null,
        Node|Reference|array|null $offers = null,
        ?string $url = null,
        array $properties = [],
    ): Node {
        return self::validated(Product::of(name: $name, id: $id, description: $description, image: $image, sku: $sku, gtin: $gtin, brand: $brand, offers: $offers, url: $url, properties: $properties));
    }

    /**
     * An `Offer`, with its price and its currency.
     *
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function offer(
        string|int|float $price,
        string $priceCurrency,
        ?string $id = null,
        ?ItemAvailability $availability = null,
        ?string $url = null,
        DateTimeInterface|string|null $priceValidUntil = null,
        Node|Reference|null $seller = null,
        array $properties = [],
    ): Node {
        return self::validated(Offer::of(price: $price, priceCurrency: $priceCurrency, id: $id, availability: $availability, url: $url, priceValidUntil: $priceValidUntil, seller: $seller, properties: $properties));
    }

    /**
     * An `Event`, or one of its kinds, with its name, its start and its place.
     *
     * @param Node|Reference|list<Node|Reference>|null $offers
     * @param Node|Reference|list<Node|Reference>|null $performer
     * @param array<string, mixed>                     $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function event(
        string|LanguageString $name,
        DateTimeInterface|string $startDate,
        string|Node|Reference $location,
        ?string $id = null,
        DateTimeInterface|string|null $endDate = null,
        string|LanguageString|null $description = null,
        ?string $url = null,
        string|Node|Reference|null $image = null,
        ?EventStatus $status = null,
        ?EventAttendanceMode $attendanceMode = null,
        Node|Reference|array|null $offers = null,
        Node|Reference|null $organizer = null,
        Node|Reference|array|null $performer = null,
        ?string $inLanguage = null,
        string $type = 'Event',
        array $properties = [],
    ): Node {
        return self::validated(Event::of(name: $name, startDate: $startDate, location: $location, id: $id, endDate: $endDate, description: $description, url: $url, image: $image, status: $status, attendanceMode: $attendanceMode, offers: $offers, organizer: $organizer, performer: $performer, inLanguage: $inLanguage, type: $type, properties: $properties));
    }

    /**
     * A `Place`, with its name or its address.
     *
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function place(
        string|LanguageString|null $name = null,
        string|Node|Reference|null $address = null,
        ?string $id = null,
        ?string $url = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $telephone = null,
        array $properties = [],
    ): Node {
        return self::validated(Place::of(name: $name, address: $address, id: $id, url: $url, latitude: $latitude, longitude: $longitude, telephone: $telephone, properties: $properties));
    }

    /**
     * A `PostalAddress`.
     *
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function postalAddress(
        ?string $streetAddress = null,
        ?string $addressLocality = null,
        ?string $addressRegion = null,
        ?string $postalCode = null,
        ?string $addressCountry = null,
        ?string $postOfficeBoxNumber = null,
        ?string $id = null,
        array $properties = [],
    ): Node {
        return self::validated(PostalAddress::of(streetAddress: $streetAddress, addressLocality: $addressLocality, addressRegion: $addressRegion, postalCode: $postalCode, addressCountry: $addressCountry, postOfficeBoxNumber: $postOfficeBoxNumber, id: $id, properties: $properties));
    }

    /**
     * An `Organization`, or one of its kinds, with its name.
     *
     * @param list<string>         $sameAs     its pages elsewhere: an encyclopedia, its social profiles
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function organization(
        string|LanguageString $name,
        ?string $url = null,
        ?string $id = null,
        string|Node|Reference|null $logo = null,
        string|LanguageString|null $description = null,
        ?string $legalName = null,
        ?string $email = null,
        ?string $telephone = null,
        Node|Reference|null $address = null,
        array $sameAs = [],
        string $type = 'Organization',
        array $properties = [],
    ): Node {
        return self::validated(Organization::of(name: $name, url: $url, id: $id, logo: $logo, description: $description, legalName: $legalName, email: $email, telephone: $telephone, address: $address, sameAs: $sameAs, type: $type, properties: $properties));
    }

    /**
     * A `Person`, with a name.
     *
     * @param list<string>         $sameAs     the person's pages elsewhere: profiles, an encyclopedia
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function person(
        string|LanguageString $name,
        ?string $url = null,
        ?string $id = null,
        string|Node|Reference|null $image = null,
        string|LanguageString|null $jobTitle = null,
        Node|Reference|null $worksFor = null,
        ?string $email = null,
        array $sameAs = [],
        array $properties = [],
    ): Node {
        return self::validated(Person::of(name: $name, url: $url, id: $id, image: $image, jobTitle: $jobTitle, worksFor: $worksFor, email: $email, sameAs: $sameAs, properties: $properties));
    }

    /**
     * A `WebSite`, with its name and its address.
     *
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function webSite(
        string|LanguageString $name,
        string $url,
        ?string $id = null,
        string|LanguageString|null $alternateName = null,
        string|LanguageString|null $description = null,
        ?string $inLanguage = null,
        Node|Reference|null $publisher = null,
        ?string $searchUrl = null,
        array $properties = [],
    ): Node {
        return self::validated(WebSite::of(name: $name, url: $url, id: $id, alternateName: $alternateName, description: $description, inLanguage: $inLanguage, publisher: $publisher, searchUrl: $searchUrl, properties: $properties));
    }

    /**
     * A `WebPage`, or one of its kinds, with its address.
     *
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function webPage(
        string $url,
        ?string $id = null,
        string|LanguageString|null $name = null,
        string|LanguageString|null $description = null,
        ?string $inLanguage = null,
        Node|Reference|null $isPartOf = null,
        Node|Reference|null $breadcrumb = null,
        string|Node|Reference|null $primaryImageOfPage = null,
        DateTimeInterface|string|null $datePublished = null,
        DateTimeInterface|string|null $dateModified = null,
        string $type = 'WebPage',
        array $properties = [],
    ): Node {
        return self::validated(WebPage::of(url: $url, id: $id, name: $name, description: $description, inLanguage: $inLanguage, isPartOf: $isPartOf, breadcrumb: $breadcrumb, primaryImageOfPage: $primaryImageOfPage, datePublished: $datePublished, dateModified: $dateModified, type: $type, properties: $properties));
    }

    /**
     * A `BreadcrumbList`, with at least one page.
     *
     * @param list<array{name: string|LanguageString, url?: ?string}> $items from the top of the site to the current page
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function breadcrumbList(array $items, ?string $id = null): Node
    {
        return self::validated(BreadcrumbList::of(items: $items, id: $id));
    }

    /**
     * An `ImageObject`, with the address of its file.
     *
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     *
     * @throws InvalidStructuredData when a value is refused, or the node lacks what its type needs
     */
    public static function imageObject(
        string $contentUrl,
        ?string $id = null,
        string|LanguageString|null $caption = null,
        ?int $width = null,
        ?int $height = null,
        ?string $license = null,
        Node|Reference|null $creator = null,
        string|LanguageString|null $creditText = null,
        ?string $url = null,
        array $properties = [],
    ): Node {
        return self::validated(ImageObject::of(contentUrl: $contentUrl, id: $id, caption: $caption, width: $width, height: $height, license: $license, creator: $creator, creditText: $creditText, url: $url, properties: $properties));
    }

    /**
     * The node, once the validator finds no error in it nor in the nodes it holds.
     *
     * @throws InvalidStructuredData when it does
     */
    private static function validated(Node $node): Node
    {
        $errors = (self::$validator ??= new Validator())->validate(Graph::of($node))->errors();

        if ([] !== $errors) {
            throw InvalidStructuredData::because(implode(' ', array_map(static fn (Issue $issue): string => $issue->message, $errors)));
        }

        return $node;
    }
}
