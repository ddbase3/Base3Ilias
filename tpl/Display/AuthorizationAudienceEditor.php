<?php
/** @var string $editorPrefix */
/** @var array $editorRows */
/** @var bool $allowGroups */
/** @var bool $allowRoles */
/** @var array $availableGroups */
/** @var array $criterionOptions */
/** @var string $roleListId */
$schemaCatalog = [];
foreach ($criterionOptions as $criterion) $schemaCatalog[$criterion['name']] = $criterion['schema'];
$esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="base3-audience-editor" data-audience-editor data-counter="<?php echo count($editorRows); ?>"
	data-schemas="<?php echo $esc(json_encode($schemaCatalog, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); ?>"
	data-role-list-id="<?php echo $esc($roleListId); ?>">
	<div data-audience-list>
		<?php foreach ($editorRows as $rowIndex => $rule): ?>
			<?php require __DIR__ . '/AuthorizationAudienceRow.php'; ?>
		<?php endforeach; ?>
	</div>
	<template data-audience-template>
		<?php $rowIndex = '__INDEX__'; $rule = []; require __DIR__ . '/AuthorizationAudienceRow.php'; ?>
	</template>
	<button class="btn btn-default" type="button" data-audience-add><?php echo $esc($t('add_assignment', 'Add assignment')); ?></button>
	<p class="base3-audience-hint"><?php echo $esc($t('assignment_hint', 'Assignments are combined with OR. Advanced expressions support AND, OR and NOT.')); ?></p>
</div>
