<!-- phil-dev source-sha256=f449a06425c79c6eb007f42853353ae370961ef5c2f609433cb30f9bf1967a17 -->
# Formulaires

## L'option `autosave`

Le bundle ajoute l'option `autosave` à chaque type de formulaire.

Sur un formulaire racine, `true` ou un tableau active les brouillons :

```php
$builder = $this->createForm(PostType::class, $post, ['autosave' => true]);
$builder = $this->createForm(PostType::class, $post, ['autosave' => ['subject' => $chapter->slug, 'server' => false]]);
```

| Clé | Valeur par défaut | |
|---|---|---|
| `subject` | `null` | L'enregistrement que le formulaire modifie, qui fait partie de la clé : `null` lit `getId()` des données du formulaire, `false` n'en prend aucun ; sinon un texte, un nombre, un `Stringable` ou une fermeture qui reçoit les données initiales du formulaire et retourne l'un d'eux |
| `server` | `true` | `false` garde les brouillons de ce formulaire dans le navigateur, même avec le stockage serveur activé |

La balise du formulaire porte alors le contrôleur `phil--forms-autosave--autosave` et ses valeurs : la clé, la version
de la structure, `ttl`, `debounce`, `max_bytes`, les libellés, `invalid` après une soumission invalide et, avec le
stockage serveur et un utilisateur connecté, l'URL du brouillon et le nom de son témoin CSRF. Un `data-controller` que
le formulaire a déjà est conservé.

Sur un champ, `false` l'exclut des brouillons et `true` l'y inclut, même s'il est caché :

```php
$builder
    ->add('notes', TextareaType::class)
    ->add('bankAccount', TextType::class, ['autosave' => false])   // never kept
    ->add('position', HiddenType::class, ['autosave' => true]);    // kept, though hidden
```

Le champ reçoit `data-phil-autosave="off"` ou `data-phil-autosave="on"`. Dans un gabarit, le même attribut sur un champ
ou sur un élément qui l'entoure décide pour les champs qu'il contient ; le plus proche l'emporte.

## Clés

Une clé est faite de 32 caractères sûrs pour une URL, tirés d'une empreinte SHA-256 calculée avec le secret configuré
sur le type et le nom du formulaire, son sujet et son utilisateur. Deux enregistrements modifiés avec le même formulaire
ont deux brouillons ; deux utilisateurs dans un même navigateur ont deux brouillons ; et la clé ne dit rien d'aucun
d'eux. L'utilisateur est l'identifiant que donne `DraftOwner`, null pour un visiteur.

## La version de la structure

Un brouillon n'est remis que dans la structure d'où il a été tiré : la version est une empreinte du chemin et du type de
chaque champ. Ajouter, retirer, renommer ou changer le type d'un champ donne une nouvelle version, et les brouillons
plus anciens sont abandonnés. Une collection compte par le type de ses entrées : ajouter une entrée garde donc la
version.

## Les champs conservés

Le contrôleur conserve chaque champ nommé et activé du formulaire, sauf :

- les mots de passe, les fichiers et les boutons ;
- les jetons CSRF : un nom `_token`, terminé par `[_token]`, ou `_csrf_token` ;
- les champs dont l'`autocomplete` nomme une carte de paiement (`cc-…`), un mot de passe ou un code à usage unique ;
- les champs cachés, sauf s'ils sont marqués `on` ;
- les champs marqués `off`, sur eux-mêmes ou autour d'eux.

Le champ de `phil/block-editor-bundle`, un champ caché qui contient un document UJO (Universal JSON Output), est marqué
`on` et reçoit `data-phil-autosave-remount` : quand un brouillon est restauré, son éditeur est remonté pour qu'il lise
le document restauré.

## Soumissions

Un formulaire soumis est gardé dans la requête. Quand la réponse part, si le formulaire est toujours valide (après les
erreurs que l'application a elle-même ajoutées, le cas échéant), sa clé est inscrite dans le témoin
`phil_forms_autosave_done` et sa copie sur le serveur est supprimée. Le contrôleur lit le témoin sur la page suivante où
il s'exécute, oublie ces brouillons et supprime le témoin. Une soumission invalide garde le brouillon, et la page
affiche les valeurs soumises plutôt que le brouillon.
