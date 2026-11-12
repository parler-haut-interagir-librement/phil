<!-- phil-dev source-sha256=8d18805dc0a10e7f8593b7db42aa1f7667e770b2697a81669e4c3520efac7784 -->
# Validation

`EmailValidator::validate(string $input): Validation` exécute les étapes dans l'ordre, chacune seulement si les
précédentes ont réussi, et ne lève jamais d'exception.

| Étape | Configurée par | Problème |
|---|---|---|
| 1. Syntaxe | `$policy`, une `SyntaxPolicy` | `Problem::Syntax`, la raison dans `syntaxError` |
| 2. Domaine de premier niveau | `$topLevelDomains`, un `TopLevelDomains` | `Problem::UnknownTopLevelDomain` |
| 3. Règles de domaine | `$rules`, un `DomainRules` | `Problem::DeniedDomain`, `Problem::DomainNotAllowed` |
| 4. Domaines jetables | `$disposable`, un `DisposableDomains` | `Problem::Disposable` |
| 5. DNS | `$resolver`, un `Contract\MailDomainResolver` | `Problem::RefusesMail` (MX nul), `Problem::NoMailServer` |

Une étape dont l'argument est `null` est sautée : par défaut, seule la syntaxe est vérifiée. Un `TopLevelDomains` vide
saute aussi son étape, comme la liste fournie avant sa première importation, et un littéral IP n'a pas de domaine de
premier niveau à vérifier. Le domaine de premier niveau est vérifié avant le DNS : `ada@example.con` est ainsi refusée
sans résolution. Le DNS n'est jamais
interrogé pour un littéral IP, ni pour une adresse qu'une étape précédente a refusée : une adresse refusée ne coûte
donc aucune résolution. Une résolution qui échoue (`MailDomain::Unknown`) ne refuse rien : un résolveur en panne ne
ferme pas un formulaire d'inscription.

La suggestion vient de `$suggester`, un `TypoSuggester` par défaut, et elle est cherchée quel que soit le résultat,
aussi pour un texte dont la syntaxe échoue : `ada@gmailcom` reçoit ainsi `ada@gmail.com`. `null` la désactive.

## Le résultat

| Membre | Contient |
|---|---|
| `address` | L'`EmailAddress` quand la syntaxe est bonne, sinon `null` |
| `syntaxError` | La `SyntaxError` quand elle ne l'est pas |
| `problems` | La liste des `Problem` ; vide quand l'adresse est acceptée |
| `suggestion` | L'`EmailAddress` que la personne voulait sans doute écrire, ou `null` |
| `mailDomain` | Le `MailDomain` qu'a répondu le résolveur, ou `null` quand il n'a pas été interrogé |
| `isValid()` | Aucun problème |
| `has(Problem)` | Ce problème est dans la liste |

```php
$validation = $validator->validate($form['email']);

if (!$validation->isValid()) {
    $error = match ($validation->problems[0]) {
        Problem::Syntax => 'email.syntax.'.$validation->syntaxError?->value,
        Problem::Disposable => 'email.disposable',
        default => 'email.undeliverable',
    };
}

if (null !== $validation->suggestion) {
    $hint = $validation->suggestion->toString(); // "Did you mean …?"
}
```

Une suggestion est une question posée à la personne, jamais une correction que l'application fait d'elle-même.
