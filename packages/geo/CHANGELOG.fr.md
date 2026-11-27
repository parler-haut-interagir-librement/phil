<!-- phil-dev source-sha256=c411f9f5f7f53969805bc1e303ce0b6cfab66f373cf9b3819c3e90054ad9275f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/geo` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-25

### Ajouté

- `Geocoding\Timeouts` (`timeout`, 3 secondes, et `maxDuration`, 5 secondes, par défaut ; au-dessus de zéro et 120
  secondes au plus), pris en dernier par chaque adaptateur HTTP et réglé sur son client par `withOptions()` ;
  `InvalidPlace::timeouts()`.

### Modifié

- Le test d'une base de données illisible laisse de côté le fichier sans permissions quand les tests s'exécutent sous un
  utilisateur qui le lit quand même, comme root dans un conteneur.
- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`symfony/cache-contracts` 3.8,
  `symfony/http-client-contracts` 3.8).

### Sécurité

- Les adaptateurs HTTP envoyaient leurs requêtes sans délai d'attente : un fournisseur lent retenait un worker jusqu'à
  `default_socket_timeout`, 60 secondes. Chaque appel attend un octet 3 secondes au plus et dure 5 secondes au plus par
  défaut.
- `IpstackLocator` envoyait à ipstack une adresse privée ou réservée, comme celle d'un mandataire derrière un mandataire
  auquel l'application ne se fiait pas ; lui et `MaxMindLocator` répondent `null` pour une telle adresse sans la
  chercher.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Coordinates`, `Distance`, `BoundingBox` ; `GeoException` et `InvalidCoordinates`.
- `SchemaOrg` : la forme schema.org de la valeur de la bibliothèque, à intégrer dans du JSON-LD.
- Le géocodage : les ports `Geocoding\Geocoder` et `Geocoding\IpLocator`, `Geocoding\Place`, `GeocodingFailed` et
  `InvalidPlace`, dont le message montre une adresse IP refusée coupée à 64 octets, les caractères de contrôle échappés.
  Une `GeocodingFailed` due à une réponse illisible ne donne que le statut HTTP ou la sorte d'échec, jamais le message
  du client HTTP, qui nomme l'URL de la requête avec sa clé et l'adresse demandée.
- Les adaptateurs, sur `symfony/http-client-contracts` et `symfony/cache-contracts`, suggérés : `NominatimGeocoder` ;
  `PhotonGeocoder`, Photon sur les données d'OpenStreetMap, sans clé — le serveur public ou le vôtre —, une locale
  envoyée seulement dans une langue que le serveur connaît ; `GoogleMapsGeocoder` ; `NrcanGeocoder`, le service de
  géolocalisation de Ressources naturelles Canada, en anglais ou en français, sans clé ; `OpenCageGeocoder`,
  `HereGeocoder`, `MapboxGeocoder` (réponses temporaires ou permanentes), `AzureMapsGeocoder` ;
  `AdressesQuebecGeocoder`, le registre des adresses du Québec, sans clé ; `IpstackLocator`, qui interroge
  `https://api.ipstack.com` par défaut, le HTTP en clair étant une URL de base choisie en connaissance de cause ;
  `ChainGeocoder` et `CachedGeocoder`. Un pays de fournisseur qui n'est pas en deux lettres est une `GeocodingFailed`,
  qu'un `ChainGeocoder` passe. Les clés de `GoogleMapsGeocoder` et d'`IpstackLocator` sont `#[SensitiveParameter]`.
- Les bases de données MaxMind lues sur le serveur : `Geocoding\MaxMind\Database` et `Metadata`, d'après la
  spécification du format MaxMind DB, le fichier lu par blocs de 4 Ko dont jusqu'à 64 (256 Ko) sont gardés ;
  `MaxMindLocator`, un `IpLocator` sur une base de villes, où un pays qui n'est pas en deux lettres est une
  `GeocodingFailed` ; `InvalidDatabase`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
