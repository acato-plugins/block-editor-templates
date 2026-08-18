# Making a block's content usable as placeholder text

Block Editor Templates can turn the content an author types into a block into
**placeholder text**: a greyed-out hint that seeds new posts created from a
template, instead of real content. In the template editor this is exposed as a
**"Use content as placeholder"** toggle inside a **"Placeholder options"** panel,
plus a *"Used as placeholder"* badge on the block in the canvas.

This document explains how a block developer opts a block into that feature.

## TL;DR

- Your block declares a `{base}Placeholder` attribute next to a content
  attribute (e.g. `content` → `contentPlaceholder`), of the **same `type`**.
- Your edit component renders that value as the input's placeholder.
- **You do not add `textAsPlaceholder`** — the plugin adds and manages it.

That's it. Any block that has at least one `{base}Placeholder` attribute
automatically gets the toggle, the badge and the content-move behaviour.

## Step 1 — declare a `{base}Placeholder` attribute

In your block's `block.json`, add a placeholder counterpart for the content
attribute you want to make templatable. The naming is a strict convention: the
placeholder attribute must be exactly the base attribute name followed by
`Placeholder`.

```jsonc
{
  "name": "acme/callout",
  "attributes": {
    "content": {
      "type": "string",
      "source": "html",
      "selector": ".acme-callout__text"
    },
    "contentPlaceholder": {
      "type": "string",
      "default": ""
    }
  }
}
```

Requirements for the pair to be considered valid:

- **Same `type`** as the base attribute (`string` ↔ `string`, etc.).
- The placeholder attribute has **no `source`** — it is stored in the block's
  comment delimiter (JSON), not read back out of the markup.
- The base attribute exists on the block.

A block may declare several pairs (e.g. `contentPlaceholder`,
`titlePlaceholder`); the single toggle governs all of them.

If a `{base}Placeholder` attribute has no matching base attribute, or the types
differ, the "Placeholder options" panel shows a warning or error in the editor
(with a suggested correct name), so mistakes are caught while authoring.

## Step 2 — render the placeholder in your edit component

Use the `{base}Placeholder` value as the placeholder/hint of your input, so an
empty block shows it greyed out:

```jsx
import { RichText } from '@wordpress/block-editor';

export default function Edit( { attributes, setAttributes } ) {
	const { content, contentPlaceholder } = attributes;

	return (
		<RichText
			tagName="p"
			className="acme-callout__text"
			value={ content }
			placeholder={ contentPlaceholder }
			onChange={ ( value ) => setAttributes( { content: value } ) }
		/>
	);
}
```

This is ordinary block code and works with or without Block Editor Templates
installed — the placeholder simply shows the configured hint when the content is
empty.

## What you do **not** do

You do **not** declare or set `textAsPlaceholder` anywhere in your block. Block
Editor Templates registers that control attribute at runtime, through a
`blocks.registerBlockType` filter, on every block that exposes a
`{base}Placeholder` attribute. Registering it (rather than relying on an unknown
attribute) keeps the block's attribute schema stable, so toggling it never
invalidates or re-syncs the block.

## What gets stored, and why

When an author enables the toggle, the plugin **moves** the typed value from the
base attribute into its placeholder counterpart and clears the base (disabling
moves it back). A saved template block therefore looks like:

```html
<!-- wp:acme/callout {"textAsPlaceholder":true,"contentPlaceholder":"Enter a short summary…"} -->
<p class="acme-callout__text"></p>
<!-- /wp:acme/callout -->
```

- `contentPlaceholder` holds the hint text; the base `content` is empty.
- `textAsPlaceholder` is the plugin's own flag. It drives the editor toggle
  state and the on-canvas badge. Your block never reads it.

When a new post is prefilled from this template, the block is copied verbatim,
so `contentPlaceholder` is already set and your block renders it as its hint —
**that behaviour comes entirely from your block's own placeholder rendering
(Step 2), not from `textAsPlaceholder`.**

## Notes

- The move happens in the editor: on toggle, and — for text typed while the
  toggle is already on — as soon as the block is deselected. While the block is
  selected the typed text is only *copied* into the placeholder attribute, so
  the field the author is typing in is never emptied underneath them.
- Because that same step also runs when a template is opened, a block whose text
  still sits in the base attribute (authored before this behaviour existed, or
  saved with the block still selected) is normalised on load, which marks the
  template as having unsaved changes.
- Content sourced from block markup (`source: "html"` / `"rich-text"`) is
  handled: the move is done in the editor where the value is available, not in
  PHP where markup-sourced content is not a plain attribute. As a safety net,
  prefilling a new post also removes text from a block's markup when that exact
  text is already stored in its `{base}Placeholder` attribute.
