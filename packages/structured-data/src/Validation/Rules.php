<?php

declare(strict_types=1);

namespace Phil\StructuredData\Validation;

/**
 * The rules the validator applies unless it is given others: for each common schema.org type, what a search engine
 * needs to read a node as that type, and what it shows when the node has it.
 */
final class Rules
{
    /**
     * Types that need nothing of their own beyond their parent's.
     *
     * @var array<string, string>
     */
    private const array SUBTYPES = [
        'Corporation' => 'Organization',
        'EducationalOrganization' => 'Organization',
        'NGO' => 'Organization',
        'PerformingGroup' => 'Organization',
        'Store' => 'LocalBusiness',
        'Restaurant' => 'LocalBusiness',
        'CafeOrCoffeeShop' => 'LocalBusiness',
        'BarOrPub' => 'LocalBusiness',
        'EntertainmentBusiness' => 'LocalBusiness',
        'BusinessEvent' => 'Event',
        'EducationEvent' => 'Event',
        'ExhibitionEvent' => 'Event',
        'Festival' => 'Event',
        'MusicEvent' => 'Event',
        'SocialEvent' => 'Event',
        'SportsEvent' => 'Event',
        'TheaterEvent' => 'Event',
        'NewsArticle' => 'Article',
        'BlogPosting' => 'Article',
        'TechArticle' => 'Article',
        'AboutPage' => 'WebPage',
        'CollectionPage' => 'WebPage',
        'ContactPage' => 'WebPage',
        'ItemPage' => 'WebPage',
        'ProfilePage' => 'WebPage',
        'WebApplication' => 'SoftwareApplication',
        'MobileApplication' => 'SoftwareApplication',
        'VideoGame' => 'SoftwareApplication',
    ];

    /**
     * @return list<TypeRules>
     */
    public static function schemaOrg(): array
    {
        $rules = [
            new TypeRules('CreativeWork', recommended: ['name', 'author']),
            new TypeRules('WebSite', ['name', 'url'], ['inLanguage', 'publisher']),
            new TypeRules('WebPage', ['url'], ['name', 'inLanguage', 'isPartOf']),
            new TypeRules('FAQPage', ['mainEntity'], parent: 'WebPage'),
            new TypeRules('Question', ['name', 'acceptedAnswer']),
            new TypeRules('Answer', ['text']),
            new TypeRules('BreadcrumbList', ['itemListElement']),
            new TypeRules('ListItem', ['position', 'name'], ['item']),
            new TypeRules('Organization', ['name'], ['url', 'logo']),
            new TypeRules('LocalBusiness', ['address'], ['telephone', 'url', 'image', 'openingHoursSpecification', 'geo'], 'Organization'),
            new TypeRules('Person', ['name'], ['url', 'sameAs']),
            new TypeRules('Event', ['name', 'startDate', 'location'], ['endDate', 'eventStatus', 'description', 'image', 'offers', 'organizer']),
            new TypeRules('Place', [['name', 'address']], ['address']),
            new TypeRules('PostalAddress', recommended: ['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry']),
            new TypeRules('Product', ['name', ['offers', 'review', 'aggregateRating']], ['image', 'description', 'sku', 'brand']),
            new TypeRules('Offer', ['price', 'priceCurrency'], ['availability', 'url']),
            new TypeRules('Book', ['name', 'author'], ['isbn', 'url', 'inLanguage', 'bookFormat'], 'CreativeWork'),
            new TypeRules('Article', ['headline'], ['author', 'datePublished', 'dateModified', 'image', 'publisher'], 'CreativeWork'),
            new TypeRules('SoftwareSourceCode', ['name', 'codeRepository'], ['programmingLanguage', 'license', 'description', 'version'], 'CreativeWork'),
            new TypeRules('SoftwareApplication', ['name'], ['applicationCategory', 'operatingSystem', 'offers', 'softwareVersion']),
            new TypeRules('ImageObject', [['contentUrl', 'url']], ['caption', 'width', 'height', 'license', 'creator']),
        ];

        foreach (self::SUBTYPES as $type => $parent) {
            $rules[] = new TypeRules($type, parent: $parent);
        }

        return $rules;
    }
}
