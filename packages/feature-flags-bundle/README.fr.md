<!-- phil-dev source-sha256=28d458dcc8deb30993040da2bcc5bee0e58499e7bfa74be6d7e3a0101abf3b2b -->
# phil/feature-flags-bundle

**Désactivez une fonctionnalité sans déploiement, menez un test A/B et forcez l'ouverture du circuit d'un tiers
défaillant.**

phil/feature-flags-bundle donne deux outils à une application Symfony. Les indicateurs de fonctionnalité retirent une
nouvelle fonctionnalité sur-le-champ. Les tests A/B répartissent les contextes entre des variantes pondérées. Chaque
disjoncteur de `phil/circuit-breaker-bundle` reçoit aussi un indicateur qui force son ouverture, pour qu'un opérateur
cesse d'appeler un tiers défaillant. Les indicateurs sont déclarés dans la configuration ou créés dans la base de
données à partir d'écrans d'administration. Chacun porte un responsable, un genre et une date d'expiration, et cible un
locataire, un projet ou un sujet, avec des déploiements progressifs par pourcentage stables.

Il s'adresse aux développeurs qui livrent des fonctionnalités derrière un indicateur et aux administrateurs qui les
basculent. Chaque bascule et chaque modification est autorisée, auditée avant d'être écrite, et porte une raison
obligatoire : l'historique d'un indicateur dit donc qui l'a changé et pourquoi. Le bundle n'est pas une couche
d'authentification : l'application place les écrans d'administration derrière son pare-feu et décide qui peut changer
quoi. Il n'est pas non plus le client d'un service d'indicateurs hébergé, ni un outil d'analytique. Une exposition A/B
est comptée par indicateur et par variante, jamais enregistrée avec son contexte.

Servez-vous-en pour retirer une nouvelle fonctionnalité. Ce n'est pas un interrupteur d'urgence opérationnel pour un
sous-système établi. Un tel interrupteur est une liste courte et énumérée qui doit continuer de fonctionner lorsque le
problème vient de la base de données ou du cache : il se lit donc sans les fournisseurs de ce bundle.

Un indicateur se lit dans un attribut de contrôleur, une condition de route, Twig ou un service. Il est évalué une seule
fois par requête et par contexte à partir de fournisseurs ordonnés (la configuration, puis le magasin Doctrine DBAL), et
chaque réponse donne sa raison. Un panneau du profileur montre les indicateurs qu'une requête a évalués.

## État

| Domaine | État |
|---|---|
| Indicateurs, fournisseurs, magasin d'états, évaluation une fois par requête, raisons | Implémenté et testé |
| Ciblage (appartenance, déploiements progressifs par pourcentage stables) | Implémenté et testé |
| Expériences A/B et comptage des expositions | Implémenté et testé |
| Métadonnées et expiration | Implémenté et testé |
| Bascule et éditeur audités, révisions, conflits | Implémenté et testé |
| Magasin en base de données (Doctrine DBAL, écouteur de schéma) | Implémenté, testé sur SQLite, MySQL 8 et PostgreSQL |
| Écrans d'administration, garde de même origine | Implémenté, testé dans un noyau |
| Attribut de contrôleur, condition de route, fonctions Twig | Implémenté, testé dans un noyau |
| Commande de liste avec `--fail-on-expired` | Implémenté, testé dans un noyau |
| Un indicateur par circuit de `phil/circuit-breaker-bundle` qui force son ouverture | Implémenté et testé |
| Un panneau du profileur pour les indicateurs | Implémenté, son collecteur testé |

## Documentation

- [Installation](doc/fr/installation.md) : les prérequis, Composer, l'enregistrement du bundle, les tables de la base de
  données et leur migration, les routes d'administration et le pare-feu devant elles.
- [Configuration](doc/fr/configuration.md) : chaque clé de `phil_feature_flags` avec son type, sa valeur par défaut, son
  effet et les services qu'elle branche, un exemple complet et annoté, et les erreurs qui font échouer la construction
  du conteneur.
- [Indicateurs et évaluation](doc/fr/flags-and-evaluation.md) : les définitions, les fournisseurs ordonnés et leur
  priorité, le magasin d'états, la mémoïsation par requête et par contexte, la réinitialisation dans les workers, les
  raisons, le contexte et le catalogue.
- [Ciblage](doc/fr/targeting.md) : les règles d'appartenance et de pourcentage, le compartiment stable `murmur3a`, et
  pourquoi sa fonction est une porte à sens unique.
- [Expériences](doc/fr/experiments.md) : les variantes pondérées, l'attribution par poids cumulé, la lecture de la
  variante, et les expositions comptées sans contexte.
- [Métadonnées et expiration](doc/fr/metadata-and-expiry.md) : la description, le responsable, les quatre genres
  d'indicateur, la date d'expiration et la façon dont elle fait échouer une construction.
- [Bascules et modifications](doc/fr/toggles-and-edits.md) : la bascule et l'éditeur audités, l'ordre
  autoriser–lire–auditer–écrire, la comparaison-échange, les insertions du premier état, les révisions, les conflits et
  les transactions.
- [Magasin en base de données](doc/fr/database-store.md) : les deux tables, l'écouteur de schéma, la lecture par
  requête, les écritures conditionnelles et les rangées illisibles.
- [Administration](doc/fr/admin.md) : les routes, les écrans, les statuts HTTP, la garde de même origine, le fournisseur
  d'acteur, et le remplacement de la mise en page et des gabarits.
- [Placement](doc/fr/placement.md) : l'attribut de contrôleur, la condition de route, les fonctions Twig et les
  indicateurs dans le cœur.
- [Console](doc/fr/console.md) : `phil:feature-flags:list`, ses colonnes et ses options, en intégration continue.
- [Disjoncteurs](doc/fr/circuit-breakers.md) : l'indicateur d'ouverture forcée de chaque circuit de
  `phil/circuit-breaker-bundle`, et l'ouverture forcée d'un circuit par une bascule auditée.
- [Points d'extension](doc/fr/extension-points.md) : chaque port avec son contrat, ses invariants et un exemple
  d'adaptateur, et chaque identifiant de service, étiquette, route, fonction Twig et attribut.
- [Tests](doc/fr/testing.md) : les suites et les bases de données du bundle, les tests de mutation, et comment tester
  une application qui utilise des indicateurs.
- [Référence](doc/fr/reference.md) : l'API publique par couche, une ligne par type avec son état.

## Prérequis

- PHP 8.5 ou plus récent, avec `ext-mbstring` (et `ext-hash`, toujours compilée) ;
- les composants Symfony 8.2 (`config`, `console`, `dependency-injection`, `http-foundation`, `http-kernel`, `routing`),
  `twig/twig` 3.21 ou plus récent, `psr/clock`, `phil/ddd-bundle`, `phil/circuit-breaker` et
  `phil/circuit-breaker-bundle` ;
- Doctrine DBAL 4.5 et ORM 3.7 ;
- dans l'application : FrameworkBundle et `symfony/clock`, TwigBundle pour les fonctions Twig et l'administration,
  DoctrineBundle pour le magasin en base de données, `symfony/expression-language` pour les conditions de route.

Les détails sont dans [doc/fr/installation.md](doc/fr/installation.md).

## Démarrage rapide

```bash
composer require phil/feature-flags-bundle
```

```php
// config/bundles.php
Phil\FeatureFlagsBundle\PhilFeatureFlagsBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_feature_flags.yaml
phil_feature_flags:
    context_resolver: phil_feature_flags.context_resolver.anonymous   # no targeting yet: an empty context
    flags:
        new-checkout:
            active: true
            owner: checkout-team
            kind: release
            expires_on: '2027-03-31'
```

```php
use Phil\FeatureFlagsBundle\Infrastructure\Http\IsFeatureEnabled;

#[IsFeatureEnabled('new-checkout')]          // 404 while the flag is off
final class CheckoutController
{
    // …
}
```

```twig
{% if feature_enabled('new-checkout') %}<a href="/checkout">Try the new checkout</a>{% endif %}
```

```bash
bin/console phil:feature-flags:list --fail-on-expired
```

Ensuite : un résolveur de contexte pour le ciblage, le magasin en base de données, la bascule auditée et
l'administration ; voyez [doc/fr/installation.md](doc/fr/installation.md).

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
