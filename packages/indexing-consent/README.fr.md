<!-- phil-dev source-sha256=0ee502a5afbd50e1d031a157e3c87eb1e9782627e78a7d3d8e1b24e202933714 -->
# phil/indexing-consent

**La portée d'une fiche publique, telle que la personne qu'elle concerne en décide : listée, gardée hors des moteurs de
recherche, ou non listée.**

phil/indexing-consent est un petit contrat entre une fiche publique et ce qui la lit au-delà de sa propre page. Une
fiche — un profil, une inscription dans un répertoire, un billet signé d'un nom — implémente `HasIndexingConsent` et
répond par un `Indexing` : `Listed`, partout ; `NotIndexed`, sur le site et dans ses listes, mais hors des moteurs de
recherche, du plan de site et des données structurées ; `Unlisted`, joignable seulement par son adresse. Chaque lecteur
demande `allows()` en disant ce qu'il est : le plan de site laisse de côté ce qu'il ne peut pas lister, les données
structurées n'en disent rien, le répertoire le cache de ses listes, et la page dit `noindex, noarchive` aux robots.

Il existe parce qu'une personne peut ne plus vouloir qu'une fiche à son sujet soit trouvée par son nom — des lois sur la
vie privée comme la Loi 25 du Québec lui donnent le droit de la faire désindexer — sans demander au site de la retirer.
Le choix vit sur la fiche ; chaque lecteur pose la même question et reçoit la même réponse, pour que retirer une fiche
des moteurs de recherche ne dépende pas de la mémoire de chaque lecteur.

C'est une librairie PHP simple, sans dépendance. Elle ne conserve rien et n'affiche rien : l'application garde le choix
sur sa fiche, et les lecteurs — un plan de site, les données structurées d'une page, un répertoire — le lui demandent.
Une page porte le choix de la fiche qu'elle montre sur sa requête, sous `IndexingPolicy::REQUEST_ATTRIBUTE`, pour que
ses autres lecteurs reçoivent la même réponse ; phil/structured-data-bundle n'imprime alors aucun JSON-LD, et
phil/directory-bundle garde ses fiches à ce que leurs auteurs ont choisi.

## État

| Partie | État |
|---|---|
| `Indexing`, `Reader` | Implémentés et testés |
| `HasIndexingConsent` | Contrat |
| `IndexingPolicy` | Implémentée et testée |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Référence](doc/fr/reference.md) | Les valeurs, ce que chaque lecteur reçoit comme réponse, les directives aux robots, le plus strict de deux choix. |
| [Points d'extension](doc/fr/extension-points.md) | Le contrat qu'une fiche implémente, et comment un lecteur le demande. |
| [Tests](doc/fr/testing.md) | Comment la librairie est testée. |

## Démarrage rapide

```php
final class Profile implements HasIndexingConsent
{
    public function __construct(private Indexing $indexing = Indexing::Listed)
    {
    }

    public function indexing(): Indexing
    {
        return $this->indexing;
    }
}

$profile = new Profile(Indexing::NotIndexed);

IndexingPolicy::allows($profile, Reader::Sitemap);      // false
IndexingPolicy::allows($profile, Reader::SiteListings); // true
$profile->indexing()->robots();                         // 'noindex, noarchive'

foreach (IndexingPolicy::filter($profiles, Reader::Sitemap) as $listed) {
    // add $listed to the sitemap
}
```

## Prérequis

PHP 8.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
