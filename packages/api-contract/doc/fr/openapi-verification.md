<!-- phil-dev source-sha256=c393e74c1db0546da6deefc8846812c967e78421ec3f9f2c2d2746279a2acbd5 -->
# Vérification OpenAPI

`ContractVerifier` vérifie un document OpenAPI 3.0, 3.1 ou 3.2 selon des niveaux nommés de règles et retourne un
`Report` de constats, chacun avec sa règle, sa gravité et son emplacement dans le document. Les règles décrivent une API
REST qui parle JSON, sans numéro de version : le contrat que le reste de ce paquet porte dans ses types. Le paquet ne vérifie que des documents OpenAPI : un schéma
GraphQL, un document AsyncAPI ou une définition gRPC décrit un autre genre d'API, qu'il ne couvre pas.

```php
use Phil\ApiContract\OpenApi\{ContractVerifier, OpenApiDocument, RuleLevel, Severity};

$report = ContractVerifier::withBuiltInRules()->verify(OpenApiDocument::fromJson($json), RuleLevel::Recommended);

foreach ($report->findings as $finding) {
    echo $finding->severity->value, ' ', $finding->ruleId, ' at ', $finding->location, ': ', $finding->message, "\n";
}

exit($report->fails(Severity::Error) ? 1 : 0);
```

Dans une application Symfony, `phil/api-contract-bundle` exécute la même vérification depuis la console.

## Niveaux

Chaque niveau exécute ses propres règles et celles des niveaux précédents.

| Niveau | Ce qu'est un document qui le satisfait |
|---|---|
| `RuleLevel::Minimal` | Un document OpenAPI 3 que tout outil lit de la même façon : une version, un `info`, des chemins, des réponses, des paramètres de chemin qui correspondent à leurs gabarits, des identifiants d'opération uniques, des références locales qui se résolvent. |
| `RuleLevel::Recommended` | Le contrat d'une API JSON sans version : aucune version dans un chemin, un succès pour chaque opération, des corps JSON, des détails de problème pour les erreurs, un retrait pour une opération obsolète, un identifiant pour chaque opération. La valeur par défaut. |
| `RuleLevel::Strict` | Un contrat qu'un client lit sans deviner : des énumérations extensibles, des erreurs déclarées, une description et une exigence de sécurité pour chaque opération. |

## Gravités

| Gravité | Sens |
|---|---|
| `Severity::Error` | Le document enfreint le contrat. |
| `Severity::Warning` | Le document affaiblit le contrat, ou y fait une exception délibérée. |

`Report::fails($threshold)` vaut `true` quand un constat atteint le seuil : une erreur atteint les deux seuils, un
avertissement seulement `Severity::Warning`. `Report::reaching($threshold)` liste ces constats.

## Les règles

| Id | Niveau | Gravité | Ce qu'elle demande au document |
|---|---|---|---|
| `openapi-version` | minimal | erreur | `openapi` vaut `3.0.x`, `3.1.x` ou `3.2.x`. Un document Swagger 2.0 échoue. |
| `info` | minimal | erreur | Un objet `info` avec un `title` et un `version` non vides. |
| `paths` | minimal | erreur | Un objet `paths`, chaque clé commençant par `/` et contenant un objet d'élément de chemin. |
| `operation-responses` | minimal | erreur | Chaque opération a un objet `responses` non vide. |
| `path-parameters` | minimal | erreur | Chaque `{name}` d'un gabarit de chemin est un paramètre de chemin de chacune de ses opérations, déclaré sur l'élément de chemin ou sur l'opération, et marqué `required: true`; chaque paramètre de chemin figure dans son gabarit. |
| `operation-id-unique` | minimal | erreur | Deux opérations ne partagent jamais un `operationId`. |
| `local-references` | minimal | erreur | Chaque `$ref` qui commence par `#` pointe vers une valeur du document. Les références externes ne sont pas suivies. |
| `no-version-in-path` | recommended | erreur | Aucun chemin et aucun `servers[].url` n'a de segment comme `v1`, `V2` ou `v3.0.1`. |
| `success-response` | recommended | erreur | Chaque opération déclare une réponse `2XX` ou `3XX`. |
| `json-content` | recommended | avertissement | Chaque corps de requête et de réponse d'une opération est de type `application/json` ou `application/…+json`. |
| `problem-details` | recommended | avertissement | Chaque réponse `4XX`, `5XX` ou `default` qui a un corps offre `application/problem+json`. |
| `deprecation-sunset` | recommended | avertissement | Chaque opération marquée `deprecated: true` documente un en-tête `Sunset` sur l'une de ses réponses. |
| `operation-id` | recommended | avertissement | Chaque opération a un `operationId` non vide. |
| `extensible-enum` | strict | avertissement | Aucun schéma n'a de `enum` : les valeurs qui peuvent croître sont listées dans `x-extensible-enum` et lues avec une valeur par défaut, comme le fait `TolerantEnum::withDefault()`. Les variables d'une URL de serveur ne sont pas des schémas et ne sont pas signalées. |
| `error-responses` | strict | avertissement | Chaque opération déclare une réponse `4XX` ou une réponse `default`. |
| `operation-description` | strict | avertissement | Chaque opération a un `summary` ou un `description` non vide. |
| `security-requirement` | strict | avertissement | Le document déclare un `security` de premier niveau, sinon chaque opération déclare le sien; `security: []` dit qu'une opération est publique délibérément. |

`json-content` est un avertissement pour qu'une opération qui sert un fichier, une image ou un PDF puisse passer une
vérification qui échoue sur les erreurs seulement. `extensible-enum` signale aussi un champ d'autorité : ce champ garde
son `enum`, et le document est vérifié au niveau recommandé, ou la règle est écartée avec `ContractVerifier::without()`.

Les codes de réponse sont comparés comme OpenAPI les écrit : `200`, `2XX` ou `2xx`, `default`. Les réponses, les corps
de requête et les paramètres donnés comme références locales sont suivis, jusqu'à seize références dans une chaîne;
une chaîne qui boucle ou qui se rompt compte comme absente, et `local-references` signale où elle se rompt.

## Constats et rapports

| Type | Contient |
|---|---|
| `Finding` | `ruleId`, `severity`, `location`, `message`; `toArray()`. |
| `Report` | `level`, `findings` dans l'ordre d'exécution des règles; `fails()`, `reaching()`, `toArray()`. |

L'emplacement est un pointeur JSON (RFC 6901) dans le document : `/paths/~1orders~1{id}/get/responses`. L'emplacement et
le message citent le document, que n'importe qui peut avoir écrit : chacun est donc limité à de l'UTF-8 valide et à
1024 caractères, un caractère de contrôle étant remplacé par U+FFFD. Un rapport imprimé dans un terminal ou dans un
journal d'intégration continue montre le texte du document sans lui obéir.

## Lire un document

`OpenApiDocument::fromJson()` lit un texte JSON; `OpenApiDocument::fromArray()` prend un document déjà décodé, depuis du
YAML par exemple. Les deux refusent avec `UnreadableDocument` ce qui ne peut pas être vérifié du tout :

| Borne | Valeur |
|---|---|
| Taille du texte JSON | 8 Mio (`OpenApiDocument::MAX_BYTES`) |
| Profondeur | 128 niveaux (`OpenApiDocument::MAX_DEPTH`) |
| Valeurs | 500 000 (`OpenApiDocument::MAX_VALUES`) |
| Références suivies dans une chaîne | 16 |

Un document qui se lit mais enfreint des règles n'est pas refusé : ses constats sont dans le rapport. Les entiers hors
de la plage de PHP sont gardés comme chaînes. Une clé d'objet JSON que PHP lit comme un entier, un code de statut comme
`200`, est traitée comme la chaîne qu'elle est.

Les valeurs de `example`, de `examples` et des extensions `x-…` sont des données, pas une description : les règles qui
parcourent tout le document, `local-references` et `extensible-enum`, les écartent, sauf si elles nomment les propriétés
d'un schéma.

## Les règles du vérificateur

`ContractVerifier::withBuiltInRules()` contient les dix-sept règles ci-dessus, dans l'ordre du tableau, puis toute règle
qui lui est passée. `new ContractVerifier($rules)` contient exactement les règles données.
`without('operation-description')` retourne un vérificateur sans ces règles et refuse un identifiant qu'il ne contient
pas, pour qu'une faute de frappe ne garde pas une règle en silence. `rulesAt($level)` liste les règles qu'un niveau
exécute.

Un identifiant de règle compte de 1 à 64 lettres minuscules, chiffres et traits d'union, commence par une lettre et est
unique parmi les règles d'un vérificateur; le constructeur refuse tout le reste avec `InvalidContractValue`. La règle
propre à une application implémente `Contract\OpenApiRule` : voyez
[Points d'extension](extension-points.md#openapirule).

Le vérificateur et les règles ne gardent aucun état d'un document à l'autre : une instance en vérifie autant qu'on veut,
depuis n'importe quelle requête ou n'importe quel *worker*.
