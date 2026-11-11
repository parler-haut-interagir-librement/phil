<!-- phil-dev source-sha256=60d2bf27406caddf461f0e02cb01f16db3bc73de91f54fff2703081874ff81d0 -->
# Formatage

| `PhoneFormat` | `+33 6 12 34 56 78` | `+1 514 555 0123 x12` |
|---|---|---|
| `E164` | `+33612345678` | `+15145550123` |
| `International` | `+33 6 12 34 56 78` | `+1 514-555-0123 ext. 12` |
| `National` | `06 12 34 56 78` | `(514) 555-0123 ext. 12` |
| `Rfc3966` | `tel:+33-6-12-34-56-78` | `tel:+1-514-555-0123;ext=12` |

Les groupes suivent le plan du pays principal de l'indicatif. La forme nationale ajoute le préfixe national comme ce
pays l'écrit : `0 `, `(0)`, `8 (800)`. Un poste s'écrit comme son pays l'écrit, ` x12` au Royaume-Uni, ` ext. 12` par
défaut. Un numéro qu'aucun format ne couvre s'écrit sous forme de ses chiffres.

## Stocker un numéro

`(string) $number` est la forme E.164 avec le poste écrit à la façon de la RFC 3966, `+15145550123;ext=12`, et
`parse()` la relit en un numéro égal. C'est la forme à stocker, à comparer et à envoyer aux services.
`phil/phone-bundle` la fait correspondre au type de colonne `phil_phone`.
