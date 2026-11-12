<!-- phil-dev source-sha256=27b48b49754a8ee179b558c218ceebd9ca6611d34fbd06f499c47b203e81c221 -->
# Points d'extension

Ce qu'une application implémente, configure ou utilise.

## Les ports écrits par l'application

### `DraftOwner`

`Phil\FormsAutosave\Application\Contract\DraftOwner` : qui remplit le formulaire (`server.owner`). Sans lui, c'est
l'utilisateur de Symfony Security quand SecurityBundle est installé, et seulement des visiteurs sinon.

| Méthode | Contrat |
|---|---|
| `current(): ?string` | Un identifiant stable d'une session et d'un appareil à l'autre de l'utilisateur, ou null pour un visiteur |

## Les ports avec adaptateurs fournis

### `DraftStore`

`Phil\FormsAutosave\Application\Contract\DraftStore` : l'endroit où les brouillons du serveur sont conservés. Fournis :
`DoctrineDraftStore` et `InMemoryDraftStore` ; une application le remplace en créant un alias de l'interface.

| Méthode | Contrat |
|---|---|
| `find(string $owner, DraftKey $key): ?Draft` | Le brouillon sous l'empreinte d'un propriétaire et une clé |
| `insert(Draft $draft): bool` | Faux quand le propriétaire a déjà un brouillon sous cette clé |
| `replace(Draft $read, Draft $next): bool` | Faux quand la révision stockée n'est plus celle qui a été lue |
| `delete(string $owner, DraftKey $key): void` | |
| `purge(DateTimeImmutable $now): int` | Supprime les brouillons expirés à ce moment et dit combien |

### `DraftSealer`

`Phil\FormsAutosave\Application\Contract\DraftSealer` : la façon de sceller les contenus. Fournis : `KeyManagementSealer`
et `PlainSealer` ; celui d'une application, déclaré comme alias de l'interface, prend leur place.

| Méthode | Contrat |
|---|---|
| `seal(string $content, string $context): string` | Le texte scellé, lié au contexte |
| `open(string $sealed, string $context): ?string` | Le contenu, ou null quand il ne peut pas être ouvert dans ce contexte |

## Configuration

Voir [Configuration](configuration.md) : le secret, les durées et les tailles, et le stockage serveur.

## Gabarits et styles

`@PhilFormsAutosave/status.html.twig` peut être surchargé dans `templates/bundles/PhilFormsAutosave/`, ou remplacé par
la recette `AutosaveStatus` d'une trousse PHIL UX. Ses classes, `phil-autosave`, `phil-autosave__status` et
`phil-autosave__notice`, sont mises en forme par `forms-autosave.css`, hors de toute couche, pour que les règles de
l'application l'emportent.

## Traductions

Les libellés sont traduits dans le domaine `phil_forms_autosave` ; le bundle fournit l'anglais et le français. Une
application ajoute une locale avec son propre fichier `translations/phil_forms_autosave.<locale>.*`.

## Ce sur quoi le bundle s'appuie

| | |
|---|---|
| La balise du formulaire | Produite par `form_start()`, qui écrit les `attr` du formulaire racine |
| Stimulus | L'application charge le contrôleur, par le StimulusBundle |
| La protection CSRF de FrameworkBundle | Activée, pour le stockage serveur ; le jeton est sans état et n'a pas besoin de session |
| L'événement `kernel.response` | L'endroit où les formulaires soumis sont oubliés |
