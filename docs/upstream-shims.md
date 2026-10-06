# Upstream shims (temporary fixes)

Workarounds in **pitea-customisation** (and related deploy patches) that exist because upstream Municipio / Modularity / Styleguide / third-party code is missing a fix or API we need. They are **not** permanent site policy.

When you add a shim:

1. Pick a stable **`id`** (kebab-case, e.g. `modularity-where-used-metabox`).
2. Add **`@upstream-shim id=…`** on the class or method (see [Comment format](#comment-format)).
3. Add a row to the [registry](#registry) below.
4. Run `./scripts/list-upstream-shims.sh` and confirm the id appears in code and in this file.

When upstream merges or you upgrade to a release that includes the fix:

1. Follow **Verify removal** for that id.
2. Delete the shim code (and its `App.php` registration if the whole class goes away).
3. Remove the registry row and any `@upstream-shim` tags.
4. Re-run `./scripts/list-upstream-shims.sh` (should stay in sync).

**Related context:** Cursor transcript `fc2b5c67-f628-4f62-99ae-510d05c38291` (admin profiling — `whereUsedMetaBox` / editor slowness).

**Monorepo-only patches** (not in this plugin): see [Deploy patches in pitea-se](#deploy-patches-in-pitea-se).

---

## Comment format

Use a **class or method docblock** (not inline chat-style comments). Custom tags are for humans and `./scripts/list-upstream-shims.sh`; IDEs ignore them.

```php
/**
 * Short summary of what this shim does for Piteå.
 *
 * @upstream-shim id=modularity-where-used-metabox
 * @upstream-repo municipio (Modularity in theme)
 * @upstream-broken ModuleManager::whereUsedMetaBox() runs getModuleUsage() on every add_meta_boxes before checking post type.
 * @upstream-fix-needed Only scan post_content on enabled module post types.
 * @upstream-fixed-in municipio@7.55.8
 * @remove-when Deployed Municipio theme >= 7.55.8; grep ModuleManager.php for post_type guard before getModuleUsage().
 * @verify-removal Edit a page: no SQL LIKE `[modularity id=`. Edit a mod-* post: usage metabox still works.
 */
```

Optional: **`@upstream-fixed-in`** `municipio@abc1234` or **`modularity@6.44.2`** once you know the release.

---

## Registry

Status: **`open`** = still needed · **`watch`** = upstream fix exists; confirm version on upgrade · **`pr-open`** = waiting on merge

| id | Status | Location | Upstream | What is broken | Fix needed in core | Remove when | Verify removal |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `modularity-where-used-metabox` | watch | `source/php/Customisations/ModuleUsageMetabox.php` | Municipio / Modularity (`ModuleManager`) | `whereUsedMetaBox()` calls `getModuleUsage()` (full `post_content` scan) on every `add_meta_boxes`, including pages and the block editor. | Guard: only run usage scan on `$enabled` module post types. | Deployed Municipio theme **≥ 7.55.8** (fix in core). Remove `ModuleUsageMetabox` and its `App.php` registration. | Page edit: no `[modularity id=` query; module edit: usage metabox still present. |
| `municipio-text-post-content-644` | watch | `Text::ensureTextModulePostContent()` | Municipio Text module | 6.44.0–6.44.1 stopped populating `postContent` in view data (regression). | Fixed in Municipio **6.44.2** (PR #2021; regression PR #1999). | Theme/Municipio **≥ 6.44.2** and spot-check Text modules render body content without the filter. | Remove filter; Text module body still renders on front and in preview. |
| `component-library-filter-arity` | open | `Text::applyPendingCardStyles()` (constructor registers 1-arg filter) | ComponentLibrary (mu-plugin vs theme bundle) | `ComponentLibrary/Component/Data` is invoked with 1 arg (legacy) or 2 (new Municipio). | Stable filter signature across deployments, or document required `$accepted_args`. | Single ComponentLibrary version everywhere Piteå runs; filter always passes component instance if we rely on it. | Text module card styling still applies; no PHP argument warnings. |
| `municipio-block-pattern-allowlist` | open | `source/php/Customisations/BlockPatternCompatibility.php` | Municipio block editor allow-list | Restricted `allowed_block_types` hides blocks used inside published `wp_block` patterns. | Include pattern dependency block types in allow-list (or official filter). | Municipio exposes patterns without needing to merge block types from all `wp_block` posts. | Reusable patterns visible in page editor without this class. |
| `municipio-empty-mounted-archive` | open | `source/php/Customisations/Archive.php` | Municipio archive routing | Empty CPT archives rewrite to `page`; modules on archive mount do not load. | Correct `pre_get_posts` / archive mount behavior for zero posts. | Empty mounted archives work without `restoreEmptyMountedArchivePostType` / `ensureArchiveHasPostForModules`. | Empty CPT archive URL shows archive template + Modularity modules, not front page. |
| `better-post-ui-template-dup` | open | `Config::maybeRemoveBlockEditorTemplateSelector()` | Gutenberg + Better Post UI | Duplicate page template UI (classic block editor + Better Post UI). | Single template picker (either core or plugin). | Better Post UI or core resolves duplication; safe to drop filter. | One template control in page editor. |
| `nested-pages-cache-invalidation` | open | `source/php/Customisations/NestedPagesCache.php` | **Third-party:** Nested Pages | Plugin updates `post_parent` via SQL without `clean_post_cache` / `save_post`. | Plugin fires cache hooks or uses WP APIs. | Nested Pages release fixes cache invalidation on sort. | Reorder pages in Nested Pages; permalinks/parent chain correct without this class. |
| `acf-focuspoint-hero-iframe` | open | `source/php/Customisations/FocusPointEditor.php` | acf-focuspoint + block editor iframe | Focus point UI in hero block canvas iframe lacks assets/clicks. | acf-focuspoint (or ACF) loads in editor iframe. | Upstream enqueues focuspoint in iframe; hero background image clickable in editor. | Hero focus point works with this class removed. |

### Not upstream shims

These mention “upstream” in comments but are **Piteå design / permanent** overrides (Styleguide tokens, brand CSS). Do **not** tag with `@upstream-shim`:

- SCSS token gaps (e.g. `drawer.scss`, `inlay-list.scss`, `button.scss`)
- Site routing, Matomo, Font Awesome, permissions, Typesense mappings, etc.

---

## Deploy patches in pitea-se

The WordPress **monorepo** (`pitea-se`) may carry Composer/git patches under `patches/municipio/` until theme upgrades absorb them. Track them here so plugin shims are not confused with deploy patches.

| Patch file | Purpose | Remove when |
| --- | --- | --- |
| `modularity-archive-template-modules.patch` | Load Modularity modules from archive/singular **template** slug in `Module.php`. | Equivalent logic in shipped Municipio/Modularity. |
| `modularity-archive-template-slug-fix.patch` | Template slug fix paired with archive modules. | Same as above. |

After removing a patch, bump Municipio in the monorepo and regression-test archive pages and module areas.

---

## Maintenance commands

```bash
cd wp-content/plugins/pitea-customisation
./scripts/list-upstream-shims.sh
```

When upgrading Municipio/Modularity, grep this file for **`watch`** and **`pr-open`** rows and re-run **Verify removal** before deleting shims.
