/**
 * The "Template for" panel in the editor of every template post type.
 *
 * A template made with Add New is not for anything until its author chooses a post type, taxonomy
 * or special page here. Once that is saved, and for the templates the plugin creates itself, the
 * panel only says what the template is for: the server refuses to change it (see
 * Admin::register_template_meta).
 */

/**
 * Import WordPress dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

/**
 * The panel.
 *
 * @return {Element|null} The panel, or null without its settings.
 */
const TemplateForPanel = () => {
	const { metaKey, choices } = window.abetTemplateFor ?? {};

	const { saved, edited } = useSelect(
		(select) => {
			const editor = select(editorStore);
			return {
				saved: editor.getCurrentPostAttribute('meta')?.[metaKey] ?? '',
				edited: editor.getEditedPostAttribute('meta')?.[metaKey] ?? '',
			};
		},
		[metaKey]
	);
	const { editPost } = useDispatch(editorStore);

	const options = useMemo(
		() => [
			{
				value: '',
				label: __('— Choose —', 'block-editor-templates'),
			},
			...(choices ?? []).map(({ value, label, taken }) => ({
				value,
				label: taken
					? sprintf(
							/* translators: %s: post type, taxonomy or special page name. */
							__(
								'%s (already has a template)',
								'block-editor-templates'
							),
							label
						)
					: label,
			})),
		],
		[choices]
	);

	const onChange = useCallback(
		(value) => editPost({ meta: { [metaKey]: value } }),
		[editPost, metaKey]
	);

	if (!metaKey) {
		return null;
	}

	const current = (choices ?? []).find(({ value }) => value === saved);

	return (
		<PluginDocumentSettingPanel
			name="abet-template-for"
			title={__('Template for', 'block-editor-templates')}
		>
			{saved ? (
				<p>
					{sprintf(
						/* translators: %s: post type, taxonomy or special page name. */
						__(
							'This template is for: %s',
							'block-editor-templates'
						),
						current?.label ?? saved
					)}
				</p>
			) : (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={__('Template for', 'block-editor-templates')}
					hideLabelFromVision
					help={__(
						'Choose what this template is for. It is not used until you do, and it cannot be changed after saving.',
						'block-editor-templates'
					)}
					value={edited}
					options={options}
					onChange={onChange}
				/>
			)}
		</PluginDocumentSettingPanel>
	);
};

registerPlugin('abet-template-for', { render: TemplateForPanel });
