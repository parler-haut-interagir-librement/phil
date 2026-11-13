<!-- phil-dev source-sha256=4aaf32da5593efe8eab0b81fa5f8c2ffddd5e1244cef155dca322e4356651d4d -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Ce que choisit une application passe par des arguments ;
trois interfaces lui permettent d'apporter ce que la bibliothèque ne contient pas.

## `CertificateSource`

`Phil\PagedMedia\Gotenberg\Signing\CertificateSource` — le certificat avec lequel un PDF est signé, demandé à chaque
signature pour qu'un certificat renouvelé soit pris sans redémarrage. Voir [Signature](signing.md).

| Méthode | Contrat |
|---|---|
| `certificate(): Certificate` | Le certificat et sa clé, tels que les construisent `Certificate::pkcs12()` ou `Certificate::pem()` ; `CertificateUnavailable` quand il ne peut pas être lu |

`FileCertificateSource`, `InlineCertificateSource` et `VaultCertificateSource` sont fournies. Une application qui a son
propre coffre implémente l'interface, et ne garde jamais la clé dans un journal ou un message.

## `TimestampAuthority`

`Phil\PagedMedia\Gotenberg\Signing\TimestampAuthority` — une autorité RFC 3161.

| Méthode | Contrat |
|---|---|
| `url(): string` | L'URL de l'autorité, que Gotenberg appelle lui-même |

`TimestampAuthorityUrl` en est une connue par son URL ; une application qui choisit l'autorité à l'exécution
implémente l'interface.

## `Stage`

`Phil\PagedMedia\Gotenberg\Stage\Stage` — une opération sur un PDF, dans un `Pipeline` ou seule. Voir
[Étapes](stages.md).

| Méthode | Contrat |
|---|---|
| `operation(): Operation` | L'opération de Gotenberg qu'elle représente |
| `options(): array` | Les options telles que les nomme Gotenberg, celles qui ne sont pas réglées étant omises |
| `files(): array` | Les fichiers que nomment les options, téléversés à côté des PDF |

Les opérations forment une énumération fermée, celle de Gotenberg : une étape de l'application construit ses options
autrement — à partir de son stockage, par exemple — pour une opération que la bibliothèque connaît.

## Polices

Le moteur d'écriture natif accepte une `StandardFont` ou une `TrueTypeFont` et rien d'autre : ce qu'il écrit, il doit
savoir l'intégrer. `Phil\PagedMedia\Font\Font` — `width(string $text, float $size): float`, en points — est ce que les
deux donnent au code de mise en page de l'application pour mesurer le texte. Voir [Polices](fonts.md).

## Arguments

| Point | Comment |
|---|---|
| L'emplacement de Gotenberg | Le client HTTP, l'URL de base, `BasicAuth` et le délai donnés à `Gotenberg` — voir [Gotenberg](gotenberg.md) |
| Les résultats livrés plus tard | Un `Webhook` dans `RequestOptions`, et `WebhookEvent::fromJson()` sur le point de terminaison de l'application |
| Les polices intégrées | `TrueTypeFont` à partir des fichiers de l'application, réduites à l'écriture ; c'est à l'application de respecter leurs licences |
| Les gabarits d'imprimés | Une `LabelSheet` du stock de l'application, `TemplateFonts`, et les valeurs déjà formatées dans la langue du lecteur ; un gabarit maison dessine sur le `Document` qu'un gabarit renvoie — voir [Gabarits d'imprimés](templates.md) |
| La page d'un moteur HTML et CSS | Une `PageRule` au format, aux marges, au fond perdu et aux marques de l'application, donnée au moteur comme feuille de style — voir [Moteurs HTML et CSS](html-and-css.md) |
| Les champs de formulaire | `TextField`, `CheckBox`, `RadioGroup` et `ChoiceField` construits à partir des données de l'application, et `render(flattenForm: true)` pour une copie qui ne peut pas être modifiée — voir [Formulaires](forms.md) |
