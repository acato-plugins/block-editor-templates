/**
 * Import WordPress dependencies.
 */
import {ToggleControl} from '@wordpress/components';
import {useSelect, useDispatch} from '@wordpress/data';
import {PluginDocumentSettingPanel, store as editorStore} from '@wordpress/editor';
import {registerPlugin} from '@wordpress/plugins';
import {useCallback} from '@wordpress/element';
import {__} from '@wordpress/i18n';

const META_KEY = '_abet_use_default_content';

/**
 * Document setting panel to toggle whether a Post Type Template prefills new posts.
 *
 * @return {JSX.Element|null}
 */
const AbetDefaultContentPanel = () => {
  const {postType, meta} = useSelect((select) => {
    const editor = select(editorStore);
    return {
      postType: editor.getCurrentPostType(),
      meta: editor.getEditedPostAttribute('meta') || {},
    };
  }, []);
  const {editPost} = useDispatch(editorStore);

  const onChange = useCallback(
    (value) => editPost({meta: {...meta, [META_KEY]: value}}),
    [editPost, meta]
  );

  if (postType !== 'block-templates') {
    return null;
  }

  return (
    <PluginDocumentSettingPanel
      name="abet-default-content"
      title={__('Post Type Template', 'block-editor-templates')}
    >
      <ToggleControl
        label={__('Prefill new posts with this template', 'block-editor-templates')}
        help={__(
          'Copy this template into new posts (keeping heading and paragraph text). Only applies when creating a post in the WordPress admin.',
          'block-editor-templates'
        )}
        checked={!!meta[META_KEY]}
        onChange={onChange}
      />
    </PluginDocumentSettingPanel>
  );
};

registerPlugin('abet-default-content', {render: AbetDefaultContentPanel});
