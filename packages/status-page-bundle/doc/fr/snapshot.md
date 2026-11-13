<!-- phil-dev source-sha256=3dc762b21abdf99d31e96d50c2a00dd7e8fd5c809a7dba2185717edbfac13aa0 -->
# Instantané

Un instantané est la copie statique de la page à un moment donné, lisible là où le site ne peut pas l'entraîner dans sa
chute :

| Fichier | Contenu |
|---|---|
| `status.json` | Le JSON de [la page](page.md), ses adresses sous `snapshot.base_url`. |
| `feed.atom` | Le flux Atom, ses identifiants et ses liens sous `snapshot.base_url`. |
| `index.html` | La page dans un document à part, avec des liens vers les deux fichiers voisins. |

Un éditeur les écrit dans cet ordre, de sorte que le HTML qui renvoie aux autres vient en dernier. Le HTML ne porte
aucun script ni aucune adresse du site ; il est marqué `noindex`, renvoie au flux et au JSON comme alternatives, et
porte quelques règles pour les classes des recettes du kit — ou renvoie plutôt à `snapshot.stylesheet`, une adresse
absolue sur un autre hôte.

## Cibles

| `type` | Éditeur | Écrit |
|---|---|---|
| `directory` | `DirectoryPublisher` | Dans `path` : un montage d'une autre machine, la racine d'un serveur qui n'exécute pas l'application, un dossier qu'une synchronisation copie ailleurs. Chaque fichier va dans un fichier temporaire, puis est renommé. |
| `flysystem` | `FlysystemPublisher` | Dans le stockage Flysystem `storage`, sous `prefix`, chaque fichier avec son type de média et, s'il est donné, `visibility` : un stockage d'objets servi comme un site Web, un serveur joint par SFTP. |
| `service` | Celui de l'application | Ce que fait le `SnapshotPublisher` : un service d'hébergement est un adaptateur de l'application, écrit contre ce port. |

La machine d'où l'instantané est lu ne doit pas dépendre de ce qu'elle décrit : un autre hôte, un autre fournisseur, un
autre réseau.

## Publier

```sh
php bin/console phil:status-page:push           # every target
php bin/console phil:status-page:push mirror    # one target
```

La page est assemblée une fois et donnée à chaque cible dans l'ordre où elles ont été déclarées. Une cible qui échoue
n'arrête pas les autres : l'échec est journalisé au niveau `error` avec son exception, la commande affiche le nom court
de l'exception — jamais son message, qui peut porter un hôte ou un identifiant de connexion — et se termine par 1. Une
cible inconnue se termine par 2 avant que rien ne soit écrit.

`PushStatusSnapshot` demande à un worker de publier vers une cible, ou vers toutes ; il ne porte que le nom de la cible.
Son gestionnaire retourne le `PushReport` et ne lance pas d'exception pour une cible en échec, de sorte qu'une cible
injoignable ne retient pas les autres dans une boucle de nouvelles tentatives : le message suivant publie de nouveau.

Avec `snapshot.schedule`, l'horaire `phil_status_page` envoie un `PushStatusSnapshot` à cette fréquence :

```sh
php bin/console messenger:consume scheduler_phil_status_page
```

Un déploiement, la fin d'un incident ou un changement du fichier d'enregistrements peut aussi publier sur-le-champ,
par la commande ou en envoyant le message.
