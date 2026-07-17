<?php
	$id = (string) $this->_['id'];
	$name = (string) $this->_['name'];
	$value = (string) $this->_['value'];
	$className = (string) $this->_['className'];
	$rows = (int) $this->_['rows'];
	$minimumHeight = (int) $this->_['minimumHeight'];
	$placeholder = (string) $this->_['placeholder'];
	$spellcheck = (bool) $this->_['spellcheck'];
	$readonly = (bool) $this->_['readonly'];
	$disabled = (bool) $this->_['disabled'];
	$ariaLabel = (string) $this->_['ariaLabel'];
	$tinyMceScriptUrl = (string) $this->_['tinyMceScriptUrl'];
	$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
?>
<style>
	.base3-ilias-rich-text-editor-wrapper {
		box-sizing: border-box;
		width: 100%;
		max-width: 100%;
		min-width: 0;
	}
	.base3-ilias-rich-text-editor-wrapper .tox-tinymce,
	.base3-ilias-rich-text-editor-wrapper .tox-editor-container,
	.base3-ilias-rich-text-editor-wrapper .tox-editor-header,
	.base3-ilias-rich-text-editor-wrapper .tox-toolbar-overlord,
	.base3-ilias-rich-text-editor-wrapper .tox-toolbar,
	.base3-ilias-rich-text-editor-wrapper .tox-toolbar__primary,
	.base3-ilias-rich-text-editor-wrapper .tox-edit-area {
		box-sizing: border-box;
		width: 100%;
		max-width: 100%;
		min-width: 0;
	}
	.base3-ilias-rich-text-editor-wrapper .tox-toolbar__primary {
		flex-wrap: wrap;
	}
</style>
<div
	class="base3-ilias-rich-text-editor-wrapper"
	data-base3-rich-text-editor-wrapper="base3ilias"
>
	<textarea
		id="<?php echo htmlspecialchars($id, ENT_QUOTES); ?>"
		class="<?php echo htmlspecialchars($className, ENT_QUOTES); ?>"
		rows="<?php echo $rows; ?>"
		spellcheck="<?php echo $spellcheck ? 'true' : 'false'; ?>"
		data-base3-rich-text-editor="base3ilias"
		data-base3-rich-text-editor-control
		<?php if ($name !== ''): ?>name="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>"<?php endif; ?>
		<?php if ($placeholder !== ''): ?>placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES); ?>"<?php endif; ?>
		<?php if ($ariaLabel !== ''): ?>aria-label="<?php echo htmlspecialchars($ariaLabel, ENT_QUOTES); ?>"<?php endif; ?>
		<?php if ($readonly): ?>readonly<?php endif; ?>
		<?php if ($disabled): ?>disabled<?php endif; ?>
	><?php echo htmlspecialchars($value, ENT_QUOTES); ?></textarea>
</div>
<script>
	(() => {
		const editorId = <?php echo json_encode($id, $jsonFlags); ?>;
		const tinyMceScriptUrl = <?php echo json_encode($tinyMceScriptUrl, $jsonFlags); ?>;
		const placeholder = <?php echo json_encode($placeholder, $jsonFlags); ?>;
		const ariaLabel = <?php echo json_encode($ariaLabel, $jsonFlags); ?>;
		const minimumHeight = <?php echo $minimumHeight; ?>;
		const spellcheck = <?php echo $spellcheck ? 'true' : 'false'; ?>;
		const readOnly = <?php echo ($readonly || $disabled) ? 'true' : 'false'; ?>;
		const textarea = document.getElementById(editorId);

		if (!textarea || textarea.base3RichTextEditor) {
			return;
		}

		let editor = null;
		let initializationPromise = null;
		let visibilityObserver = null;
		let destroyed = false;
		let initialized = false;
		let currentValue = String(textarea.value || '');

		function updateTextarea(value, notify = false) {
			currentValue = value === null || value === undefined ? '' : String(value);
			textarea.value = currentValue;

			if (notify) {
				textarea.dispatchEvent(new Event('input', { bubbles: true }));
			}
		}

		function isVisible() {
			return textarea.isConnected && textarea.getClientRects().length > 0;
		}

		function stopVisibilityObserver() {
			if (!visibilityObserver) {
				return;
			}

			visibilityObserver.disconnect();
			visibilityObserver = null;
		}

		function getTinyMce() {
			if (window.tinymce) {
				return Promise.resolve(window.tinymce);
			}

			if (window.base3IliasTinyMceLoader) {
				return window.base3IliasTinyMceLoader;
			}

			window.base3IliasTinyMceLoader = new Promise((resolve, reject) => {
				const scriptUrl = new URL(tinyMceScriptUrl, document.baseURI).href;
				let script = Array.from(document.scripts).find((candidate) => {
					return candidate.src === scriptUrl || candidate.src.includes('/node_modules/tinymce/tinymce');
				});

				const resolveTinyMce = () => {
					if (window.tinymce) {
						resolve(window.tinymce);
						return;
					}

					reject(new Error('ILIAS TinyMCE did not expose window.tinymce.'));
				};

				if (script) {
					if (window.tinymce) {
						resolveTinyMce();
						return;
					}

					script.addEventListener('load', resolveTinyMce, { once: true });
					script.addEventListener('error', () => reject(new Error('Unable to load ILIAS TinyMCE.')), { once: true });
					return;
				}

				script = document.createElement('script');
				script.src = scriptUrl;
				script.async = true;
				script.dataset.base3IliasTinyMceLoader = 'true';
				script.addEventListener('load', resolveTinyMce, { once: true });
				script.addEventListener('error', () => reject(new Error('Unable to load ILIAS TinyMCE.')), { once: true });
				document.head.appendChild(script);
			});

			return window.base3IliasTinyMceLoader;
		}

		function removeEditor() {
			initialized = false;

			if (!editor) {
				return;
			}

			const activeEditor = editor;
			editor = null;
			activeEditor.remove();
		}

		async function initializeEditor() {
			if (destroyed || editor || initializationPromise || !isVisible()) {
				return;
			}

			stopVisibilityObserver();
			updateTextarea(currentValue);

			initializationPromise = getTinyMce()
				.then(async (tinymce) => {
					const existingEditor = tinymce.get(editorId);
					if (existingEditor) {
						existingEditor.remove();
					}

					const editors = await tinymce.init({
						target: textarea,
						license_key: 'gpl',
						branding: false,
						promotion: false,
						menubar: false,
						statusbar: true,
						toolbar_mode: 'wrap',
						toolbar_persist: true,
						plugins: [
							'anchor',
							'autolink',
							'charmap',
							'code',
							'directionality',
							'fullscreen',
							'image',
							'insertdatetime',
							'link',
							'lists',
							'media',
							'nonbreaking',
							'preview',
							'searchreplace',
							'table',
							'visualblocks',
							'wordcount'
						].join(' '),
						toolbar: [
							'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | removeformat',
							'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | charmap insertdatetime nonbreaking | searchreplace visualblocks code fullscreen preview'
						].join(' '),
						browser_spellcheck: spellcheck,
						contextmenu: 'link image table',
						convert_urls: false,
						valid_elements: '*[*]',
						image_advtab: true,
						image_caption: true,
						min_height: minimumHeight,
						height: minimumHeight,
						resize: 'vertical',
						placeholder,
						readonly: readOnly,
						content_style: 'html { overflow: initial; } body { overflow-wrap: anywhere; } img { max-width: 100%; height: auto; }',
						setup(createdEditor) {
							createdEditor.on('init', () => {
								if (ariaLabel !== '') {
									createdEditor.getBody().setAttribute('aria-label', ariaLabel);
								}

								createdEditor.setContent(currentValue);
								initialized = true;
								updateTextarea(createdEditor.getContent());
							});

							createdEditor.on('change input undo redo', () => {
								if (!initialized) {
									return;
								}

								updateTextarea(createdEditor.getContent(), true);
							});

							createdEditor.on('blur', () => {
								if (!initialized) {
									return;
								}

								updateTextarea(createdEditor.getContent(), true);
							});

							createdEditor.on('keydown', (event) => {
								const isSubmitShortcut = (event.ctrlKey || event.metaKey) && event.key === 'Enter';
								if (!isSubmitShortcut && event.key !== 'Escape') {
									return;
								}

								const forwardedEvent = new KeyboardEvent('keydown', {
									key: event.key,
									code: event.code,
									ctrlKey: event.ctrlKey,
									metaKey: event.metaKey,
									shiftKey: event.shiftKey,
									altKey: event.altKey,
									bubbles: true,
									cancelable: true
								});

								if (!textarea.dispatchEvent(forwardedEvent)) {
									event.preventDefault();
								}
							});
						}
					});

					const createdEditor = Array.isArray(editors) ? editors[0] : null;
					if (!createdEditor) {
						throw new Error('ILIAS TinyMCE did not create an editor instance.');
					}

					if (destroyed || !textarea.isConnected) {
						createdEditor.remove();
						return;
					}

					editor = createdEditor;
					if (editor.getContent() !== currentValue) {
						editor.setContent(currentValue);
					}
					updateTextarea(editor.getContent());
				})
				.catch((error) => {
					textarea.hidden = false;
					textarea.style.display = '';
					console.error('Unable to initialize Base3Ilias rich text editor.', error);
				})
				.finally(() => {
					initializationPromise = null;
				});

			await initializationPromise;
		}

		function scheduleInitialization() {
			if (destroyed || editor || initializationPromise) {
				return;
			}

			if (isVisible()) {
				void initializeEditor();
				return;
			}

			if (visibilityObserver) {
				return;
			}

			visibilityObserver = new MutationObserver(() => {
				if (!isVisible()) {
					return;
				}

				stopVisibilityObserver();
				void initializeEditor();
			});

			visibilityObserver.observe(document.documentElement, {
				subtree: true,
				childList: true,
				attributes: true,
				attributeFilter: ['hidden', 'style', 'class', 'aria-hidden']
			});
		}

		const adapter = {
			getValue() {
				if (editor) {
					updateTextarea(editor.getContent());
				}

				return currentValue;
			},
			setValue(value) {
				if (editor && !isVisible()) {
					removeEditor();
				}

				updateTextarea(value);

				if (editor) {
					if (editor.getContent() !== currentValue) {
						editor.setContent(currentValue);
					}
					return;
				}

				scheduleInitialization();
			},
			focus() {
				if (editor) {
					editor.focus();
					return;
				}

				scheduleInitialization();
				textarea.focus();
			},
			async destroy() {
				destroyed = true;
				stopVisibilityObserver();
				textarea.base3RichTextEditor = null;
				removeEditor();
			}
		};

		textarea.base3RichTextEditor = adapter;
	})();
</script>
