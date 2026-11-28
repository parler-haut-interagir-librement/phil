# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `AccessPolicy` | What a content asks of its reader: audience, rule, early access, preview, denial |
| `Audience` | `Public`, `Members` or `Restricted` |
| `WhenDenied` | `Locked` or `Hidden`: what a viewer without the right sees |
| `AccessDecider` | Decides a policy for a viewer at the clock's instant |
| `Decision` | What the viewer may see, why, whether it is personal, when it changes, and the explanation |
| `Access` | `Full`, `Preview`, `Locked` or `Hidden` |
| `Reason` | The stable code of a decision |
| `Viewer` | Who asks: identifier, roles, verified email domain, network address |
| `AccessFacts` | The facts a rule of access reads, under the names the library sets |
| `PreviewCut` | The blocks of a UJO (Universal JSON Output) document a decision lets through |
| `StructuredData` | The schema.org properties of a reserved content |
| `Condition\AccessConditions` | The registry of the condition types of access |
| `Condition\MemberCondition` | `access.member` |
| `Condition\RoleCondition` | `access.role` |
| `Condition\FeatureCondition` | `access.feature`, with an opening by seniority and a rolling duration |
| `Condition\InstitutionCondition` | `access.institution`, by verified email domain or network range |
| `Entitlement\Entitlements` | The port that gives the grants of a person |
| `Entitlement\Grant` | A feature held from a start until an optional end, and its source |
| `Entitlement\InMemoryEntitlements` | Grants held in memory, keyed by subject |
| `Entitlement\ChainEntitlements` | The grants of several sources together |
| `Json\AccessPolicyReader` | Reads the versioned JSON form of a policy, strictly |
| `Json\AccessPolicyWriter` | Writes the canonical form of a policy, and its fingerprint |
| `Exception\ContentAccessException` | Every exception of the library |
| `Exception\InvalidPolicy` | A contradictory or malformed policy |
| `Exception\InvalidViewer` | A viewer with a malformed member |
| `Exception\InvalidGrant` | A grant with a malformed feature or source, or an end before its start |
| `Exception\ReservedFact` | An application's fact under a name the library sets |
