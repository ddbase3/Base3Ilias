<?php declare(strict_types=1);

namespace Base3Ilias\Group;

/** Discoverable code-owned group; only editable groups accept host-configured criteria. */
final class Base3IliasAnonymousGroup extends AbstractIliasGroup {
	protected const GROUP_NAME = 'anonymous';
	protected const DESCRIPTION = 'Unauthenticated ILIAS users.';
	protected const EDITABLE = false;
	protected const CRITERIA = ['criterion' => 'base3anonymouscriterion', 'config' => []];
}
