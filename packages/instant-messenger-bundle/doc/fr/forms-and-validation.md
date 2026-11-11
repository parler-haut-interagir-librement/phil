<!-- phil-dev source-sha256=d18470d19378d7da99258b4aa1a9bde277e3a1b298a45f9b7d451dc09e608a8f -->
# Formulaires et validation

## Les services acceptés

Un champ et une contrainte nomment les services qu'ils acceptent comme `phil/url-bundle` nomme les genres d'adresse :
`null` pour chaque service international, une classe d'enum pour tous ses cas, un cas, un `EnumSet` ou une liste de
ceux-ci. Les cas d'une même enum forment un seul ensemble, et les ensembles gardent l'ordre de leur première apparition.

## `HandleType`

Deux champs, vers un `Handle` et depuis un `Handle` : le service, choisi parmi ceux qui sont acceptés (les choix dans
cet ordre, nommés par `label()` pour les services internationaux et par le nom de leur cas pour ceux de l'application),
et l'identifiant tel que la personne le tape.

```php
$builder->add('messenger', HandleType::class, ['types' => [ForumMessenger::class, KnownMessenger::Signal], 'required' => false]);
```

Un identifiant vide vaut `null` ; un identifiant que le service choisi ne lit pas rend le champ invalide avec *This
value is not a valid handle on this service.* Un identifiant est affiché tel que les gens le lisent, `@adalove`, quand
son service relit ce texte comme le même identifiant, et sous sa forme canonique sinon : un formulaire de modification
soumis sans changement garde son identifiant, même pour un service de l'application dont `display()` le décore.

## `HandleOfType`

```php
#[HandleOfType([KnownMessenger::Signal, KnownMessenger::WhatsApp])]
public ?Handle $messenger = null;
```

Un `Handle` est vérifié selon son service ; un texte, par détection parmi les services acceptés. `null` et le texte vide
sont valides. Le message, *This handle is on none of the accepted services: {{ types }}.*, a le code
`HandleOfType::NOT_ACCEPTED_ERROR`.

## Stocker un identifiant

Un identifiant forme deux colonnes que l'application mappe, le service avec sa propre enum :

```php
#[ORM\Column(enumType: KnownMessenger::class)]
private KnownMessenger $service;

#[ORM\Column(length: 255)]
private string $handle;

public function messenger(): Handle
{
    return Handle::fromCanonical($this->service, $this->handle);
}

public function setMessenger(Handle $messenger): void
{
    [$this->service, $this->handle] = [$messenger->type, $messenger->value];
}
```

L'identifiant stocké est l'identifiant canonique, lu au moment où il a été conservé. `Handle::fromCanonical()` le
reprend tel quel : `Handle::of()` le relirait et refuserait un numéro de téléphone qu'une importation ultérieure des
plans de numérotation ne connaît plus, si bien que l'entité ne pourrait plus être chargée ni affichée.
