<!-- phil-dev source-sha256=27dc0f7678b30d116d54b5f6e07a3e66fa79bea92f6882ebfc5d88e05bb24552 -->
# Tests

## Comment le bundle est testé

Des tests unitaires sous `tests/Unit/`, dans l'espace de noms `Phil\AuditViewer\Tests\`, avec PHPUnit 13 et
`#[CoversClass]` sur chaque classe de test.

| Test | Ce qu'il prouve |
|---|---|
| `Infrastructure\ControllerTest` | Chaque page sur des gabarits qui impriment leur contexte : l'attribut qu'elle demande, la page lue avec les filtres, `400` et `404` là où ils vont, la réponse privée et non conservée; l'export refusé sans son jeton ou avec des filtres qui ne se lisent pas, consigné avant d'être écrit, au plus sa limite; une politique d'accès qui restreint par ligne et par site, imposée à la liste du journal, à la liste de la chaîne et à l'export, chaque ligne et chaque segment demandés avec eux-mêmes |
| `Infrastructure\FiltersAndCsvTest` | Chaque filtre lu, la période dans le fuseau horaire du lecteur, chaque refus; les colonnes du CSV, sa limite, les cellules écrites après un `'` |
| `Infrastructure\AccessTest` | Chaque attribut accordé à son seul rôle, l'historique à son propre rôle et à celui du lecteur, avec les raisons; la politique d'accès qui restreint les rôles sous les stratégies `affirmative`, `consensus` et `unanimous`, interrogée seulement une fois un rôle détenu, et sa raison; les routes, les icônes et les libellés du menu |
| `PhilAuditViewerBundleTest` | Les services et leurs arguments avec les valeurs par défaut et avec chaque clé; chaque valeur hors des bornes refusée |

Une suite fonctionnelle sous `tests/Functional/` démarre une interface d'administration EasyAdmin,
`tests/Functional/App/TestKernel.php` : SecurityBundle avec un pare-feu à session sur quatre comptes, TwigBundle avec
les composants et les extras de Twig, DoctrineBundle sur un fichier SQLite dans le répertoire de cache du noyau avec une
commande auditée et une note qui ne l'est pas, EasyAdmin avec un tableau de bord à `/admin` et leurs contrôleurs CRUD, et
`phil/audit-bundle` avec une sérialisation JSON et son lecteur. `AuditViewerTest` suit les pages comme le fait une
personne qui audite : le journal refusé sans le rôle, filtré et paginé; une ligne et l'historique de son
enregistrement; l'action « Historique » sur les pages de la commande et non sur celles de la note, conservée par un
contrôleur qui nomme la sienne, montrée selon le rôle, et l'historique qu'un commis lit sans le journal; la chaîne
vérifiée, puis une ligne modifiée dans la table et le segment rompu; la chaîne regroupée par site et le journal filtré
sur un locataire; un export consigné puis écrit; et le menu. Elle couvre `AuditHistoryActions`.

## Le fuzzing

La cible de fuzzing `log-filters` donne aux filtres du journal des chaînes de requête que personne n'a écrites, à partir
des germes de `tests/Fuzz/corpus/log-filters` : elles se lisent en une requête et aucun refus, ou en des refus des
champs du formulaire et aucune requête, et un champ donné sous forme de liste est refusé par la requête; rien d'autre
n'est levé. Depuis la racine du monodépôt :

```bash
composer fuzz -- log-filters
```

### Depuis la racine d'un monodépôt qui installe le bundle par chemin

```bash
vendor/bin/phpunit packages/audit-viewer/tests
```

### Installé seul

```bash
cd audit-viewer
composer install
vendor/bin/phpunit --no-configuration tests
```

Le paquet ne fournit aucun `phpunit.xml`. `phil/audit-bundle` et `phil/ddd-bundle` doivent pouvoir être résolus, depuis
un dépôt `path` ou `vcs` tant qu'ils ne sont pas publiés.
