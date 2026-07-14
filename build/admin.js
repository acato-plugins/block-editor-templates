/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/admin/assets/src/abet-filters.js"
/*!**********************************************!*\
  !*** ./src/admin/assets/src/abet-filters.js ***!
  \**********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/compose */ "@wordpress/compose");
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_compose__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/hooks */ "@wordpress/hooks");
/* harmony import */ var _wordpress_hooks__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_hooks__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/settings.mjs");
/* harmony import */ var _abet_filters_scss__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./abet-filters.scss */ "./src/admin/assets/src/abet-filters.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);
/**
 * Import WordPress dependencies.
 */









/**
 * Import styles (extracted to build/admin.css by wp-scripts).
 */


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
 * Collect the `{attr}Placeholder` attribute keys of a block's attribute schema,
 * excluding the control attribute itself.
 *
 * @param {Object} schemaAttributes - The block type attribute definitions.
 * @return {string[]} The placeholder attribute keys.
 */
const getPlaceholderKeys = schemaAttributes => Object.keys(schemaAttributes || {}).filter(key => key.endsWith(PLACEHOLDER_SUFFIX) && key !== CONTROL_ATTRIBUTE);

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
const suggestBaseAttributes = (schemaAttributes, placeholderKey, placeholderType) => Object.keys(schemaAttributes).filter(key => {
  if (key === placeholderKey || key === CONTROL_ATTRIBUTE || key.endsWith(PLACEHOLDER_SUFFIX)) {
    return false;
  }
  const attribute = schemaAttributes[key];
  return attribute?.type === placeholderType && CONTENT_SOURCES.includes(attribute?.source);
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
const analysePlaceholders = schemaAttributes => getPlaceholderKeys(schemaAttributes).map(placeholderKey => {
  const baseKey = placeholderKey.slice(0, -PLACEHOLDER_SUFFIX.length);
  const placeholderType = schemaAttributes[placeholderKey]?.type;
  if (!(baseKey in schemaAttributes)) {
    return {
      placeholderKey,
      baseKey,
      status: 'missing',
      placeholderType,
      suggestions: suggestBaseAttributes(schemaAttributes, placeholderKey, placeholderType)
    };
  }
  const baseType = schemaAttributes[baseKey]?.type;
  if (baseType !== placeholderType) {
    return {
      placeholderKey,
      baseKey,
      status: 'mismatch',
      placeholderType,
      baseType
    };
  }
  return {
    placeholderKey,
    baseKey,
    status: 'ok',
    placeholderType,
    baseType
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
const addControlAttribute = settings => {
  if (getPlaceholderKeys(settings.attributes).length) {
    settings.attributes = {
      ...settings.attributes,
      [CONTROL_ATTRIBUTE]: {
        type: 'boolean',
        default: false
      }
    };
  }
  return settings;
};

/**
 * Add the "Placeholder options" inspector panel with validation feedback.
 */
const withPlaceholderControls = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_2__.createHigherOrderComponent)(BlockEdit => props => {
  const {
    name,
    clientId,
    attributes,
    setAttributes
  } = props;
  const {
    schemaAttributes,
    ancestorEnabled
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    const blockType = select('core/blocks').getBlockType(name);
    const {
      getBlockParents,
      getBlockAttributes
    } = select('core/block-editor');
    return {
      schemaAttributes: blockType?.attributes,
      ancestorEnabled: getBlockParents(clientId).some(parentClientId => !!getBlockAttributes(parentClientId)?.[CONTROL_ATTRIBUTE])
    };
  }, [name, clientId]);
  const placeholders = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useMemo)(() => analysePlaceholders(schemaAttributes), [schemaAttributes]);

  // Block has no placeholder attributes at all: leave it untouched.
  if (!placeholders.length) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(BlockEdit, {
      ...props
    });
  }
  const validPairs = placeholders.filter(placeholder => placeholder.status === 'ok');
  const isOwnEnabled = !!attributes[CONTROL_ATTRIBUTE];

  // Toggle the flag and move each pair's value between the base attribute and its
  // `{base}Placeholder` counterpart, so enabling stores the typed content as placeholder
  // text (and clears the real content) while disabling restores it. The move is done here,
  // in the editor, because content sourced from block markup (e.g. a heading's or
  // paragraph's text) never reaches PHP as a plain attribute.
  const onToggle = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useCallback)(() => {
    const enabling = !isOwnEnabled;
    const updates = {
      [CONTROL_ATTRIBUTE]: enabling
    };
    validPairs.forEach(({
      baseKey,
      placeholderKey
    }) => {
      const fromKey = enabling ? baseKey : placeholderKey;
      const toKey = enabling ? placeholderKey : baseKey;
      const value = attributes[fromKey];
      if (value !== undefined && value !== null && value !== '') {
        updates[toKey] = value;
        updates[fromKey] = '';
      }
    });
    setAttributes(updates);
  }, [isOwnEnabled, validPairs, attributes, setAttributes]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(BlockEdit, {
      ...props
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('Placeholder options', 'block-editor-templates'),
        icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_7__["default"],
        initialOpen: true,
        children: [placeholders.map(placeholder => {
          if (placeholder.status === 'missing') {
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
              status: "warning",
              isDismissible: false,
              children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.sprintf)(/* translators: 1: placeholder attribute name, 2: expected base attribute name. */
              (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('“%1$s” is set, but its matching attribute “%2$s” does not exist on this block, so it cannot be used as a placeholder.', 'block-editor-templates'), placeholder.placeholderKey, placeholder.baseKey), placeholder.suggestions.length === 1 && (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.sprintf)(/* translators: 1: suggested placeholder attribute name (e.g. "contentPlaceholder"), 2: existing base attribute name (e.g. "content"). */
              (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('Did you mean “%1$s”? This block has a matching “%2$s” attribute.', 'block-editor-templates'), placeholder.suggestions[0] + PLACEHOLDER_SUFFIX, placeholder.suggestions[0]), placeholder.suggestions.length > 1 && (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.sprintf)(/* translators: %s: comma-separated list of suggested placeholder attribute names. */
              (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('Did you perhaps mean one of: %s?', 'block-editor-templates'), placeholder.suggestions.map(candidate => '“' + candidate + PLACEHOLDER_SUFFIX + '”').join(', '))]
            }, placeholder.placeholderKey);
          }
          if (placeholder.status === 'mismatch') {
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
              status: "error",
              isDismissible: false,
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.sprintf)(/* translators: 1: placeholder attribute name, 2: placeholder data-type, 3: base attribute name, 4: base data-type. */
              (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('Type mismatch: “%1$s” (%2$s) must be the same data-type as “%3$s” (%4$s).', 'block-editor-templates'), placeholder.placeholderKey, placeholder.placeholderType, placeholder.baseKey, placeholder.baseType)
            }, placeholder.placeholderKey);
          }
          return null;
        }), validPairs.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelRow, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.ToggleControl, {
            __nextHasNoMarginBottom: true,
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('Use content as placeholder', 'block-editor-templates'),
            help: ancestorEnabled ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('Inherited from a parent block: this block’s content is already used as placeholder text.', 'block-editor-templates') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('When on, the text you typed is moved into this block’s placeholder attribute and shown as a greyed-out hint. New posts created from this template start with that hint instead of real content. Turn it off to move the text back.', 'block-editor-templates'),
            checked: ancestorEnabled || isOwnEnabled,
            disabled: ancestorEnabled,
            onChange: onToggle
          })
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
          status: "info",
          isDismissible: false,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('This block has no valid placeholder attributes to toggle.', 'block-editor-templates')
        })]
      })
    })]
  });
}, 'withPlaceholderControls');

/**
 * Add marker class names to the block wrapper in the editor canvas:
 *  - `abet-placeholder--on`        : this block's own toggle is enabled (badge).
 *  - `abet-placeholder--inherited` : an ancestor's toggle is enabled (group).
 *
 * Only the class name is changed (never the wrapper element), so the block is
 * not remounted and selection is preserved.
 */
const withPlaceholderBadge = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_2__.createHigherOrderComponent)(BlockListBlock => props => {
  const {
    clientId,
    attributes
  } = props;
  const ancestorEnabled = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    const {
      getBlockParents,
      getBlockAttributes
    } = select('core/block-editor');
    return getBlockParents(clientId).some(parentClientId => !!getBlockAttributes(parentClientId)?.[CONTROL_ATTRIBUTE]);
  }, [clientId]);
  const isOwnEnabled = !!attributes?.[CONTROL_ATTRIBUTE];
  if (!isOwnEnabled && !ancestorEnabled) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(BlockListBlock, {
      ...props
    });
  }
  const className = [props.className, isOwnEnabled ? 'abet-placeholder--on' : '', ancestorEnabled ? 'abet-placeholder--inherited' : ''].filter(Boolean).join(' ');

  // Feed the (translated) badge label to the stylesheet as a custom
  // property, so the on-canvas pill text stays translatable.
  const wrapperProps = isOwnEnabled ? {
    ...props.wrapperProps,
    style: {
      ...props.wrapperProps?.style,
      '--abet-placeholder-label': JSON.stringify((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_6__.__)('Used as placeholder', 'block-editor-templates'))
    }
  } : props.wrapperProps;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(BlockListBlock, {
    ...props,
    className: className,
    wrapperProps: wrapperProps
  });
}, 'withPlaceholderBadge');

// Fire hooks!
(0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_5__.addFilter)('blocks.registerBlockType', 'abet/placeholder-attribute', addControlAttribute);
(0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_5__.addFilter)('editor.BlockEdit', 'abet/inspector-control', withPlaceholderControls);
(0,_wordpress_hooks__WEBPACK_IMPORTED_MODULE_5__.addFilter)('editor.BlockListBlock', 'abet/placeholder-badge', withPlaceholderBadge);

/***/ },

/***/ "./src/admin/assets/src/default-content-panel.js"
/*!*******************************************************!*\
  !*** ./src/admin/assets/src/default-content-panel.js ***!
  \*******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/editor */ "@wordpress/editor");
/* harmony import */ var _wordpress_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/plugins */ "@wordpress/plugins");
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_plugins__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * Import WordPress dependencies.
 */







const META_KEY = '_abet_use_default_content';

/**
 * Document setting panel to toggle whether a Post Type Template prefills new posts.
 *
 * @return {Element|null} The settings panel, or null when not editing a Post Type Template.
 */
const AbetDefaultContentPanel = () => {
  const {
    postType,
    meta
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => {
    const editor = select(_wordpress_editor__WEBPACK_IMPORTED_MODULE_2__.store);
    return {
      postType: editor.getCurrentPostType(),
      meta: editor.getEditedPostAttribute('meta') || {}
    };
  }, []);
  const {
    editPost
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_wordpress_editor__WEBPACK_IMPORTED_MODULE_2__.store);
  const onChange = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useCallback)(value => editPost({
    meta: {
      ...meta,
      [META_KEY]: value
    }
  }), [editPost, meta]);
  if (postType !== 'block-templates') {
    return null;
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_editor__WEBPACK_IMPORTED_MODULE_2__.PluginDocumentSettingPanel, {
    name: "abet-default-content",
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Post Type Template', 'block-editor-templates'),
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.ToggleControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Prefill new posts with this template', 'block-editor-templates'),
      help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('When enabled, new posts of this post type start with this template’s blocks and the content entered in them. When disabled, new posts get only the empty block structure. Only applies when creating a post in the WordPress admin.', 'block-editor-templates'),
      checked: !!meta[META_KEY],
      onChange: onChange
    })
  });
};
(0,_wordpress_plugins__WEBPACK_IMPORTED_MODULE_3__.registerPlugin)('abet-default-content', {
  render: AbetDefaultContentPanel
});

/***/ },

/***/ "./src/admin/assets/src/abet-filters.scss"
/*!************************************************!*\
  !*** ./src/admin/assets/src/abet-filters.scss ***!
  \************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/block-editor"
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
(module) {

module.exports = window["wp"]["blockEditor"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/compose"
/*!*********************************!*\
  !*** external ["wp","compose"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["compose"];

/***/ },

/***/ "@wordpress/data"
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["data"];

/***/ },

/***/ "@wordpress/editor"
/*!********************************!*\
  !*** external ["wp","editor"] ***!
  \********************************/
(module) {

module.exports = window["wp"]["editor"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/hooks"
/*!*******************************!*\
  !*** external ["wp","hooks"] ***!
  \*******************************/
(module) {

module.exports = window["wp"]["hooks"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "@wordpress/plugins"
/*!*********************************!*\
  !*** external ["wp","plugins"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["plugins"];

/***/ },

/***/ "@wordpress/primitives"
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["primitives"];

/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/settings.mjs"
/*!*************************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/settings.mjs ***!
  \*************************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ settings_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/settings.tsx


var settings_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", fill: "currentColor", children: [
  /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "m19 7.5h-7.628c-.3089-.87389-1.1423-1.5-2.122-1.5-.97966 0-1.81309.62611-2.12197 1.5h-2.12803v1.5h2.12803c.30888.87389 1.14231 1.5 2.12197 1.5.9797 0 1.8131-.62611 2.122-1.5h7.628z" }),
  /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "m19 15h-2.128c-.3089-.8739-1.1423-1.5-2.122-1.5s-1.8131.6261-2.122 1.5h-7.628v1.5h7.628c.3089.8739 1.1423 1.5 2.122 1.5s1.8131-.6261 2.122-1.5h2.128z" })
] });

//# sourceMappingURL=settings.mjs.map


/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	const __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		const cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		const module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			const e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			const getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter/value functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			if(Array.isArray(definition)) {
/******/ 				var i = 0;
/******/ 				while(i < definition.length) {
/******/ 					var key = definition[i++];
/******/ 					var binding = definition[i++];
/******/ 					if(!__webpack_require__.o(exports, key)) {
/******/ 						if(binding === 0) {
/******/ 							Object.defineProperty(exports, key, { enumerable: true, value: definition[i++] });
/******/ 						} else {
/******/ 							Object.defineProperty(exports, key, { enumerable: true, get: binding });
/******/ 						}
/******/ 					} else if(binding === 0) { i++; }
/******/ 				}
/******/ 			} else {
/******/ 				for(var key in definition) {
/******/ 					if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 						Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 					}
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.hasOwn(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
let __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!***********************************!*\
  !*** ./src/admin/assets/admin.js ***!
  \***********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _src_abet_filters_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./src/abet-filters.js */ "./src/admin/assets/src/abet-filters.js");
/* harmony import */ var _src_default_content_panel_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./src/default-content-panel.js */ "./src/admin/assets/src/default-content-panel.js");


})();

/******/ })()
;
//# sourceMappingURL=admin.js.map