<?php
/** @var string $editorPrefix */
/** @var string|int $rowIndex */
/** @var array $rule */
/** @var bool $allowGroups */
/** @var bool $allowRoles */
/** @var array $availableGroups */
/** @var array $criterionOptions */
$kind = (string)($rule['kind'] ?? ($allowGroups ? 'group' : 'user'));
$numberActive = in_array($kind, ['user', 'role'], true);
$criterionActive = $kind === 'criterion';
$configActive = in_array($kind, ['criterion', 'expression'], true);
$value = (string)($rule['value'] ?? '');
$config = (string)($rule['config'] ?? '{}');
$name = $editorPrefix . '[' . $rowIndex . ']';
$esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="base3-audience-row" data-audience-row>
	<label class="base3-audience-field"><?php echo $esc($t('assignment_type', 'Type')); ?>
		<select name="<?php echo $esc($name); ?>[kind]" data-audience-kind>
			<option value="user"<?php echo $kind === 'user' ? ' selected' : ''; ?>><?php echo $esc($t('assignment_user', 'ILIAS user')); ?></option>
			<?php if ($allowRoles): ?><option value="role"<?php echo $kind === 'role' ? ' selected' : ''; ?>><?php echo $esc($t('assignment_role', 'ILIAS role')); ?></option><?php endif; ?>
			<?php if ($allowGroups): ?><option value="group"<?php echo $kind === 'group' ? ' selected' : ''; ?>><?php echo $esc($t('assignment_group', 'BASE3 group')); ?></option><?php endif; ?>
			<option value="criterion"<?php echo $kind === 'criterion' ? ' selected' : ''; ?>><?php echo $esc($t('assignment_criterion', 'Criterion')); ?></option>
			<option value="expression"<?php echo $kind === 'expression' ? ' selected' : ''; ?>><?php echo $esc($t('assignment_expression', 'Advanced expression')); ?></option>
		</select>
	</label>
	<label class="base3-audience-field" data-audience-number-wrap<?php echo $numberActive ? '' : ' hidden'; ?>>
		<span data-audience-number-label><?php echo $esc($t('user_id', 'ILIAS user ID')); ?></span>
		<input type="number" inputmode="numeric" min="1" step="1" name="<?php echo $esc($name); ?>[value]" value="<?php echo $esc($value); ?>" data-audience-number<?php echo $numberActive ? ' required' : ' disabled'; ?><?php echo $kind === 'role' ? ' list="' . $esc($roleListId) . '"' : ''; ?>>
	</label>
	<?php if ($allowGroups): ?>
		<label class="base3-audience-field" data-audience-group-wrap<?php echo $kind === 'group' ? '' : ' hidden'; ?>><?php echo $esc($t('assignment_group', 'BASE3 group')); ?>
			<select name="<?php echo $esc($name); ?>[value]" data-audience-group<?php echo $kind === 'group' ? ' required' : ' disabled'; ?>>
				<?php foreach ($availableGroups as $groupName): ?>
					<option value="<?php echo $esc($groupName); ?>"<?php echo $value === $groupName ? ' selected' : ''; ?>><?php echo $esc($groupName); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	<?php endif; ?>
	<label class="base3-audience-field" data-audience-criterion-wrap<?php echo $criterionActive ? '' : ' hidden'; ?>><?php echo $esc($t('assignment_criterion', 'Criterion')); ?>
		<select name="<?php echo $esc($name); ?>[value]" data-audience-criterion<?php echo $criterionActive ? ' required' : ' disabled'; ?>>
			<?php foreach ($criterionOptions as $criterion): ?>
				<option value="<?php echo $esc($criterion['name']); ?>"<?php echo $value === $criterion['name'] ? ' selected' : ''; ?>><?php echo $esc($criterion['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<div class="base3-audience-config" data-audience-config-wrap<?php echo $configActive ? '' : ' hidden'; ?>>
		<div class="base3-audience-properties" data-audience-properties></div>
		<label class="base3-audience-field" data-audience-json-wrap<?php echo $configActive ? '' : ' hidden'; ?>><?php echo $esc($t('criterion_config', 'Configuration (JSON)')); ?>
			<textarea rows="2" spellcheck="false" name="<?php echo $esc($name); ?>[config]" data-audience-json<?php echo $configActive ? '' : ' disabled'; ?>><?php echo $esc($config); ?></textarea>
		</label>
		<div class="base3-audience-schema" data-audience-schema aria-live="polite"></div>
	</div>
	<button class="btn btn-default" type="button" data-audience-remove aria-label="<?php echo $esc($t('remove_assignment', 'Remove assignment')); ?>"><?php echo $esc($t('remove_assignment', 'Remove assignment')); ?></button>
</div>
