<!-- phil-dev source-sha256=a61db55f3ed7488d11e6d48e7fb4382998f5d6f40ce6946807293ff247c8bd56 -->
# Cartes

## La vie d'une carte

1. Une page sans image nomme sa carte : `SocialCards::request()` calcule une empreinte du moteur de rendu, du gabarit
   et du contenu, et garde ce à partir de quoi la carte est dessinée — le nom du gabarit, le titre, le sous-titre et
   la locale — dans `<directory>/<first two digits>/<fingerprint>.json`.
2. La première fois, la carte est mise en file d'attente : avec Messenger, un message `GenerateSocialCard` ne porte
   que l'empreinte, et `GenerateSocialCardHandler` dessine la carte dans un worker. Routez le message vers un
   transport asynchrone :

   ```yaml
   framework:
       messenger:
           routing:
               Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCard: async
   ```

3. `/social-cards/{fingerprint}.png` sert la carte gardée, ou la dessine à partir de sa demande quand aucun worker ne
   l'a encore dessinée. Une empreinte qu'aucune page n'a nommée donne un `404`.

L'image ne change qu'avec son empreinte : sa réponse est donc `public` et `immutable`, gardée `cache_max_age`
secondes, avec l'empreinte comme `ETag` et un `304` pour une requête qui la détient. Un nouveau titre, un nouveau
gabarit ou une nouvelle version de GD donne une nouvelle adresse, qu'aucun cache ne détient encore.

## Gabarits

Les gabarits sont déclarés par nom dans `cards.templates`, avec les clés de `CardTemplate::fromArray()` ; voyez la
[documentation des cartes de la bibliothèque](../../../social-cards/doc/cards.md). `CardTemplates` lit les polices et
les images d'un gabarit la première fois qu'il sert et le garde pour la durée de vie du processus : un worker les lit
une seule fois. Une page choisit un gabarit avec `card_template`.

Une carte dont la configuration ne déclare plus le gabarit donne un `404` jusqu'à ce qu'une page la nomme de nouveau
sous un gabarit qui existe.

## Le moteur de rendu

`cards.renderer` choisit ce qui dessine les cartes : `gd`, par défaut, ou `imagick`, qui dessine au moyen d'ImageMagick
et, lorsqu'ImageMagick est compilé avec libraqm, met en forme le texte de l'arabe, de l'hébreu et des écritures de
l'Inde et le dispose dans son sens, un titre de droite à gauche sur la marge de droite ; voyez la
[documentation des cartes de la bibliothèque](../../../social-cards/doc/fr/cards.md#dessiner-avec-imagemagick). `imagick`
a besoin de l'extension imagick de PHP sur les serveurs web comme sur les workers : le conteneur n'est pas construit
sans elle. La version du moteur de rendu entre dans chaque empreinte : changer de moteur de rendu donne à chaque page
une nouvelle carte, dessinée à sa prochaine consultation.

```yaml
phil_social_cards:
    cards:
        renderer: imagick
```

## Le stockage

Les cartes et leurs demandes sont des fichiers sous `cards.directory`, écrits sous un nom temporaire puis renommés à
leur place. Un déploiement sur plusieurs serveurs fait pointer le répertoire vers un stockage partagé, ou remplace les
alias `CardStore` et `CardRequestStore`. Supprimer le répertoire ne retire rien qu'une page ne puisse dessiner de
nouveau : la prochaine consultation de chaque page nomme de nouveau sa carte.

## Étiquettes de cache

Avec phil/http-cache-tags-bundle, chaque carte servie est étiquetée `social-card` et `social-card:<fingerprint>` :
purger `social-card` vide un CDN de toutes les cartes.
