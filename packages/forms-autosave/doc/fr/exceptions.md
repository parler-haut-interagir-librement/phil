<!-- phil-dev source-sha256=da524acdbfd9b14da4f5fddd1d9715398234ac92a935ec0e726c045b8ad3ebd9 -->
# Exceptions

Chaque exception du bundle implémente `Phil\FormsAutosave\Domain\Exception\AutosaveException`.

| Exception | Lancée quand | Réponse du point de terminaison |
|---|---|---|
| `Domain\Exception\InvalidDraft` | Une clé, une version, des champs, une révision ou une ligne stockée dont on ne peut pas faire un brouillon ; un sujet qui n'est ni un texte, ni un nombre, ni null | `400` |
| `Application\Exception\RevisionConflict` | Un enregistrement nomme une autre révision que celle conservée ; `$current` est cette révision, ou null quand le brouillon a disparu | `409` |
| `Application\Exception\DraftTooLarge` | Le brouillon encodé dépasse `server.max_bytes` | `413` |

Le conteneur s'arrête avec une `LogicException` du composant DependencyInjection quand il manque au stockage serveur la
protection CSRF, un propriétaire ou un chiffreur ; voir [Configuration](configuration.md).
