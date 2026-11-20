<!-- phil-dev source-sha256=ddd334b6771832b32b8a89eef59764c1d3e0cc8f8c4cbdd5be6fc085ade14168 -->
# Notes de sécurité

## Ce dont le bundle se défend

- **Les adresses courriel dans les identifiants.** Chaque identifiant compte de 1 à 128 lettres ASCII, chiffres, `_`,
  `.` ou `-` ; un identifiant avec `@` est refusé, de sorte qu'aucune adresse n'atteint une table, un journal ou un
  événement par un identifiant de membre.
- **L'élévation de privilèges par les octrois.** Un membre n'accorde, ne change ou ne retire que des rôles dont il
  détient lui-même les permissions, sur des membres dont les rôles sont dans les siens ; un administrateur ne peut ni
  nommer, ni rétrograder, ni retirer un propriétaire. Une invitation est refusée quand son créateur ne peut plus inviter
  ni accorder ses rôles.
- **Les organisations orphelines.** Le dernier propriétaire ne peut jamais partir, être retiré ni perdre le rôle de
  propriétaire. Chaque changement verrouille la ligne de l'organisation — `SELECT … FOR UPDATE` sur PostgreSQL, MySQL et
  MariaDB ; SQLite écrit une transaction à la fois —, de sorte que deux changements simultanés ne peuvent pas retirer
  tous deux un propriétaire.
- **Les tables volées.** Le jeton d'une invitation n'est conservé que sous la forme de son HMAC-SHA-256 calculé avec la
  clé de l'application, sous des clés en rotation ; le jeton n'apparaît dans aucun événement, journal ni table. Les
  empreintes sont recherchées par un index unique, et une empreinte à clé ne dit rien d'un jeton par son temps de
  réponse.
- **La double utilisation.** Chaque utilisation d'une invitation est un seul `UPDATE` conditionnel dont le nombre de
  lignes touchées est vérifié : une invitation n'est jamais utilisée plus de fois qu'elle ne le permet, ni après son
  expiration ou sa révocation.
- **L'énumération.** Une invitation refusée lève le même message quelle qu'en soit la cause ; la cause est une propriété
  pour l'application, pas pour le visiteur.
- **La confusion de casse.** Chaque colonne d'identifiant et de clé se compare octet par octet — `ascii_bin` sur MySQL
  et MariaDB, la collation `C` sur PostgreSQL —, de sorte que `Acme` n'est jamais `acme`.
- **Les cycles et les arbres démesurés.** Une unité ne se déplace jamais sous elle-même ni sous une unité qui est en
  dessous d'elle ; les déplacements d'une organisation s'exécutent l'un après l'autre sous le verrou de sa ligne, de
  sorte que deux déplacements ne peuvent pas faire un cycle ensemble. Un arbre compte seize niveaux de profondeur et dix
  mille unités de largeur au plus, de sorte que chaque question de l'arbre reste une requête bornée.
- **Les rôles d'unité qui sortent de leur unité.** Un rôle d'unité vaut dans son unité et en dessous, jamais au-dessus,
  à côté, ni dans l'organisation ; un gestionnaire d'unité n'accorde que des rôles d'unité dont il détient les
  permissions à cet endroit, et gère les unités en dessous de la sienne, pas la sienne.
- **Les formulaires falsifiés.** Les formulaires des pages des unités portent le jeton CSRF sans état de Symfony et sont
  refusés quand le navigateur dit qu'ils viennent d'un autre site.
- **Les événements fantômes.** Les événements sont émis une fois la transaction validée, jamais pour un changement
  annulé.
- **Les renseignements personnels dans les événements.** Les événements ne portent que des identifiants et des noms de
  rôle — le nom d'une unité n'est pas dans ses événements — ; une adresse vérifiée fournie pour adhérer par un domaine est lue pour son domaine et n'est jamais conservée,
  journalisée ni émise.
- **Les *workers*.** Aucun service ne garde d'état entre les requêtes ; le contexte d'`OnBehalf` et le magasin en
  mémoire sont réinitialisés entre elles.

## Ce que fait l'application

- Authentifier les membres et donner au bundle leur identifiant de sujet stable, jamais une adresse courriel.
- Vérifier un domaine de courriel avant de le consigner, et l'adresse d'un membre avant d'adhérer par le domaine.
- Envoyer les liens d'invitation par un canal de confiance, et garder `ORGANIZATIONS_INVITATION_KEY` dans le coffre de
  secrets.
- Vérifier les permissions de ses propres écrans avec le *voter*, et échapper les noms à l'affichage comme tout texte
  saisi par un membre.
- Nommer un nouveau propriétaire quand `OrganizationLeftWithoutOwner` est émis, après avoir vérifié la demande par un
  autre canal.
- Limiter le débit du point de terminaison qui accepte les invitations, de sorte que deviner des jetons coûte plus cher
  que ne le font déjà 256 bits.
