<!-- phil-dev source-sha256=e79cbf6b5315d1484078a0ddf420a46ca69a07e282a084e36d9169a51eacadd6 -->
# Exports de livraison

`ShippingExports` écrit, pour un avantage qui expédie un objet, le fichier dont un livreur a besoin pour envoyer les
éléments encore à servir d'une période.

```php
$result = $exports->export($period, 'postcard', 'staff-7', $now);

$result->record->rows;            // how many rows the file holds
$result->record->withoutAddress;  // how many members were left out for want of an address
$result->token;                   // the token of the download link
```

## Ce que le fichier contient, et ne contient jamais

Un fichier CSV d'une ligne par élément à livrer, avec ces colonnes seulement :

| Colonne | Tirée de l'adresse |
|---|---|
| `name` | Les prénoms, noms additionnels et noms de famille ; l'organisation quand aucune personne n'est nommée |
| `address_line_1`, `address_line_2`, `address_line_3` | Les lignes de l'adresse |
| `city` | La localité |
| `region` | La province, l'État, la région |
| `postal_code` | Le code postal |
| `country` | Le code ISO 3166-1 alpha-2 du pays |
| `deliverable` | Le code de l'avantage |

**Jamais** l'adresse courriel du membre, son identifiant ou son palier, ni son numéro de téléphone : le fichier va à un
tiers, et ne contient que ce dont l'étiquette a besoin, rien de plus.

- **Formules neutralisées** : le fichier est écrit par `phil/spreadsheet`, qui garde comme du texte, derrière une
  apostrophe, une valeur qu'un tableur exécuterait comme une formule (`=`, `+`, `-`, `@`, une tabulation, un retour
  chariot).
- **Compatible avec Excel** : UTF-8 avec une marque d'ordre des octets, lignes terminées par CRLF.
- **Les éléments livrés sont écartés** : un export liste ce qui reste, de sorte qu'un export fait après l'expédition
  d'une partie de la période ne contient que le reste.
- **Un membre sans adresse est écarté** et compté dans `withoutAddress` ; rien d'autre n'est dit à son sujet.
- **Borné** : les membres sont lus par pages de 500, le fichier est écrit au fur et à mesure, et un export contient au
  plus 50 000 lignes par défaut (`$maxRows`).

Un avantage qui n'expédie rien, une mention ou une partie, n'a pas d'export : `ExportRefused::shipsNothing()`.

## D'où viennent les adresses

L'application implémente `Contract\DeliveryAddressProvider` : `addressesOf($members)` retourne l'`Phil\Address\Address`
de chaque membre qui en a une. Elle peut lire l'adresse de livraison d'un abonnement, le profil d'un membre ou un
formulaire ; le paquet n'en garde aucune.

## Le journal

Chaque export est consigné par `Contract\ExportJournal` sous la forme d'un `ExportRecord` : son identifiant, la période
et l'avantage, **qui l'a demandé et quand**, **combien de lignes**, combien de membres n'avaient pas d'adresse, jusqu'à
quand son fichier est gardé, et quand le fichier a été effacé. L'enregistrement survit à son fichier, comme la trace de
ce qui est parti chez un livreur.

## Le lien signé et l'expiration

Le jeton d'`ExportResult` est `<id>.<expiry>.<signature>`, la signature étant un HMAC-SHA256 de l'identifiant et de
l'expiration, calculé avec une clé que `DownloadLinks` dérive de son secret par HKDF et l'étiquette
`phil_benefit_deliverables.download_link`, de sorte qu'aucun autre usage du secret ne donne les mêmes signatures.
L'application le met dans une route de téléchargement à elle, derrière l'autorisation de son équipe :

```php
$stream = $exports->download($token, $now);   // the file, open for reading
```

- Le lien et le fichier durent `$ttlSeconds`, une heure par défaut, de une minute à un jour.
- Un jeton d'une autre forme, une signature qui ne correspond pas, comparée en temps constant, une expiration passée, un
  export inconnu ou un fichier effacé donnent tous le même `ExportRefused::unavailable()`, de sorte qu'un lien essayé
  au hasard n'apprend rien.
- Le secret fait 32 octets ou plus, gardé avec les secrets de l'application ; le changer annule tous les liens.

## La purge

```php
$exports->purge($now);   // erases the files of the exports expired, up to 500
```

`purge()` efface le fichier de chaque export expiré et le note dans le journal. Lancez-la depuis une tâche planifiée,
toutes les heures par exemple. Lancée de nouveau, elle ne trouve plus rien.

## Où les fichiers attendent

`Contract\ExportFileStore` écrit, ouvre et efface les fichiers. `Export\LocalExportFileStore` les garde dans un
répertoire du serveur sous la forme `<id>.csv`, lisibles par le propriétaire seulement (`0600`), écrits dans un fichier
temporaire puis renommés, de sorte qu'un lecteur ne voit jamais la moitié d'un fichier et qu'un échec n'en laisse
aucun. Gardez le répertoire **hors de la racine Web** : un fichier est remis seulement par son lien signé. Une
application dont les serveurs ne partagent aucun disque implémente le port sur son propre stockage privé.
