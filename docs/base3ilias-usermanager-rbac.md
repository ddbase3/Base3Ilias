# ILIAS 11: BASE3 users, group criteria and native authorization

This document describes the ILIAS 11 BASE3 integration. See the normative
BASE3 Framework `docs/authorization-model.md` and `docs/authorization-adapters.md`.

## Native ILIAS authority

`Base3IliasUsermanager` resolves native user identity, effective native roles,
compatibility role metadata, and discoverable BASE3 groups. It is **not** an
additional permission engine. For the exact ILIAS authority `ilias`, the
project-bound `Base3IliasAuthorizationService` evaluates permissions through native
`ilRbacSystem::checkAccessOfUser`, using a trusted ILIAS user and repository
`ref_id`. Native local/global roles, scope and inheritance are never recomputed in
BASE3. `decideMany()` preloads the native RBAC permission cache when possible.

Native `system/admin` maps to membership in ILIAS's system administrator role.
BASE3 `feature` permissions are separate from native object operations: their
host-managed allowed-group settings are read through one
`Base3IliasFeatureConfiguration`. A plugin declares an `IFeature`; the runtime
checks it through the same single `IAuthorizationService` as native scopes.

```php
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Authorization\Dto\ResourceReference;
use Base3\Usermanager\Permission;

$allowed = $authorization->decide(new AuthorizationRequest(
    'ilias',
    Permission::for('ilias','read'),
    new ResourceReference('ilias','ref_id',$refId)
))->isAllowed();
```

Do not use `IUsermanager::can()`, `Permission::$target`, manual repository hierarchy
loops, session role snapshots or local reimplementations of ILIAS operation lookup.

## ILIAS admin integration

`Base3IliasAuthorizationAdministrationService` reads the native permission catalog,
local/direct grants, effective/inherited grants and editable role subjects via
`ilRbacReview`. It writes through `ilRbacAdmin::grantPermission()` in a native DB
transaction and invalidates native RBAC caches. A client cannot write grants merely
by passing a role ID: server-side `edit_permission`, role protection, type-specific
operation validation and resource identity are all checked first.

`Base3IliasGroupAdminDisplay` edits **BASE3 group criteria only**, not ILIAS roles.
`Base3IliasFeatureAdminDisplay` edits discoverable feature group grants. Both use
server-side administrator authorization, native ILIAS settings persistence and
session-protected AJAX forms. The UI exposes fixed code-owned groups as read-only;
additional groups and criteria can be supplied by project plugins via `IClassMap`.
Empty feature groups means no users are allowed to use that feature. No implicit
reset-to-default occurs after an explicit empty save.

## Versioned migration and compatibility

`Migration001ConvertGroupAssignments` transforms `base3ilias/groups` records from
`assignments[GROUP][user_ids|role_ids]` to the canonical `criteria` expression.
It is owned by Base3Ilias and executed by the configured DB migration runner after
plugin initialization but before request execution. No legacy read/write code remains
in the normal group-evaluation path. Back up the host settings before deployment.

ILIAS Activities are **optional**: the ILIAS RBAC API remains valid and required.
Activities do not replace native RBAC in any supported version. Verify any Activity
adapter against the actual ILIAS version API before enabling it. This first patch
was prepared from BASE3 components for ILIAS 11 and has not been runtime-tested
inside the deployment's complete ILIAS bootstrap.

## Privacy and performance

The host owns the user, email, role and RBAC data. Group criteria may include native
user IDs, role IDs and email-domain matching; they are stored in the configured ILIAS
settings backend when editable and need the deployment's own retention, access and
erasure policy. Native permission APIs and preload caches perform hierarchy and batch
checks; large ILIAS repository queries must use native permission-aware data sources,
not collect all entries and filter in PHP. Do not cache an authorization decision
beyond host invalidation guarantees. See the ILIAS module `PRIVACY.md`.
