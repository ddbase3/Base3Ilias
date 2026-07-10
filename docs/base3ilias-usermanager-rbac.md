# Base3IliasUsermanager RBAC Adapter

This package updates:

```text
Base3Ilias-main/src/Base3/Base3IliasUsermanager.php
```

The class still implements:

```text
Base3\Usermanager\Api\IUsermanager
Base3\Api\ICheck
```

## Purpose

The adapter maps ILIAS user and RBAC data from the shared ILIAS `$DIC` into the BASE3 usermanager model.

It keeps the existing no-argument constructor so the current Base3Ilias plugin registration can stay unchanged:

```php
->set(IUsermanager::class, fn() => new Base3IliasUsermanager(), IContainer::SHARED)
```

## Implemented behavior

### Current user

`getUser()` returns a `Base3\Usermanager\User` with:

```text
id       ILIAS usr_id as string
userid   ILIAS login
name     ILIAS full display name
email    ILIAS email
lang     ILIAS language
role     BASE3 compatibility role: visit | member | admin
roles    effective ILIAS roles mapped as BASE3 Role objects
```

Anonymous user is ignored and returns `null`.

### Roles

`getRoles()` reads assigned ILIAS roles through:

```php
ilRbacReview::assignedRoles($userId)
ilRbacReview::assignedGlobalRoles($userId)
```

Each ILIAS role becomes a `Base3\Usermanager\Role`:

```text
id       ILIAS role obj_id
name     normalized technical role title
label    original ILIAS role title
info     ILIAS global/local role note
archive  0
```

If the current user is detected as ILIAS administrator, a synthetic BASE3 role is also added:

```text
name = admin
```

This keeps BASE3 compatibility for calls such as:

```php
$usermanager->hasRole(Role::named('admin'))
```

### Permissions

`getPermissions()` maps ILIAS administrator privileges to BASE3 system grants:

```text
system/admin
entry/admin
```

This is the important bridge for Memora/XRM admin bypass:

```php
$usermanager->can(Permission::for('entry', 'admin'))
```

`getAllPermissions()` additionally exposes ILIAS operation names from:

```php
ilRbacReview::getOperations()
```

as permissions with:

```text
scope = ilias
permission = <operation-name>
```

### Object-specific ILIAS permissions

BASE3 `Permission` does not have a dedicated object/ref-id field. The adapter therefore supports this convention:

```php
$usermanager->can(Permission::for('ilias:123', 'read'));
$usermanager->can(Permission::for('ilias:123', 'write'));
```

Here `123` is an ILIAS `ref_id` and `read` / `write` is the ILIAS operation name.

For compatibility, this form is also accepted:

```php
$usermanager->can(Permission::for('ilias', 'read:123'));
```

The check resolves:

```text
current user's assigned roles
parent roles of the target ref_id
role operations on that object
operation id by operation name
```

### Groups

`getGroups()` and `getAllGroups()` currently return an empty array.

This is intentional for now. ILIAS RBAC roles are not mapped to BASE3 groups. BASE3 groups and ILIAS repository groups are semantically different enough that this should be handled deliberately later, not hidden in the RBAC adapter.

### Mutations

The following methods return `false` and do not mutate ILIAS RBAC:

```text
registUser()
changePassword()
assignRoleToUser()
revokeRoleFromUser()
assignRoleToGroup()
revokeRoleFromGroup()
addPermissionToRole()
removePermissionFromRole()
```

Reason: this adapter is a read-side bridge from ILIAS RBAC into BASE3. ILIAS role and permission administration should remain under ILIAS-owned administration flows unless a dedicated write adapter is designed.

## Notes

The adapter depends on the ILIAS services already available through the shared container:

```text
accesscontrol
ilAuthSession
ilUser
rbacreview
```

It also uses the ILIAS classes already used elsewhere in the component:

```text
ilObjUser
ilObject
ilRbacReview
```
