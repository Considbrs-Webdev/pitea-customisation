# Editor preview for Modularity modules

How Consid modules should appear in the Gutenberg canvas on Municipio 7 / WordPress 7.1 (iframed editor). Reasonable layout is the goal. Pixel-perfect clones of the front end are out of scope.

## How blocks are registered

Modularity registers each enabled module as an ACF block in `BlockManager::registerBlocks()`:

- `acf_register_block_type()` with `render_callback` → `BlockManager::renderBlock()`
- Block name is `acf/{slug}` (the `mod-` prefix is stripped)
- Default `mode` is `edit`. On WordPress 7.1 the canvas still server-renders the block; fields sit in the sidebar
- `renderBlock()` calls the module `data()` and Blade `template()`, then `Modularity\Display::renderView()`
- There is no per-module React `save()`. Previews are PHP/Blade, not `ServerSideRender` from a custom script
- Hooks plugins can use without editing the theme: `Modularity/Block/Settings`, `Modularity/Block/Data`, `Modularity/Block/{name}/Data`, `/Modularity/externalViewPath`

Municipio enqueues `blockeditor.css` and `municipio.css` on `enqueue_block_editor_assets`. That hook styles the parent editor, not the content iframe. `add_editor_style()` (styleguide, TinyMCE, Piteå `admin.scss`) does load in the iframe. Component-library modules (posts, manual input cards) therefore look closer to the front end than modules whose chrome lives in a plugin stylesheet.

## Editor width

`source/sass/general/editor.scss`, compiled into `admin.scss`.

That file is loaded in the canvas two ways: `add_editor_style()`, and `enqueue_block_assets` (handle `pitea-customisation-editor-canvas`). The second path is the one WordPress copies into the iframe asset list. `add_editor_style()` is kept so classic editor content still receives the same rules.

Root blocks use `max-width: var(--container-width, 1280px)`, the same token as the front-end container (`source/tokens/pitea-brand.tokens.json`). The rule is unlayered so it wins over Municipio’s unlayered `html :where(.wp-block) { max-width: 100% }`. `wide` stays slightly wider. `full` stays unbounded. Only direct children of `.is-root-container` are constrained, so nested column blocks and the block sidebar are left alone.

## When to enqueue styles

Enqueue the module’s built CSS on `enqueue_block_assets`, and only when `is_admin()` is true, so the front end is not double-loaded and wp-admin chrome is not restyled.

Also register the same URL on `Pitea/Editor/ModuleStyles` (`PiteaCustomisation\Customisations\EditorModuleStyles`) so one place owns the iframe policy:

```php
add_filter('Pitea/Editor/ModuleStyles', function (array $styles) use ($url): array {
    $styles['my-module'] = $url;
    return $styles;
});
```

Do not enqueue front-end JS bundles in the editor. Skip rules that assume the public page: `100vh` heroes, sticky header, quick-exit fixed positioning, autoplaying carousels, maps.

`enqueue_block_editor_assets` is the wrong hook for preview chrome. It never enters the iframe.

## Preview decision tree

### A. Server-render when it is cheap and safe

Use the existing `render_callback` / Blade output when:

- The render does not call a slow or flaky remote API
- It does not depend on front-only globals or SSO
- An empty result is honest (“Inga evenemang” when there are no events)

Style that HTML with the module CSS from the section above. Link cards and quick links are this case: the card data is already in the block attributes, and ACF sets that meta up before `render_callback`.

### B. Structured placeholder when server render is empty, slow, or wrong in admin

If the front end fills itself with JS, or the data source is a remote API, do not run that script in the editor. Render module chrome plus one of:

- A few rows from a short-lived cache (cap the count, for example 3)
- A single status line that says the preview is unavailable
- Skeleton rows that use the module’s own card classes, never an unstyled list

Empty data must not look like a broken list. “Inga evenemang” means the source returned nothing. “Förhandsvisning otillgänglig” means the editor could not build a preview.

Latest events is this case. The public template is a skeleton list replaced by `simpleview-events.ts`. The editor does not load that script. It prints the same four events the public script requests, or the status line plus styled skeletons.

### C. Do not rebuild the front end in React

No per-module editor bundle in this pattern.

## Pilots

| Module | Repo | Choice | Why |
| --- | --- | --- | --- |
| Link cards | `modularity-link-cards` | A | Blade already renders cards from block fields. The iframe was missing `modularity-link-cards.css`. |
| Quick links (Startsida snabb-länkar) | `modularity-quick-links` | A | Same gap: CSS was on `enqueue_block_editor_assets`. |
| Latest events | `modularity-latest-events` | B | Public markup is a JS skeleton. Editor reads the cached event list (or one short request) and renders card rows. No editor JS. |
| A-Ö links | `modularity-a-o` | A | The index is a local page tree plus manual rows. The editor renders that same Blade. The iframe was missing `modularity-a-o.css`. |
