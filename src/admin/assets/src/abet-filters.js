/**
 * Import WordPress dependencies.
 */
import { InspectorControls } from '@wordpress/block-editor';
import {
	ToggleControl,
	PanelBody,
	PanelRow,
	Notice,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { Fragment, useCallback, useEffect, useMemo } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { settings as settingsIcon } from '@wordpress/icons';

/**
 * Import styles (extracted to build/admin.css by wp-scripts).
 */
import './abet-filters.scss';

/**
 * The suffix that marks an attribute as the placeholder counterpart of another
 * attribute, e.g. `text` <-> `textPlaceholder`.
 *
 * @type {string}
 */
const PLACEHOLDER_SUFFIX = 'Placeholder';

/**
 * The block attribute that stores whether the block's content should be used as
 * a placeholder when the template is applied to a new post.
 *
 * NOTE: this attribute itself ends with the placeholder suffix, so it must be
 * excluded everywhere we scan for `{attr}Placeholder` pairs, otherwise it would
 * be analysed as a (broken) pair without a base attribute.
 *
 * @type {string}
 */
const CONTROL_ATTRIBUTE = 'textAsPlaceholder';

/**
 * Whether an attribute holds a non-empty value worth moving between a base
 * attribute and its placeholder counterpart.
 *
 * @param {*} value - The attribute value.
 * @return {boolean} True when the value is set and not empty.
 */
const hasValue = (value) =>
	value !== undefined && value !== null && value !== '';

/**
 * Collect the `{attr}Placeholder` attribute keys of a block's attribute schema,
 * excluding the control attribute itself.
 *
 * @param {Object} schemaAttributes - The block type attribute definitions.
 * @return {string[]} The placeholder attribute keys.
 */
const getPlaceholderKeys = (schemaAttributes) =>
	Object.keys(schemaAttributes || {}).filter(
		(key) => key.endsWith(PLACEHOLDER_SUFFIX) && key !== CONTROL_ATTRIBUTE
	);

/**
 * Attribute `source` values that hold editable, user-facing content. When a
 * `{attr}Placeholder` has no matching base attribute, an attribute with one of
 * these sources (and the same data-type) is a likely intended target.
 *
 * @type {string[]}
 */
const CONTENT_SOURCES = ['html', 'rich-text', 'text'];

/**
 * Find content-bearing attributes of the same data-type that the placeholder
 * could plausibly have been meant for, so we can suggest a correct name.
 *
 * @param {Object} schemaAttributes - The block type attribute definitions.
 * @param {string} placeholderKey   - The `{attr}Placeholder` attribute key.
 * @param {string} placeholderType  - The placeholder attribute's data-type.
 * @return {string[]} Candidate base attribute names (e.g. `['content']`).
 */
const suggestBaseAttributes = (
	schemaAttributes,
	placeholderKey,
	placeholderType
) =>
	Object.keys(schemaAttributes).filter((key) => {
		if (
			key === placeholderKey ||
			key === CONTROL_ATTRIBUTE ||
			key.endsWith(PLACEHOLDER_SUFFIX)
		) {
			return false;
		}

		const attribute = schemaAttributes[key];
		return (
			attribute?.type === placeholderType &&
			CONTENT_SOURCES.includes(attribute?.source)
		);
	});

/**
 * Analyse every `{attr}Placeholder` attribute of a block and pair it with its
 * base `{attr}` attribute.
 *
 * Each result has a `status`:
 *  - `ok`       : base attribute exists and has the same data-type   -> toggle.
 *  - `missing`  : base attribute does not exist                       -> warning.
 *  - `mismatch` : base attribute exists but has a different data-type -> error.
 *
 * @param {Object} schemaAttributes - The block type attribute definitions.
 * @return {Array<{placeholderKey:string, baseKey:string, status:string, placeholderType:string, baseType:(string|undefined)}>} The analysed placeholder/base attribute pairs.
 */
const analysePlaceholders = (schemaAttributes) =>
	getPlaceholderKeys(schemaAttributes).map((placeholderKey) => {
		const baseKey = placeholderKey.slice(0, -PLACEHOLDER_SUFFIX.length);
		const placeholderType = schemaAttributes[placeholderKey]?.type;

		if (!(baseKey in schemaAttributes)) {
			return {
				placeholderKey,
				baseKey,
				status: 'missing',
				placeholderType,
				suggestions: suggestBaseAttributes(
					schemaAttributes,
					placeholderKey,
					placeholderType
				),
			};
		}

		const baseType = schemaAttributes[baseKey]?.type;
		if (baseType !== placeholderType) {
			return {
				placeholderKey,
				baseKey,
				status: 'mismatch',
				placeholderType,
				baseType,
			};
		}

		return {
			placeholderKey,
			baseKey,
			status: 'ok',
			placeholderType,
			baseType,
		};
	});

/**
 * Register the `textAsPlaceholder` control attribute on every block that exposes
 * at least one `{attr}Placeholder` attribute.
 *
 * Registering it (instead of relying on Gutenberg to store an unknown
 * attribute) keeps the block's attribute schema stable, which prevents the
 * block from being re-synced when the toggle is flipped — the root cause of the
 * inspector/sidebar closing on toggle.
 *
 * @param {Object} settings - The block settings.
 * @return {Object} The (possibly) extended block settings.
 */
const addControlAttribute = (settings) => {
	if (getPlaceholderKeys(settings.attributes).length) {
		settings.attributes = {
			...settings.attributes,
			[CONTROL_ATTRIBUTE]: {
				type: 'boolean',
				default: false,
			},
		};
	}

	return settings;
};

/**
 * Add the "Placeholder options" inspector panel with validation feedback.
 */
const withPlaceholderControls = createHigherOrderComponent(
	(BlockEdit) => (props) => {
		const { name, clientId, attributes, setAttributes, isSelected } = props;

		const { schemaAttributes, ancestorEnabled } = useSelect(
			(select) => {
				const blockType = select('core/blocks').getBlockType(name);
				const { getBlockParents, getBlockAttributes } =
					select('core/block-editor');

				return {
					schemaAttributes: blockType?.attributes,
					ancestorEnabled: getBlockParents(clientId).some(
						(parentClientId) =>
							!!getBlockAttributes(parentClientId)?.[
								CONTROL_ATTRIBUTE
							]
					),
				};
			},
			[name, clientId]
		);

		const placeholders = useMemo(
			() => analysePlaceholders(schemaAttributes),
			[schemaAttributes]
		);

		// Block has no placeholder attributes at all: leave it untouched.
		if (!placeholders.length) {
			return <BlockEdit {...props} />;
		}

		const validPairs = useMemo(
			() =>
				placeholders.filter(
					(placeholder) => placeholder.status === 'ok'
				),
			[placeholders]
		);
		const isOwnEnabled = !!attributes[CONTROL_ATTRIBUTE];

		// Toggle the flag and move each pair's value between the base attribute and its
		// `{base}Placeholder` counterpart, so enabling stores the typed content as placeholder
		// text (and clears the real content) while disabling restores it. The move is done here,
		// in the editor, because content sourced from block markup (e.g. a heading's or
		// paragraph's text) never reaches PHP as a plain attribute.
		const onToggle = useCallback(() => {
			const enabling = !isOwnEnabled;
			const updates = { [CONTROL_ATTRIBUTE]: enabling };

			validPairs.forEach(({ baseKey, placeholderKey }) => {
				const fromKey = enabling ? baseKey : placeholderKey;
				const toKey = enabling ? placeholderKey : baseKey;

				if (hasValue(attributes[fromKey])) {
					updates[toKey] = attributes[fromKey];
					updates[fromKey] = '';
				}
			});

			setAttributes(updates);
		}, [isOwnEnabled, validPairs, attributes, setAttributes]);

		// Keep the pairs in step while the toggle is on, in two steps, so text typed *after* the toggle
		// was switched on also ends up as placeholder text:
		//
		//  1. While the block is selected the base attribute is only *copied* into its placeholder
		//     counterpart, never cleared. Clearing it on every keystroke would fight the rich-text
		//     field the author is typing in — it keeps its own in-progress value, so the base attribute
		//     would run ahead while the placeholder kept the single character that happened to trigger
		//     the first move.
		//  2. Once the block is no longer selected the base attribute is cleared, so the text turns
		//     into the greyed-out hint and the template is stored the way it is documented: content in
		//     `{base}Placeholder`, an empty base attribute.
		//
		// Because step 2 also runs on load, this doubles as the normalisation of templates authored
		// before the toggle moved content at all (their text still sits in the base attribute), and it
		// repairs templates where the two values drifted apart.
		useEffect(() => {
			if (!isOwnEnabled) {
				return;
			}

			const updates = {};
			validPairs.forEach(({ baseKey, placeholderKey }) => {
				if (!hasValue(attributes[baseKey])) {
					return;
				}

				if (attributes[placeholderKey] !== attributes[baseKey]) {
					updates[placeholderKey] = attributes[baseKey];
				}

				if (!isSelected) {
					updates[baseKey] = '';
				}
			});

			if (Object.keys(updates).length) {
				setAttributes(updates);
			}
		}, [isOwnEnabled, isSelected, validPairs, attributes, setAttributes]);

		return (
			<Fragment>
				<BlockEdit {...props} />
				<InspectorControls>
					<PanelBody
						title={__(
							'Placeholder options',
							'block-editor-templates'
						)}
						icon={settingsIcon}
						initialOpen={true}
					>
						{placeholders.map((placeholder) => {
							if (placeholder.status === 'missing') {
								return (
									<Notice
										key={placeholder.placeholderKey}
										status="warning"
										isDismissible={false}
									>
										{sprintf(
											/* translators: 1: placeholder attribute name, 2: expected base attribute name. */
											__(
												'“%1$s” is set, but its matching attribute “%2$s” does not exist on this block, so it cannot be used as a placeholder.',
												'block-editor-templates'
											),
											placeholder.placeholderKey,
											placeholder.baseKey
										)}
										{placeholder.suggestions.length === 1 &&
											sprintf(
												/* translators: 1: suggested placeholder attribute name (e.g. "contentPlaceholder"), 2: existing base attribute name (e.g. "content"). */
												__(
													'Did you mean “%1$s”? This block has a matching “%2$s” attribute.',
													'block-editor-templates'
												),
												placeholder.suggestions[0] +
													PLACEHOLDER_SUFFIX,
												placeholder.suggestions[0]
											)}
										{placeholder.suggestions.length > 1 &&
											sprintf(
												/* translators: %s: comma-separated list of suggested placeholder attribute names. */
												__(
													'Did you perhaps mean one of: %s?',
													'block-editor-templates'
												),
												placeholder.suggestions
													.map(
														(candidate) =>
															'“' +
															candidate +
															PLACEHOLDER_SUFFIX +
															'”'
													)
													.join(', ')
											)}
									</Notice>
								);
							}

							if (placeholder.status === 'mismatch') {
								return (
									<Notice
										key={placeholder.placeholderKey}
										status="error"
										isDismissible={false}
									>
										{sprintf(
											/* translators: 1: placeholder attribute name, 2: placeholder data-type, 3: base attribute name, 4: base data-type. */
											__(
												'Type mismatch: “%1$s” (%2$s) must be the same data-type as “%3$s” (%4$s).',
												'block-editor-templates'
											),
											placeholder.placeholderKey,
											placeholder.placeholderType,
											placeholder.baseKey,
											placeholder.baseType
										)}
									</Notice>
								);
							}

							return null;
						})}

						{validPairs.length > 0 ? (
							<PanelRow>
								<ToggleControl
									__nextHasNoMarginBottom
									label={__(
										'Use content as placeholder',
										'block-editor-templates'
									)}
									help={
										ancestorEnabled
											? __(
													'Inherited from a parent block: this block’s content is already used as placeholder text.',
													'block-editor-templates'
												)
											: __(
													'When on, the text you typed is moved into this block’s placeholder attribute and shown as a greyed-out hint. New posts created from this template start with that hint instead of real content. Turn it off to move the text back.',
													'block-editor-templates'
												)
									}
									checked={ancestorEnabled || isOwnEnabled}
									disabled={ancestorEnabled}
									onChange={onToggle}
								/>
							</PanelRow>
						) : (
							<Notice status="info" isDismissible={false}>
								{__(
									'This block has no valid placeholder attributes to toggle.',
									'block-editor-templates'
								)}
							</Notice>
						)}
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	},
	'withPlaceholderControls'
);

/**
 * Add marker class names to the block wrapper in the editor canvas:
 *  - `abet-placeholder--on`        : this block's own toggle is enabled (badge).
 *  - `abet-placeholder--inherited` : an ancestor's toggle is enabled (group).
 *
 * Only the class name is changed (never the wrapper element), so the block is
 * not remounted and selection is preserved.
 */
const withPlaceholderBadge = createHigherOrderComponent(
	(BlockListBlock) => (props) => {
		const { clientId, attributes } = props;

		const ancestorEnabled = useSelect(
			(select) => {
				const { getBlockParents, getBlockAttributes } =
					select('core/block-editor');

				return getBlockParents(clientId).some(
					(parentClientId) =>
						!!getBlockAttributes(parentClientId)?.[
							CONTROL_ATTRIBUTE
						]
				);
			},
			[clientId]
		);

		const isOwnEnabled = !!attributes?.[CONTROL_ATTRIBUTE];

		if (!isOwnEnabled && !ancestorEnabled) {
			return <BlockListBlock {...props} />;
		}

		const className = [
			props.className,
			isOwnEnabled ? 'abet-placeholder--on' : '',
			ancestorEnabled ? 'abet-placeholder--inherited' : '',
		]
			.filter(Boolean)
			.join(' ');

		// Feed the (translated) badge label to the stylesheet as a custom
		// property, so the on-canvas pill text stays translatable.
		const wrapperProps = isOwnEnabled
			? {
					...props.wrapperProps,
					style: {
						...props.wrapperProps?.style,
						'--abet-placeholder-label': JSON.stringify(
							__('Used as placeholder', 'block-editor-templates')
						),
					},
				}
			: props.wrapperProps;

		return (
			<BlockListBlock
				{...props}
				className={className}
				wrapperProps={wrapperProps}
			/>
		);
	},
	'withPlaceholderBadge'
);

// Fire hooks!
addFilter(
	'blocks.registerBlockType',
	'abet/placeholder-attribute',
	addControlAttribute
);

addFilter(
	'editor.BlockEdit',
	'abet/inspector-control',
	withPlaceholderControls
);

addFilter(
	'editor.BlockListBlock',
	'abet/placeholder-badge',
	withPlaceholderBadge
);
