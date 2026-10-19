<?php declare(strict_types=1);

namespace Base3Ilias\Group;

/** Discoverable code-owned group; only editable groups accept host-configured criteria. */
final class Base3IliasAuthenticatedGroup extends AbstractIliasGroup {
	protected const GROUP_NAME = 'authenticated';
	protected const DESCRIPTION = 'Authenticated ILIAS users.';
	protected const EDITABLE = false;
	protected const CRITERIA = ['criterion' => 'base3authenticatedcriterion', 'config' => []];
}
