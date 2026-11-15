<!-- phil-dev source-sha256=9b174895bb8d08b408a6f8d309c130eb390749cfb9985ac2c9949a0e1040e574 -->
# Deep Linking

Deep Linking 2.0 permet à une personne instructrice de choisir du contenu dans l'outil — un quiz, un chapitre, une
vidéo — et de le placer dans la plateforme, qui le lance ensuite comme un lien de ressource.

## La requête

La plateforme lance l'outil avec un `LtiDeepLinkingRequest`. Ses `DeepLinkingSettings` disent ce qu'elle accepte en
retour :

| Paramètre | Ce qu'il dit |
|---|---|
| `returnUrl` | Où la réponse est envoyée par POST. |
| `acceptTypes` | Les types d'éléments de contenu : `ltiResourceLink`, `link`, `file`, `html`, `image`. |
| `acceptPresentationDocumentTargets` | `iframe`, `window`, `embed`. |
| `acceptMediaTypes` | Pour les fichiers et les images, les types de médias acceptés. |
| `acceptMultiple` | Si plusieurs éléments peuvent être envoyés. |
| `acceptLineItem` | Si un lien de ressource peut demander un line item. |
| `autoCreate` | Si la plateforme place les éléments sans redemander. |
| `title`, `text` | Les valeurs par défaut que la plateforme suggère. |
| `data` | Une valeur opaque que la réponse doit renvoyer telle quelle. |

Du côté de l'outil, `$launch->isDeepLinking()` distingue la requête d'un lancement de lien de ressource, et
`$launch->message->deepLinking` contient les paramètres.

## Les éléments de contenu

| Classe | Type | Ce que c'est |
|---|---|---|
| `ResourceLinkItem` | `ltiResourceLink` | Un lien de ressource de l'outil, avec son adresse ou celle par défaut de l'outil, des paramètres personnalisés, un line item à créer, une icône, une vignette et des fenêtres de disponibilité et de soumission. |
| `LinkItem` | `link` | Une page, ouverte hors de LTI. |
| `FileItem` | `file` | Un fichier que la plateforme doit garder, avant l'expiration de son adresse. |
| `HtmlItem` | `html` | Un fragment de HTML, 64 Kio au plus. |
| `ImageItem` | `image` | Une image, avec sa taille. |

## La réponse de l'outil

```php
use Phil\Lti\DeepLinking\LineItemRequest;
use Phil\Lti\DeepLinking\ResourceLinkItem;
use Phil\Lti\Tool\DeepLinkingResponder;

$post = new DeepLinkingResponder($keys, $clock)->respond($launch, [
    new ResourceLinkItem($quizUrl, 'Quiz 3', custom: ['quiz' => '3'], lineItem: new LineItemRequest(10.0, 'Quiz 3', 'quiz-3')),
], message: 'Quiz 3 added.');
// render a form that posts $post->fields (one field, JWT) to $post->action
```

`respond()` refuse un lancement qui n'est pas une requête de Deep Linking, un élément d'un type que la requête
n'accepte pas, et plus d'un élément lorsqu'elle n'en accepte qu'un. Une liste vide dit que rien n'a été choisi ;
`errorMessage` et `errorLog` disent pourquoi le choix a échoué. La réponse est un JWT signé avec la clé de l'outil :
`iss` le client, `aud` la plateforme, cinq minutes de vie, un `nonce` neuf, le type de message
`LtiDeepLinkingResponse`, le déploiement, les éléments, le `data` de la requête, et les messages.

## Le retour sur la plateforme

```php
use Phil\Lti\Platform\DeepLinkingReturn;

$result = new DeepLinkingReturn('https://courses.example.com', $tools, $nonces, $verifier)->read($_POST['JWT']);
```

`read()` vérifie que la réponse est émise par un client enregistré et adressée à la plateforme, qu'elle se vérifie avec
une clé du jeu de clés de l'outil, que ses heures sont valides, que son `nonce` est employé une seule fois, que son
déploiement est celui de l'outil, et qu'elle est un `LtiDeepLinkingResponse` de LTI 1.3. `DeepLinkingResult` donne le
client, le déploiement, les éléments, le `data` que la plateforme a mis dans la requête — pour retrouver le placement
auquel elle était destinée — et les messages. Un élément d'un type que le paquet ne connaît pas est ignoré ; la
plateforme assainit le HTML d'un `HtmlItem` avant de l'afficher.
