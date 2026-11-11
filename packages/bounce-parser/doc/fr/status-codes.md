<!-- phil-dev source-sha256=fc2e83dd977828fd82735672405ad11005bc9aa7d9e33e0fadbde6725b83a3e5 -->
# Codes d'état

`StatusCode` est un code d'état étendu du système de courriel (RFC 3463), `class.subject.detail`.

| Membre | Donne |
|---|---|
| `StatusCode::parse(string)` | Le code écrit seul, espaces autour permises, ou `null` |
| `StatusCode::find(string)` | Le premier code dans un texte, ou `null` ; un code au milieu d'une suite plus longue de chiffres et de points — une version, une adresse IP — n'en est pas un |
| `class`, `subject`, `detail` | Les trois nombres |
| `isSuccess()`, `isTransient()`, `isPermanent()` | Classe 2, 4, 5 |
| `isAboutTheMessage()` | Une boîte pleine (`x.2.2`), un message trop long (`x.2.3`, `x.3.4`), un refus par politique (`x.7.*`) |
| `describe()` | Le sujet en quelques mots anglais : adressage, boîte de courriel, système de courriel, réseau et routage, protocole, contenu, sécurité ou politique |
| `toString()`, `(string)` | `5.1.1` |

La classe vaut 2, 4 ou 5 ; le sujet et le détail ont d'un à trois chiffres.
