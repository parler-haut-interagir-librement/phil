<!-- phil-dev source-sha256=c29fc13389e83e449f3bbc8f0eec4bd555fa60d89662d8f3d1332d7df0d0b667 -->
# Sélection

## Des identifiants ou un filtre

```php
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\Selection;

// The ticked rows, each with the version shown on the page when the target keeps one.
$selection = Selection::identifiers(['p1', 'p2', 42], ['p1' => 7, 'p2' => 3]);

// Every item of the filter, less the rows the person unticked.
$selection = Selection::filter(FilterCriteria::of('status', ['status' => 'draft', 'category' => [3, 4]]), ['p9']);
```

- Un identifiant fait de 1 à 191 octets sans caractère de contrôle ; un entier est conservé sous sa chaîne décimale. Les
  doublons sont abandonnés et l'ordre est conservé.
- Une version doit appartenir à un élément sélectionné. Quand une sélection en porte une, la première tentative de
  l'exécution la vérifie.
- Une sélection liste 10 000 identifiants au plus, quelles que soient les limites ; `Limits::maxIdentifiers` abaisse ce
  plafond.

## Les filtres

Un filtre est un nom que le dépôt connaît, avec jusqu'à 32 paramètres, chacun un scalaire, `null` ou une liste d'au plus
1 000 d'entre eux, 16 Kio au plus une fois encodé. Les paramètres sont triés par nom, de sorte qu'un même filtre est
conservé de la même façon.

Le dépôt dit s'il accepte un filtre (`supports()`), combien d'éléments le satisfont (`count()`) et lesquels, lot par lot
après un curseur (`matching()`). La bibliothèque appelle `supports()` à la sélection, à l'aperçu, à la confirmation et
avant chaque lot : un filtre que le dépôt n'accepte plus arrête l'exécution. Les identifiants sont lus quand
l'exécution a lieu ; un élément sorti du filtre après la confirmation n'est donc pas modifié, et une ligne décochée
reste à l'écart.

L'ordre de `matching()` doit être stable, comme celui de la clé primaire : le curseur est le dernier identifiant du lot
précédent. Un dépôt qui ne rend que des identifiants déjà lus est considéré comme arrivé à la fin du filtre.

## Le jeton

```php
$token = $selector->select($user->getUserIdentifier(), 'products', 'edit', $selection, new DateTimeImmutable());
// Put $token->value in the page; never the identifiers.
```

Le jeton est 32 octets aléatoires en base64url, 43 caractères. Le magasin range la sélection sous son empreinte SHA-256,
de sorte qu'il ne détient pas le jeton lui-même. Un jeton est valide pendant `Limits::lifetimeSeconds`, pour la seule
personne qui a fait la sélection, et une seule fois : la confirmation le prend. Un jeton inconnu, déjà utilisé, expiré,
détenu par une autre personne ou mal formé lance `SelectionUnavailable`, avec le même message dans tous les cas.

`SelectionToken::fromString()` lit ce que la page renvoie.

## Une case par champ

Un formulaire de modification groupée montre chaque champ avec une case qui dit si le champ change :

```html
<label><input type="checkbox" name="apply[price]" value="1"> Change the price</label>
<input type="number" name="values[price]">
```

```php
use Phil\BulkActions\Change\ChangeSet;

$changes = ChangeSet::fromSubmission($request->request->all('values'), $request->request->all('apply'));
```

Une case cochée par `"1"`, `"on"`, `"true"` (quelle que soit la casse) ou `true` prend la valeur du champ, `null` quand
le champ n'est pas envoyé. Toute autre case laisse le champ inchangé. `has()` distingue un champ mis à `null` d'un champ
laissé tel quel, et `value()` donne la valeur. Une modification définit 64 champs au plus.
