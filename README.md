# Piteå Customisation

Site-specific WordPress plugin for the Piteå municipality website. The site runs on the **Municipio** theme with the **Modularity** page builder, and this plugin is the single place for all customizations that extend or modify their behaviour — from icon sets and search routing to external content imports and accessibility tooling.

## What the plugin does

The plugin is organized as a collection of focused customization classes, each self-contained and responsible for one area of the site. Here is a brief description of each:

| Class               | Description                                                                                                                                                                                                                    |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `Accessibility`     | Integrates ReadSpeaker (customer `9687`) on singular pages by wrapping content in the required `<article id="article">` element. Adds a per-post ACF toggle for the accessibility/print button bar.                            |
| `Admin`             | Enqueues admin assets, restricts block editor capabilities (removes block directory, limits font sizes, removes paragraph color controls, and adds paragraph block styles).                                                    |
| `Breadcrumbs`       | Hides breadcrumbs on the front page when a breadcrumbs block is in the slider area, replaces separator icons with `horizontal_rule`, and renames the home breadcrumb to "Start".                                               |
| `ColorPicker`       | Replaces the standard WordPress color picker in all ACF fields with a custom `pitea_color_picker` that shows the design-system palette.                                                                                        |
| `Config`            | Miscellaneous global configuration: loads the plugin textdomain, sets default icons on ServiceInfo/ContactBanner/Noticeboard components, and strips `@font-face` rules from Kirki inline styles on the frontend.               |
| `Decorators`        | Decorates `news` post objects so the archive date uses the WordPress `date_format` option rather than a hardcoded format.                                                                                                      |
| `ExternalContent`   | Registers external content integrations: Sokigo Nova publication endpoint for digital noticeboard notices, traffic disruptions from an ArcGIS FeatureServer (into `modularity-service-info` posts), and e-services from the Piteå eNämnd API (into Typesense). |
| `FontAwesome`       | Full FontAwesome Pro replacement for the theme's Material Symbols icon set. Registers a custom ACF icon picker, converts all `icon` ACF fields to it, and adds FA icon pickers to both TinyMCE and the Gutenberg block editor. |
| `Headers`           | Extends the `Content-Security-Policy` header to add `blob:` to `script-src` and `worker-src`, required for ReadSpeaker's web worker.                                                                                           |
| `Navigation`        | Forces tab menu buttons to use the `c-button--md` size class.                                                                                                                                                                  |
| `News`              | Removes thumbnail images from news items in archive and Posts module contexts, and removes the "Tags:" label prefix from the Tags component.                                                                                   |
| `Pagination`        | Custom pagination rendering that always pins the first and last page items and adds ellipses for large page counts.                                                                                                            |
| `Policies`          | Extends the CSP `connect-src` and `img-src` directives with `data:` URIs.                                                                                                                                                      |
| `PostTypes`         | Loads ACF JSON field group definitions from `post-types/` and saves changes back in development mode.                                                                                                                          |
| `SamlUserProvisioning` | Extends the miniOrange SAML login flow for AzureAD/AD users: validates required SAML attributes, creates or updates users before miniOrange finishes login, assigns WordPress roles from AD groups, and assigns one optional `user_group` taxonomy term. |
| `Search`            | Routes all searches to `/sok/` via a rewrite rule, redirects legacy `/?s=` queries with a 301, and patches search form action URLs and the hero module's search link.                                                          |
| `ShareButton`       | Adds a "Share page" button (copy link to clipboard) after the post signature. Shown by default on all non-page post types; on pages controlled via an ACF toggle.                                                              |
| `Taxonomies`        | Loads ACF JSON field group definitions from `taxonomies/` and saves changes back in development mode.                                                                                                                          |
| `Templates`         | Registers the plugin's `views/` directory as a Blade template path so plugin-provided templates are resolved by Municipio's view layer.                                                                                        |
| `Typesense`         | Maps Typesense document types to hit templates: `lediga-jobb` → `jobposting`, `pitea-eservice` → custom `hit-eservice` Blade template in `views/search/hits/`.                                                                 |
| `Modules\Container` | Removes the `o-container--remove-spacing` class from full-width `acf/container` blocks.                                                                                                                                        |
| `Modules\InlayList` | Auto-assigns FontAwesome icons per link type: PDF → `fa-file-pdf`, external links → `fa-arrow-up-right-from-square`, internal links → `fa-arrow-right`.                                                                        |
| `Modules\LinkCards` | Populates the link-cards color pickers with the design-system SCSS palette.                                                                                                                                                    |
| `Modules\Posts`     | Sets `fa-solid fa-arrow-right` as the archive link icon in the Posts module.                                                                                                                                                   |
| `Modules\Slider`    | Adds an ACF toggle (`slider_show_stepper`) and applies the corresponding CSS class to the slider component.                                                                                                                    |
| `Modules\Text`      | Adds ACF fields for background color, border color/thickness/radius to the Text module and injects the resulting inline CSS on `wp_head`.                                                                                      |

---

## Directory structure

```
pitea-customisation/
├── pitea-customisation.php          # Plugin entry point — defines constants, boots App
├── composer.json                    # PSR-4 autoload: PiteaCustomisation\ → source/php/
├── package.json                     # JS deps: Vite, @wordpress/scripts, FontAwesome Kit, Work Sans
├── vite.config.js                   # Asset build config
├── data/
│   └── fontawesome-icons.json       # Icon list used by the FA picker UI
├── post-types/                      # ACF JSON definitions for custom post types
├── taxonomies/                      # ACF JSON definitions for custom taxonomies
├── views/
│   └── search/hits/                 # Blade templates for Typesense search result hits
├── dist/                            # Built CSS/JS output (generated, do not edit)
└── source/
    ├── assets/                      # PDFs, SVGs, dummy images for templates (not Vite entries)
    ├── css/                         # Plain CSS sources; Vite emits hashed files under dist/css/editor-plugins/
    ├── php/
    │   ├── App.php                  # Bootstrap — enqueues assets, instantiates all classes
    │   ├── Admin/                   # Settings page with tab system (General, External Content, Latest Events)
    │   ├── AcfFields/               # Programmatic ACF field group registrations
    │   ├── Customisations/          # All site-specific customization classes (see table above)
    │   │   └── Modules/             # Modularity module-specific customizations
    │   ├── Decorators/              # Post object decorator pattern (e.g. NewsDateDecorator)
    │   ├── ExternalContent/         # NovaPublicationEndpoint, TrafficDisruptionsImporter, EServicesImporter
    │   └── Helpers/                 # CacheBust, ScssColorParser, utility functions
    ├── js/
    │   ├── main.js                  # Frontend JS entry
    │   ├── admin.js                 # Admin JS entry
    │   ├── editor-plugins/          # TinyMCE / Quicktags / ACF color picker (Vite entries → dist/js/editor-plugins/)
    │   ├── gutenberg/               # Gutenberg block editor extensions
    │   ├── modules/                 # Per-module JS
    │   └── acf/                     # ACF field JS built by Vite (icon picker)
    └── sass/
        ├── style.scss               # Frontend styles entry
        ├── admin.scss               # Admin styles entry
        └── components/ modules/     # SCSS partials
```

---

## How it is bootstrapped

`pitea-customisation.php` defines three constants and calls `new App()`:

| Constant                      | Value                             |
| ----------------------------- | --------------------------------- |
| `PITEA_CUSTOMISATION_PATH`    | Absolute path to plugin directory |
| `PITEA_CUSTOMISATION_URL`     | Plugin URL                        |
| `PITEA_CUSTOMISATION_VERSION` | `1.0.0`                           |

`App.php` then:

1. Hooks asset enqueueing (`wp_enqueue_scripts`) with cache-busted filenames read from the Vite manifest.
2. Instantiates every customization class via `registerInstances()`. Each class registers its own WordPress hooks inside its constructor.

---

## SSO / AD login with miniOrange

The site uses the **miniOrange SAML 2.0 Single Sign-On** plugin as the SAML service provider. AzureAD is the identity provider. The custom class `PiteaCustomisation\Customisations\SamlUserProvisioning` does not replace miniOrange; it hooks into miniOrange's login flow to make the WordPress user provisioning rules match Piteå's AD group model.

The customization is only enabled when private configuration provides an allowed AD group map through the `PITEA_SAML_GROUPS` constant. If that config is missing or empty, the class does not register its SAML hooks.

Expected config shape:

```php
define('PITEA_SAML_GROUPS', [
    [
        'name' => 'Administrator group label',
        'group_id' => '00000000-0000-0000-0000-000000000001',
        'role' => 'administrator',
        'add_to_user_group' => false,
    ],
    [
        'name' => 'Editor group label',
        'group_id' => '00000000-0000-0000-0000-000000000002',
        'role' => 'editor',
        'add_to_user_group' => true,
    ],
]);
```

### miniOrange responsibility

miniOrange is still responsible for the SAML protocol work:

1. Redirecting the user to AzureAD.
2. Receiving and validating the SAML response.
3. Extracting SAML attributes into the `$attrs` array.
4. Running its internal `mo_saml_check_mapping()` flow.
5. Firing miniOrange extension hooks such as `mo_saml_user_attributes` and `mo_saml_user_group_name`.
6. Completing the WordPress login by setting auth cookies and redirecting the user.

The customization class only acts once miniOrange has a trusted SAML attribute array.

### Forced miniOrange attribute mapping

On `init` priority `0`, `SamlUserProvisioning::ensureMiniOrangeAttributeMapping()` makes sure the miniOrange attribute mapping stays aligned with our custom flow:

| miniOrange option | Value |
| ----------------- | ----- |
| `saml_am_email` | `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress` |
| `saml_am_username` | `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress` |
| `saml_am_first_name` | `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname` |
| `saml_am_last_name` | `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname` |
| `saml_am_group_name` | `http://schemas.microsoft.com/ws/2008/06/identity/claims/groups` |
| `saml_am_account_matcher` | `email` |

This is deliberately strict. miniOrange must find users by email, while our custom code controls the actual WordPress `user_login`.

### Attributes we depend on

The customization class expects these AzureAD/SAML attributes:

| Attribute | Used for |
| --------- | -------- |
| `user.onpremisessamaccountname` | Source of the WordPress username. The CN part is extracted from a DN such as `CN=TEST01,OU=...`, resulting in `TEST01`. |
| `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress` | WordPress email address and miniOrange account matching. Required. |
| `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname` | WordPress first name. |
| `http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname` | WordPress last name. |
| `http://schemas.microsoft.com/identity/claims/displayname` | WordPress display name. |
| `http://schemas.microsoft.com/ws/2008/06/identity/claims/groups` | AD group IDs used for access, role selection, and optional `user_group` assignment. |

`NameID` can still be present in the SAML response and miniOrange flow, but it is not the basis for the WordPress username in our custom provisioning. The username comes from the CN in `user.onpremisessamaccountname`.

### Login flow with our customization

The important sequence is:

1. miniOrange validates the SAML response and builds `$attrs`.
2. miniOrange fires `do_action('mo_saml_user_attributes', $attrs)`.
3. Our `validateUserAttributes()` callback runs early on that action.
4. We validate that a usable CN account name and email exist.
5. We check the returned AzureAD group IDs against the allowed group list.
6. If no allowed group is present, login is stopped with `wp_die()` (`403 Access denied`). In WP-CLI context this becomes `WP_CLI::error()`.
7. If access is allowed, we create or update the WordPress user before miniOrange continues its own user-login handling.
8. We apply the selected WordPress role and optional `user_group` taxonomy term.
9. miniOrange continues its normal login flow, finds the user by email, sets cookies, may run its own first/last-name update, and redirects.
10. Our later hooks reapply role and `user_group` after miniOrange has the final user ID, so miniOrange's remaining flow does not undo our permission decision.

The class hooks the flow in three places:

| Hook | Purpose |
| ---- | ------- |
| `mo_saml_user_attributes` | Main validation and provisioning point. This is where we deny access, create/update the user, and store the chosen role/group for the current login. |
| `mo_saml_user_group_name` | Runs later in miniOrange's flow when miniOrange has a user ID. We reapply the role and `user_group` here. |
| `set_auth_cookie` | Final safety pass after WordPress auth cookies are set. We reapply the role and `user_group` once more for this login. |

### User creation and updating

When a user is allowed through:

1. The WordPress login name is the extracted CN, for example `CN=TEST01,...` becomes `TEST01`.
2. The email claim becomes `user_email`.
3. Given name, surname, and display name update the corresponding WordPress profile fields when present.
4. Existing users are matched by email first.
5. If the email belongs to one WordPress user and the CN login belongs to a different WordPress user, login is denied. This prevents accidentally merging two identities.
6. If the CN login already exists for another account while no matching email user exists, login is denied.
7. New users receive a generated password because the real authentication is SAML.

### Role and group rules

Access is controlled by the private AD group list loaded from `PITEA_SAML_GROUPS`.

There are two separate decisions:

1. **WordPress role**: selected from the first matching allowed group in the configured group priority order. The administrator group is listed first, so it wins and grants `administrator` when present. All other allowed groups currently grant `editor`.
2. **`user_group` taxonomy**: selected from the first incoming SAML group that is marked `add_to_user_group => true`. This preserves the order AzureAD sends in the SAML response. A user is assigned to only one `user_group`.

Groups with `add_to_user_group => false` grant access and a role, but must not create or assign a `user_group` term.

### `user_group` taxonomy behavior

For groups that should be represented in the `user_group` taxonomy:

1. The AD group ID is used as the term slug.
2. The configured group label is used as the term name when the term is created.
3. If a term already exists with the correct slug, the existing term is reused.
4. If an existing term's name is still the raw group ID, the name is repaired to the configured label.
5. If an editor has renamed the term in WordPress, that name is preserved on future logins.
6. Before assigning the selected group, all existing `user_group` relationships for the user are removed, ensuring each user has at most one `user_group`.

On `init` priority `100`, and again after access is granted, the class deletes `user_group` terms for allowed AD groups that are marked `add_to_user_group => false`. This prevents role-only AD groups from lingering in the taxonomy when they are only meant to grant WordPress access.

On multisite, all `user_group` taxonomy reads and writes are performed on the main site via `switch_to_blog(get_main_site_id())`.

## Admin settings

The plugin adds a **Settings → Piteå kommun** page in the WordPress admin with three tabs:

| Tab              | Settings                                            |
| ---------------- | --------------------------------------------------- |
| General          | Overview / introduction only                        |
| External Content | Sokigo Nova endpoint info · Traffic disruptions source URL · E-services API URL |
| Latest Events    | Visit Piteå API URL · API token                     |

Settings are saved via AJAX with nonce validation.

## External Content

### Sokigo Nova publication endpoint

The plugin exposes a custom WordPress REST endpoint for Sokigo Nova so building permit notices can be published to the digital noticeboard:

```text
POST /wp-json/nova/v1/publish
```

The endpoint requires HTTP Basic Authentication and is active when these constants are defined:

```php
define('SOKIGO_NOVA_PUBLISH_USERNAME', '...');
define('SOKIGO_NOVA_PUBLISH_PASSWORD', '...');
```

Incoming publications are saved as `noticeboard_notice` posts. Nova `publishDate` becomes the WordPress post date, so future dates are scheduled by WordPress. Nova `publishEndDate` is saved to the noticeboard `archive_date` and `archive_time` fields.

Notice type terms are assigned on `noticeboard_notice_type`:

| Nova type | Terms |
| --------- | ----- |
| `1`       | `Kungörelser`, `Bygglov` |
| `2`       | `Beslut`, `Bygglov`      |
| `3`       | `Bygglov`                |

---

## Requirements

| Requirement                  | Notes                                                |
| ---------------------------- | ---------------------------------------------------- |
| PHP 8.0+                     |                                                      |
| Node.js 18+                  | For building assets                                  |
| Composer                     | PSR-4 autoloading (no additional PHP packages)       |
| Advanced Custom Fields (ACF) | Custom field types, JSON-based post types/taxonomies |
| Municipio theme              | Core view/template filters (`Municipio/*`)           |
| Modularity                   | Module view data filters (`Modularity/*`)            |
| ComponentLibrary             | Icon, Pagination, Button, NewsItem component filters |
| modularity-link-cards        | Color picker integration                             |
| modularity-service-info      | Traffic disruptions content type                     |
| typesense-search             | E-services Typesense indexing                        |
| FontAwesome Pro Kit          | Kit `ae5fa37ad3` (loaded via `@awesome.me`)          |

---

## Installation

1. Navigate to the plugin directory:

   ```bash
   cd wp-content/plugins/pitea-customisation
   ```

2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Install Node.js dependencies:

   ```bash
   npm install
   ```

4. Build assets:

   ```bash
   npm run build
   ```

5. Activate the plugin in WordPress admin.

---

## Development

```bash
# Watch with HMR
npm run dev

# Production build
npm run build
```

---

## Adding a new customization

1. Create a class in `source/php/Customisations/` (or `Customisations/Modules/` for module-specific work):

   ```php
   <?php

   namespace PiteaCustomisation\Customisations;

   class MyFeature
   {
       public function __construct()
       {
           add_filter('some/filter', [$this, 'myMethod']);
       }

       public function myMethod($value)
       {
           // modify $value
           return $value;
       }
   }
   ```

2. Register it in `source/php/App.php` inside `registerInstances()`:

   ```php
   Customisations\MyFeature::class,
   ```

   Autoloading is PSR-4 — new files in an existing namespace directory are picked up automatically. Run `composer dump-autoload` if you add a new sub-namespace directory.

---

## License

MIT
