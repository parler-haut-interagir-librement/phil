<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Locale;

/**
 * The locale packs the bundle ships: French and English Canada, France, the United States, the United Kingdom,
 * Australia, Germany and Spain.
 *
 * Names are common ones of each country, streets and cities ordinary ones; a combination may match a real person
 * or place, which is why an anonymized copy is still kept from the public. Telephone patterns follow the fictional
 * ranges of each regulator; Germany and Spain reserve none, so their packs carry no telephone pattern.
 */
final class BuiltInLocales
{
    private const array QUEBEC_GIVEN = ['Alexis', 'Amélie', 'Antoine', 'Camille', 'Chloé', 'Félix', 'Gabrielle', 'Jacob', 'Léa', 'Louis', 'Maélie', 'Mathis', 'Noah', 'Olivia', 'Rosalie', 'Samuel', 'Thomas', 'Zoé'];

    private const array QUEBEC_FAMILY = ['Bélanger', 'Bergeron', 'Bouchard', 'Côté', 'Fortin', 'Gagné', 'Gagnon', 'Gauthier', 'Lavoie', 'Leblanc', 'Lévesque', 'Morin', 'Ouellet', 'Pelletier', 'Roy', 'Tremblay'];

    private const array FRANCE_GIVEN = ['Adèle', 'Arthur', 'Clément', 'Élise', 'Emma', 'Gabriel', 'Hugo', 'Inès', 'Jade', 'Jules', 'Louise', 'Lucas', 'Manon', 'Nathan', 'Raphaël', 'Sarah'];

    private const array FRANCE_FAMILY = ['Bernard', 'Bertrand', 'Durand', 'Dubois', 'Fournier', 'Girard', 'Lambert', 'Laurent', 'Lefebvre', 'Leroy', 'Martin', 'Moreau', 'Petit', 'Richard', 'Robert', 'Simon'];

    private const array ENGLISH_GIVEN = ['Amelia', 'Benjamin', 'Charlotte', 'Daniel', 'Ella', 'Ethan', 'Grace', 'Harper', 'Henry', 'Isla', 'Jack', 'Liam', 'Lucy', 'Mason', 'Oliver', 'Sophie'];

    private const array ENGLISH_FAMILY = ['Anderson', 'Brown', 'Campbell', 'Clarke', 'Davies', 'Evans', 'Johnson', 'MacDonald', 'Miller', 'Smith', 'Taylor', 'Thompson', 'Walker', 'White', 'Wilson', 'Wright'];

    private const array GERMAN_GIVEN = ['Anna', 'Ben', 'Clara', 'Elias', 'Emilia', 'Felix', 'Hannah', 'Jonas', 'Lea', 'Leon', 'Lina', 'Luis', 'Marie', 'Noah', 'Paul', 'Sophie'];

    private const array GERMAN_FAMILY = ['Becker', 'Fischer', 'Hoffmann', 'Koch', 'Meyer', 'Müller', 'Richter', 'Schäfer', 'Schmidt', 'Schneider', 'Schulz', 'Wagner', 'Weber', 'Wolf', 'Zimmermann', 'Klein'];

    private const array SPANISH_GIVEN = ['Alejandro', 'Carmen', 'Daniel', 'Elena', 'Hugo', 'Isabel', 'Javier', 'Laura', 'Lucía', 'Manuel', 'María', 'Martín', 'Pablo', 'Paula', 'Sofía', 'Sergio'];

    private const array SPANISH_FAMILY = ['Díaz', 'Fernández', 'García', 'Gómez', 'González', 'Hernández', 'Jiménez', 'López', 'Martín', 'Martínez', 'Moreno', 'Muñoz', 'Pérez', 'Rodríguez', 'Ruiz', 'Sánchez'];

    private const array ENGLISH_STREETS = ['Cedar', 'Church', 'Elm', 'High', 'Hillside', 'King', 'Lake', 'Maple', 'Mill', 'Oak', 'Park', 'Queen', 'River', 'Station', 'Victoria', 'Willow'];

    /**
     * @return list<LocalePack>
     */
    public static function all(): array
    {
        return [
            new LocalePack(
                'fr_CA',
                self::QUEBEC_GIVEN,
                self::QUEBEC_FAMILY,
                ['des Érables', 'du Couvent', 'Principale', 'Saint-Jean', 'Notre-Dame', 'des Pins', 'du Moulin', 'Champlain', 'de la Gare', 'Sainte-Catherine'],
                ['{number}, rue {street}', '{number}, avenue {street}', '{number}, boulevard {street}', '{number}, chemin {street}'],
                ['Montréal', 'Québec', 'Laval', 'Gatineau', 'Longueuil', 'Sherbrooke', 'Trois-Rivières', 'Saguenay', 'Lévis', 'Rimouski'],
                ['Québec'],
                ['H#? #?#', 'G#? #?#', 'J#? #?#'],
                ['514 555-01##', '418 555-01##', '438 555-01##', '450 555-01##', '819 555-01##'],
                'CA',
                'Canada',
                '{line}, {city} (Québec) {postal_code}',
            ),
            new LocalePack(
                'en_CA',
                self::ENGLISH_GIVEN,
                self::ENGLISH_FAMILY,
                self::ENGLISH_STREETS,
                ['{number} {street} Street', '{number} {street} Avenue', '{number} {street} Road', '{number} {street} Drive'],
                ['Toronto', 'Vancouver', 'Calgary', 'Ottawa', 'Edmonton', 'Winnipeg', 'Halifax', 'Victoria', 'Regina', 'Saskatoon'],
                ['ON', 'BC', 'AB', 'MB', 'NS', 'SK'],
                ['M#? #?#', 'V#? #?#', 'T#? #?#', 'K#? #?#'],
                ['416 555-01##', '604 555-01##', '403 555-01##', '613 555-01##', '902 555-01##'],
                'CA',
                'Canada',
                '{line}, {city} {region} {postal_code}',
            ),
            new LocalePack(
                'fr_FR',
                self::FRANCE_GIVEN,
                self::FRANCE_FAMILY,
                ['de la République', 'Victor-Hugo', 'Jean-Jaurès', 'de la Paix', 'Pasteur', 'des Lilas', 'du Château', 'de la Mairie', 'Gambetta', 'des Écoles'],
                ['{number}, rue {street}', '{number}, avenue {street}', '{number}, boulevard {street}', '{number}, place {street}'],
                ['Paris', 'Lyon', 'Marseille', 'Toulouse', 'Nantes', 'Bordeaux', 'Lille', 'Rennes', 'Strasbourg', 'Montpellier'],
                ['Bretagne', 'Normandie', 'Occitanie', 'Grand Est', 'Hauts-de-France', 'Île-de-France', 'Nouvelle-Aquitaine', 'Pays de la Loire'],
                ['#####'],
                ['01 99 00 ## ##', '02 61 91 ## ##', '03 53 01 ## ##', '04 65 71 ## ##', '05 36 49 ## ##', '06 39 98 ## ##'],
                'FR',
                'France',
                '{line}, {postal_code} {city}',
            ),
            new LocalePack(
                'en_US',
                self::ENGLISH_GIVEN,
                self::ENGLISH_FAMILY,
                self::ENGLISH_STREETS,
                ['{number} {street} Street', '{number} {street} Avenue', '{number} {street} Boulevard', '{number} {street} Lane'],
                ['Springfield', 'Riverside', 'Franklin', 'Greenville', 'Bristol', 'Clinton', 'Fairview', 'Salem', 'Madison', 'Georgetown'],
                ['CA', 'IL', 'MA', 'NY', 'OR', 'TX', 'WA'],
                ['#####'],
                ['212 555-01##', '312 555-01##', '415 555-01##', '617 555-01##', '206 555-01##'],
                'US',
                'United States',
                '{line}, {city}, {region} {postal_code}',
            ),
            new LocalePack(
                'en_GB',
                self::ENGLISH_GIVEN,
                self::ENGLISH_FAMILY,
                self::ENGLISH_STREETS,
                ['{number} {street} Street', '{number} {street} Road', '{number} {street} Lane', '{number} {street} Close'],
                ['London', 'Birmingham', 'Manchester', 'Leeds', 'Bristol', 'Sheffield', 'Nottingham', 'Leicester', 'Cardiff', 'Glasgow'],
                ['England', 'Scotland', 'Wales', 'Northern Ireland'],
                ['?# #??', '?## #??', '??# #??'],
                ['07700 900###', '020 7946 0###', '0113 496 0###', '0161 496 0###'],
                'GB',
                'United Kingdom',
                '{line}, {city} {postal_code}',
            ),
            new LocalePack(
                'en_AU',
                self::ENGLISH_GIVEN,
                self::ENGLISH_FAMILY,
                self::ENGLISH_STREETS,
                ['{number} {street} Street', '{number} {street} Road', '{number} {street} Parade', '{number} {street} Crescent'],
                ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Hobart', 'Canberra', 'Darwin', 'Geelong', 'Newcastle'],
                ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'],
                ['####'],
                ['02 5550 ####', '03 7010 ####', '07 5550 ####', '08 7010 ####'],
                'AU',
                'Australia',
                '{line}, {city} {region} {postal_code}',
            ),
            new LocalePack(
                'de_DE',
                self::GERMAN_GIVEN,
                self::GERMAN_FAMILY,
                ['Haupt', 'Schul', 'Garten', 'Bahnhof', 'Dorf', 'Berg', 'Linden', 'Kirch', 'Wald', 'Ring'],
                ['{street}straße {number}', '{street}weg {number}', '{street}gasse {number}'],
                ['Berlin', 'Hamburg', 'München', 'Köln', 'Frankfurt am Main', 'Stuttgart', 'Düsseldorf', 'Leipzig', 'Dresden', 'Bremen'],
                ['Bayern', 'Berlin', 'Hamburg', 'Hessen', 'Niedersachsen', 'Nordrhein-Westfalen', 'Sachsen'],
                ['#####'],
                [],
                'DE',
                'Deutschland',
                '{line}, {postal_code} {city}',
            ),
            new LocalePack(
                'es_ES',
                self::SPANISH_GIVEN,
                self::SPANISH_FAMILY,
                ['Mayor', 'del Sol', 'de la Iglesia', 'Real', 'de Cervantes', 'del Carmen', 'Nueva', 'de la Paz', 'San Juan', 'del Mar'],
                ['Calle {street}, {number}', 'Avenida {street}, {number}', 'Plaza {street}, {number}'],
                ['Madrid', 'Barcelona', 'Valencia', 'Sevilla', 'Zaragoza', 'Málaga', 'Bilbao', 'Murcia', 'Palma', 'Granada'],
                ['Andalucía', 'Aragón', 'Cataluña', 'Comunidad de Madrid', 'Comunidad Valenciana', 'Galicia', 'País Vasco'],
                ['#####'],
                [],
                'ES',
                'España',
                '{line}, {postal_code} {city}',
            ),
        ];
    }
}
