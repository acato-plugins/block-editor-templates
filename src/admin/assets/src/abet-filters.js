/**
 * Import WordPress dependencies.
 */
import {InspectorControls} from '@wordpress/block-editor';
import {ToggleControl, PanelBody, PanelRow} from '@wordpress/components';
import {createHigherOrderComponent} from '@wordpress/compose';
import {Fragment} from '@wordpress/element';
import {__} from '@wordpress/i18n';
import {settings as settingsIcon} from '@wordpress/icons';

/**
 * Check if element contains any placeholder property.
 *
 * @param {object} attributes - Object of all block attributes.
 * @return {boolean}
 */
const abetPlaceholderFound = (attributes) => {
  if (typeof attributes !== 'object' || !Object.keys(attributes).length) {
    return false;
  }

  return Object.keys(attributes).some(
    (key) => key.endsWith('Placeholder') && key !== 'textAsPlaceholder'
  );
};

/**
 * Extend attributes with placeholder Boolean.
 *
 * @param {object} settings - The block settings.
 * @param {string} blockname - The name of the block like 'core/button' as defined in the block.json file.
 * @return {object}
 */
const abetAddPlaceholderAttribute = (settings, blockname) => {
  if (blockname === 'block-editor-block-card' && abetPlaceholderFound(settings.attributes)) {
    settings.attributes = {
      ...settings.attributes,
      textAsPlaceholder: {
        type: 'boolean',
        default: false,
      },
    };
  }

  return settings;
};

const abetInspectorControls = createHigherOrderComponent(
  (BlockEdit) => (props) => {
    const { attributes, setAttributes, isSelected } = props;

    if (!abetPlaceholderFound(attributes)) {
      return <BlockEdit {...props} />;
    }

    return (
      <Fragment>
        <BlockEdit {...props} />
        {isSelected && (
          <InspectorControls key="placeholderSettings">
            <PanelBody title={__('Placeholder options', 'block-editor-templates')} icon={settingsIcon}>
              <PanelRow>
                <ToggleControl
                  label={__('Use text as placeholder', 'block-editor-templates')}
                  checked={!!attributes.textAsPlaceholder}
                  onChange={() => setAttributes({ textAsPlaceholder: !attributes.textAsPlaceholder })}
                />
              </PanelRow>
            </PanelBody>
          </InspectorControls>
        )}
      </Fragment>
    );
  },
  'abetInspectorControls'
);

// Fire hooks!
if (wp.hooks) {
  wp.hooks.addFilter(
    'blocks.registerBlockType',
    'abet/placeholder-attribute',
    abetAddPlaceholderAttribute
  );

  wp.hooks.addFilter(
    'editor.BlockEdit',
    'abet/inspector-control',
    abetInspectorControls
  );
}
