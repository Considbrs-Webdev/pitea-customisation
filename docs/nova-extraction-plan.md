# Extract Nova integration to modularity-noticeboard

Status: cleanup implemented against the modularity-noticeboard 1.1.0 public
interface. Paired staging/deployment checks below remain required before rollout.
The local endpoint is removed; the guarded status/settings panel is preserved.

## Dependency

The shared plugin implements the writer, optional Nova adapter, configuration/status interface,
and migration compatibility in modularity-noticeboard branch
codex/feature-noticeboard-integrations. Its initial plan is at
docs/noticeboard-integrations-plan.md.

## Extraction scope

1. Replace direct construction of
   ExternalContent/Noticeboard/NovaPublicationEndpoint in
   Customisations/ExternalContent.php with any required Piteå-specific integration
   configuration hooks. Preserve unrelated service information and search imports.
2. Remove NovaPublicationEndpoint.php after responsibility transfers to the shared
   plugin. The shared endpoint must preserve the existing route, credentials,
   response contract, publication scheduling, and archive fields.
3. Preserve the Digital noticeboard / Building permit notices panel in
   Admin/Tabs/ExternalContentTab.php. Read status and endpoint URL through the shared
   plugin's public interface; link to its integration settings. Guard absent/older
   plugin versions and display an unavailable/dependency message without fatal errors.
   Currently this panel is informational; it has no editable Nova options to migrate.
4. Keep only Piteå-specific policy here. Use the shared plugin's documented filters
   for term mappings or formatting if needed; retain existing mappings as defaults
   unless a deliberate policy change is approved.
5. Update README.md and CLAUDE.md to describe shared ownership, required plugin
   version, configuration location, and rollout/rollback procedure. Preserve existing
   SOKIGO_NOVA_PUBLISH_USERNAME / PASSWORD configuration compatibility.

## Migration and deployment

- Inventory existing _pitea_nova_publication_id/type metadata without exposing
  credentials or full payloads. Verify the shared plugin updates those posts rather
  than creating duplicates; do not delete legacy metadata before migration succeeds.
- Agree route handoff with the shared implementation: use an activation switch or
  compatibility guard so mixed plugin versions cannot register competing callbacks.
- Declare/document the minimum compatible noticeboard version. Validate the paired
  release on staging before deploying cleanup; do not deploy this removal by itself.
- Keep a coordinated rollback path for both plugins and metadata compatibility.

## Verification

- Confirm exactly one Nova route callback is active after transfer.
- Send representative types 1, 2, and 3; verify credentials, response shape, expected
  taxonomy terms, scheduled publication, archive date/time, and content formatting.
- Retry an existing publication and verify its WordPress ID is preserved.
- Verify status panel behaviour with configured, unconfigured, missing, and older
  noticeboard versions, and that no credentials are displayed.
- Run PHP syntax checks for changed code and exercise the settings page plus unrelated
  external import registration on staging.

## Completion criteria

Piteå customisation no longer owns Nova transport/authentication/persistence,
existing incoming publications continue through modularity-noticeboard, and Piteå
retains its useful status panel and any client-specific configuration hooks.
