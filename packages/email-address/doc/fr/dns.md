<!-- phil-dev source-sha256=483f21e2ae12d0cba3df0c4a5d04c55aed5682239e374162ac9d26c04fd81ef4 -->
# DNS

`Contract\MailDomainResolver::resolve(string $asciiDomain): MailDomain` dit si un domaine reçoit le courriel. Le
validateur l'interroge avec la forme ASCII du domaine, sans point final.

| `MailDomain` | Le DNS a dit | `acceptsMail()` |
|---|---|---|
| `Mx` | Un ou plusieurs enregistrements MX | `true` |
| `AddressOnly` | Aucun MX, mais un enregistrement A ou AAAA : l'hôte reçoit son propre courriel (RFC 5321, section 5.1) | `true` |
| `NullMx` | Un seul enregistrement MX dont l'échangeur est la racine (RFC 7505) : le domaine ne reçoit aucun courriel | `false` |
| `NoRecord` | Aucun enregistrement MX, A ni AAAA | `false` |
| `Unknown` | La résolution a échoué | `null` |

## `Dns\DnsGetRecordResolver`

Interroge le résolveur du système par `dns_get_record()` : MX d'abord, puis A, puis AAAA, en s'arrêtant à la première
réponse. L'hôte est écrit avec un point final, pour qu'aucun domaine de recherche de la machine n'y soit ajouté. Une
résolution qui échoue donne `Unknown`.

`dns_get_record()` bloque aussi longtemps qu'attend le résolveur du système ; `timeout` et `attempts` dans
`resolv.conf` le bornent. Un formulaire qui ne peut pas attendre vérifie le DNS plus tard, dans un *handler* Messenger
qui reçoit l'identifiant de l'abonnement, jamais l'adresse, ou il enveloppe le résolveur dans son propre délai.

Une fonction anonyme donnée au constructeur remplace `dns_get_record()` : elle reçoit l'hôte et le type
d'enregistrement, et retourne les enregistrements, ou `false` quand la résolution a échoué.

## `Dns\StaticResolver`

Répond d'après une table de domaines ASCII, et par une seule réponse pour les autres, `MailDomain::Unknown` par
défaut : pour les tests, pour les domaines que connaît l'application, ou pour désactiver la vérification dans un
environnement sans DNS.

## Cache

La bibliothèque ne met rien en cache : un résolveur ne garde aucun état entre les appels, pour qu'un *worker* de
longue durée serve chaque requête de la même façon. Une application qui vérifie de nombreuses adresses des mêmes
domaines enveloppe le résolveur dans son propre adaptateur, adossé à son cache, avec le domaine ASCII pour clé et une
durée de quelques minutes : un domaine qui perd son MX ne devrait pas rester accepté pendant des jours.
