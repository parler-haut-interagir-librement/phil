<!-- phil-dev source-sha256=2961b0582b0321d5e7e3aa3f103df2b0fc114790d07619f40bedfec9199bae56 -->
# phil/code-of-conduct

**Publie un code de conduite versionné, enregistre qui a accepté quelle version et achemine des signalements chiffrés vers des responsables que ces signalements ne nomment pas.**

phil/code-of-conduct donne à chaque portée — une communauté, un événement, un espace — un code de conduite publié en
versions numérotées. Un membre accepte la version en vigueur ; une version majeure demande à chaque membre d'accepter de
nouveau, une version mineure non. L'application demande où en est un membre avant qu'il participe — écrire, s'inscrire,
publier — et ne lui interdit jamais la lecture.

Les membres déposent des signalements en vertu du code, nommés ou anonymes, et suivent l'état de leur signalement. Les
responsables de la portée lisent le signalement, écrivent des notes, le révisent, le règlent avec une issue ou le
rejettent. Un responsable que le signalement nomme ne le voit jamais, et un responsable peut se retirer ; un signalement
qu'aucun responsable ne pourrait recevoir est refusé, afin que le membre joigne le contact hors du site. Le texte d'un
signalement et ses notes sont scellés par le chiffrement d'enveloppe du composant KeyManagement de Symfony avant d'être
stockés.

C'est une bibliothèque PHP simple. Les magasins et la liste des responsables sont des ports que l'application
implémente ; l'issue d'un signalement est appliquée par la modération de l'application, à partir d'un événement qui ne
porte que des identifiants.

## État

| Partie | État |
|---|---|
| Versions d'un code, majeures ou mineures, numérotées, avec empreinte | Implémenté et testé |
| Acceptation de la version en vigueur, état d'un membre | Implémenté et testé |
| Signalements, nommés ou anonymes, scellés, avec un accusé de réception pour l'auteur | Implémenté et testé |
| Traitement par les responsables : file, lecture, notes, révision, issue, rejet, retrait | Implémenté et testé |
| Scellement par KeyManagement de Symfony, lié au signalement et à la note | Implémenté et testé |
| Les magasins et la liste des responsables sur une base de données | Ports seulement ; implémentations en mémoire pour les tests |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et la construction des services. |
| [Versions et acceptation](doc/fr/versions-and-acceptance.md) | Publier, versions majeures et mineures, accepter, l'état d'un membre. |
| [Signalements](doc/fr/reports.md) | Le dépôt, l'accusé de réception, qui traite un signalement, ses statuts et ses issues, le scellement de ses textes. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/code-of-conduct
```

```php
use Phil\CodeOfConduct\ChangeKind;

$code->publish('community-1', 'Code of conduct', $text);
$code->accept('community-1', 'member-7', 1);

if (!$code->status('community-1', 'member-8')->mayTakePart()) {
    // Show the code and ask member-8 to accept it before they post.
}

$report = $reports->file('community-1', 'member-7', ['member-9'], 'What happened, when, where.');
$view = $reports->read($report->id, 'moderator-2'); // a responsible person not named in the report
```

## Prérequis

PHP 8.5 ou une version plus récente avec `mbstring` et `sodium` ; `symfony/key-management` et `symfony/uid` 8.2,
`psr/clock` et `psr/event-dispatcher`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
