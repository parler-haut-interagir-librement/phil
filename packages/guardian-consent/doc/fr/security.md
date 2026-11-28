<!-- phil-dev source-sha256=5475630a21c33476e96b1e23631a1b60903c4823974fc64cc5bca2d43bd5b6bc -->
# Sécurité et vie privée

## Ce que la bibliothèque garde

| Donnée | Gardée par | Personnelle |
|---|---|---|
| Un mineur : membre, juridiction, jour de l'âge du consentement, moments de l'inscription, de l'avis et du passage de relais | `MinorStore` | Que le membre est mineur, et à peu près son âge |
| Une demande : membre, identifiant et adresse du tuteur, fonctionnalités, version du texte, instants, empreinte du jeton | `RequestStore` | L'adresse du tuteur, jusqu'à ce que la demande reçoive une réponse ou soit effacée |
| Une preuve : membre, fonctionnalité, décision, acteur, identifiant du tuteur, version du texte, demande, instant | `ProofStore` | Qui a décidé quoi pour le membre |

La date de naissance est lue puis abandonnée : seul le jour où l'âge du consentement est atteint est gardé. L'adresse
du tuteur reste sur la demande en attente et part avec elle — répondue, abandonnée au passage de relais, ou effacée par
le balayage — ; une preuve nomme le tuteur par son identifiant. Les événements ne portent que des identifiants.

## Les jetons de confirmation

Un jeton est composé de l'identifiant de la demande, 16 octets aléatoires, et d'un vérificateur, 32 octets d'un
générateur cryptographiquement sûr, 256 bits, en Base64 compatible avec les URL. Le vérificateur n'est jamais stocké :
la demande garde le HMAC-SHA-256 de son identifiant et de son vérificateur sous la clé de l'application, de sorte que le
lien est signé par l'application et lié à sa demande, et qu'une copie du dépôt n'ouvre aucun lien. Les empreintes sont
comparées avec `hash_equals()`, sous toutes les clés à la fois.

Un jeton s'utilise une fois : `decide()` retire la demande par `RequestStore::consume()`, qu'un dépôt partagé exécute de
façon atomique, de sorte que deux réponses en concurrence obtiennent une seule preuve. La demande est retirée et chaque preuve de la
réponse écrite par un seul appel à `Transactions::run()` : quand une écriture échoue, la transaction est annulée, la
demande est de retour et son lien répond de nouveau, au lieu d'un jeton utilisé sur une réponse à moitié enregistrée. Un vérificateur falsifié laisse
la demande intacte. Chaque refus se lit de la même façon, afin de ne rien révéler de la vérification qui a échoué ;
seule une demande expirée le dit, une fois le vérificateur reconnu.

La clé compte au moins 32 octets. Pour la faire tourner, passez la nouvelle clé en premier et l'ancienne ensuite : les
liens émis sous l'ancienne continuent de fonctionner jusqu'à ce qu'elle soit abandonnée, après la plus longue durée de
vie d'une demande.

La page que le lien ouvre montre la demande et ne décide de rien : la décision est un formulaire, de sorte qu'un
analyseur de courriels qui suit le lien ne change rien. Le formulaire est protégé contre les requêtes intersites comme
les autres formulaires de l'application.

## Une collecte limitée à la finalité

Le tuteur est joint par courriel, avec une confirmation. Une transaction par carte ou une pièce d'identité prouverait
davantage sur le tuteur, et recueillerait bien plus que la finalité n'exige : la bibliothèque n'a aucun port pour elles.

## Accès et effacement

`GuardianConsentRights` implémente `Contract\SubjectRights` pour les outils de vie privée de l'application. Le sujet
est lu à la fois comme membre et comme tuteur.

| Lu comme | `export()` | `erase()` |
|---|---|---|
| Un membre | Son enregistrement, les demandes qui le concernent, les preuves de ses consentements | Tout est effacé |
| Un tuteur | Les demandes qui lui ont été envoyées, avec son adresse, et les preuves de ses décisions | Les demandes sont effacées ; les preuves, qui sont celles du membre, ne le nomment plus |

`erase()` renvoie le nombre d'enregistrements qu'elle a effacés ou modifiés, et 0 quand on la relance. Un sujet que la
bibliothèque n'a pas pu garder, comme une adresse de courriel, n'a rien.

## Des bornes sur chaque entrée

| Entrée | Borne |
|---|---|
| Un identifiant de membre ou de tuteur | De 1 à 255 octets, sans espace, caractère de contrôle ni `@` |
| L'adresse d'un tuteur | Un `@` entre deux parties non vides, sans espace, 254 octets au plus |
| Une fonctionnalité | De 1 à 64 lettres minuscules, chiffres, `.`, `_` ou `-` |
| Une version du texte | De 1 à 64 lettres, chiffres, `.`, `_`, `-` ou `+` |
| Une date de naissance | `YYYY-MM-DD`, pas après aujourd'hui, au plus 150 ans avant |
| Les fonctionnalités d'une demande, ou d'un passage de relais | De 1 à 64 pour une demande, jusqu'à 64 pour un passage de relais, chacune une fois |
| Demandes en attente pour un membre | 5 |
| La durée de vie d'une demande | De 1 à 30 jours, 7 par défaut |
| La conservation d'une demande sans réponse | De 0 à 365 jours, 30 par défaut |
| Un balayage | De 1 à 1000 enregistrements de chaque sorte, 100 par défaut |
| Une clé de jeton | 32 octets au moins ; jusqu'à 8 clés |
| Juridictions | 300 |

Le lecteur de jetons de confirmation est soumis au fuzzing.
