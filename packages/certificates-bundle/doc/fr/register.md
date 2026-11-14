<!-- phil-dev source-sha256=7edef319a1fe1953eb54a33bc435071dd2d0abc5883052a63cb7658db1025661 -->
# Le registre

Les routes de `config/routes.php`, sous le préfixe que donne l'application :

| Route | Chemin | Réponse |
|---|---|---|
| `phil_certificates_register` | `/` | Les formations ouvertes et un formulaire pour trouver un certificat par son code; `?code=` redirige vers la page du certificat, ou répond `404` avec le formulaire |
| `phil_certificates_course` | `/courses/{slug}` | La formation, ses critères, sa fin et les crédits qu'exige sa recertification, et les certificats affichés, 50 par page, les plus récents d'abord; `404` pour un *slug* inconnu |
| `phil_certificates_certificate` | `/{code}` | La page du certificat : valide, expiré ou révoqué, avec son destinataire, sa formation, ses dates, les certificats qu'il renouvelle et ceux qui le renouvellent et, une fois révoqué, le motif |
| `phil_certificates_pdf` | `/{code}/certificate.pdf` | Le PDF, affiché dans le navigateur |
| `phil_certificates_credential` | `/{code}/credential` | L'attestation signée, en téléchargement, ouverte aux autres origines |

La recherche pardonne les minuscules, les tirets et les espaces, ainsi que les lettres lues comme des chiffres. Les
chemins prennent le code sous forme de douze caractères en majuscules, tel que l'imprime le code QR.

## Ce que divulgue le registre

Le code d'un certificat est ce qui y donne accès : soixante bits aléatoires, trop pour être devinés. Sa page montre le
nom imprimé dessus, la formation et les dates, soit ce que montre le papier, ainsi que les codes des certificats qu'il renouvelle et de ceux qui le renouvellent, jamais
l'identifiant de son titulaire. Un certificat non affiché ne figure sur
aucune page sauf la sienne, et cette page demande aux moteurs de recherche de ne pas l'indexer
(`X-Robots-Tag: noindex`); le PDF et l'attestation ne sont jamais indexés.

Les pages de formation peuvent être mises en cache cinq minutes par les navigateurs et les caches partagés. La page et
les fichiers d'un certificat sont revalidés à chaque requête (`Cache-Control: no-cache`), pour qu'une révocation se
voie sur-le-champ; le PDF et l'attestation d'un certificat révoqué répondent `410 Gone`. Chaque page est envoyée avec
`Referrer-Policy: no-referrer`.

## Les pages

Les gabarits `@PhilCertificates/register.html.twig`, `course.html.twig` et `certificate.html.twig` étendent la mise en
page de `register.layout`, qui définit les blocs `title` et `content`; chacun se remplace comme n'importe quel gabarit
de bundle. Les textes se trouvent dans le domaine de traduction `phil_certificates`, en anglais et en français.
