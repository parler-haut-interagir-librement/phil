<!-- phil-dev source-sha256=f83f6535e47505f14d275bc8bdc89c7c37d86cab5f23fdf3b1194f59f451a8e2 -->
# phil/virtual-classroom-bundle

**Des cours en direct dans une application Symfony : des salles sur BigBlueButton ou Jitsi, un lien d'accès écrit selon
le rôle de chaque participant, l'assiduité — qui est arrivé et reparti, et quand — et les enregistrements lus dans les
notifications signées des serveurs, et, avec phil/courses-bundle, un cours en direct comme activité d'un cours,
complétée par la part de la séance suivie.**

phil/virtual-classroom-bundle branche phil/virtual-classroom dans une application. Les **fournisseurs** — serveurs
BigBlueButton, serveurs Jitsi propres à l'application, comptes JaaS ou un service `VirtualClassroom` de l'application —
sont nommés dans la configuration. `Classrooms` **planifie** une salle sur l'un d'eux, appartenant à ce que dit
l'application ; la salle **s'ouvre** sur son serveur quand son premier participant suit le **lien d'accès** du bundle,
qui l'envoie, selon son rôle, au lien auquel le serveur fait confiance. Est modérateur quiconque a un rôle Symfony
configurable.

Les serveurs rappellent le **webhook** du bundle à des adresses qu'il signe, une par salle et par canal, ou une par
compte : la fin d'une salle, des participants qui arrivent et repartent, un enregistrement prêt, l'assiduité d'une
séance. `phil:virtual-classroom:sync`, lancée par un planificateur, interroge les serveurs capables de dire qui est
dans les salles ouvertes et ce qu'elles ont enregistré. Les **séjours** des participants sont conservés, une arrivée
signalée deux fois comptant une seule fois, et `Classrooms` rapporte ce que chaque participant a suivi d'une séance, en
secondes et en pourcentages entiers.

Avec phil/courses-bundle, le type d'activité **`virtual_classroom`** planifie la salle d'une activité à son premier
lancement, et complète l'activité de chaque apprenant inscrit dont l'assiduité atteint le seuil une fois la salle
terminée. Avec phil/privacy-bundle, les séjours d'une personne figurent dans ses demandes d'accès et d'effacement.

C'est un bundle Symfony fondé sur phil/virtual-classroom, Doctrine DBAL, HttpKernel et Routing, avec le client HTTP de
Symfony pour BigBlueButton ; la console, Messenger, Security, phil/courses-bundle et phil/privacy-bundle sont
facultatifs. Les salles, les séjours et les enregistrements vivent dans trois tables sur SQLite, MySQL, MariaDB ou
PostgreSQL, leurs identifiants comparés octet par octet. Ses services ne gardent aucun état entre les requêtes et
conviennent à un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| `Room`, `RoomStatus`, les événements et les exceptions | Implémentés et testés |
| `Classrooms` : planifier, rejoindre, terminer, synchroniser, notifications, assiduité, enregistrements | Implémentés et testés |
| `Providers`, `WebhookSigner`, `AttendeeReport` | Implémentés et testés |
| `DbalClassroomStore`, `ClassroomTables`, `ClassroomSchemaListener`, `InMemoryClassroomStore` | Implémentés et testés |
| `JoinController`, `WebhookController`, `RoutedCallbackUrls`, `SecurityCurrentParticipant` | Implémentés et testés |
| `VirtualClassroomActivity`, `CoursesCompletion` : le type d'activité de phil/courses-bundle | Implémentés et testés |
| `ClassroomPrivacy`, les commandes `sync` et `webhook-url`, `SyncRoom` et son *handler*, la recette Flex | Implémentés et testés |

Le rappel signé d'analyse de l'apprentissage de BigBlueButton — l'assiduité postée avec son jeton porteur — n'est
vérifié que contre des réponses simulées de BigBlueButton : il n'a pas encore été vérifié contre un vrai serveur
BigBlueButton 3.x.

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les routes, les tables. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple pour chaque fournisseur. |
| [Salles](doc/fr/rooms.md) | Planifier, rejoindre, terminer, synchroniser ; le propriétaire d'une salle. |
| [Webhooks](doc/fr/webhooks.md) | Les adresses, leur preuve, ce que chaque fournisseur envoie, les réponses. |
| [Assiduité et enregistrements](doc/fr/attendance.md) | Les séjours, le rapport d'une séance, les enregistrements. |
| [Cours](doc/fr/courses.md) | Le type d'activité `virtual_classroom` et son achèvement. |
| [Vie privée](doc/fr/privacy.md) | Les demandes d'accès et d'effacement, et la conservation. |
| [Console et Messenger](doc/fr/console.md) | `phil:virtual-classroom:sync`, `phil:virtual-classroom:webhook-url`, `SyncRoom`. |
| [Sécurité](doc/fr/security.md) | Ce dont le bundle protège, et ce qui revient à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les événements, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et les serveurs que lisent ses tests d'intégration. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\VirtualClassroomBundle\PhilVirtualClassroomBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_virtual_classroom.yaml
phil_virtual_classroom:
    providers:
        main: { type: bigbluebutton, url: '%env(BBB_URL)%', secret: '%env(BBB_SECRET)%' }

# config/routes/phil_virtual_classroom.yaml
phil_virtual_classroom:
    resource: '@PhilVirtualClassroomBundle/config/routes.php'
    prefix: /virtual-classroom
```

```php
$room = $classrooms->schedule('Algebra, week 3', record: true, owner: 'workshop:42');
// Send the participants to /virtual-classroom/{room}/join.
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente et Doctrine DBAL 4.5 ou une version plus
récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
