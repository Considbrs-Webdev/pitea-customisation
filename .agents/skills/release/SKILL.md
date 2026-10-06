---
name: release
description: >-
  Cut a new version of the pitea-customisation plugin — merge dev into main,
  bump the version, update CHANGELOG.md, commit, tag, and publish a GitHub
  release. Use when the user asks to release, cut a version, bump the version,
  or tag a release.
---

# Cutting a release

A release merges `dev` into `main`, bumps the version, writes a changelog entry, tags `main`, and publishes a GitHub release. It does not deploy the site. `pitea.se` keeps running whatever its `composer.local.json` constraint resolves to (currently `dev-dev`) until that constraint is changed.

Version lives in **three** places and must move together:

- `pitea-customisation.php` — `* Version: X.Y.Z` header and `PITEA_CUSTOMISATION_VERSION`
- `package.json` — `"version": "X.Y.Z"`
- `package-lock.json` — root `"version"` and `packages[""].version`

Leave `composer.json` without a `version` field. Composer resolves the version from the git tag.

Built assets stay gitignored (`/dist/`). The deployment build runs this plugin's `build.php` and compiles them. Do not commit `dist/`, and never run `build.php --cleanup` locally.

There is no test suite. Do not invent `composer test` or CI steps.

## Branches

- `dev` is where work lands and what the site currently installs (`dev-dev`).
- `main` is the release branch. It only receives merges from `dev` when cutting a release, and tags are created on it.
- `main` may carry commits that `dev` does not have. Merge `dev` into `main` with a merge commit; do not force-push or reset `main`.

## 1. Figure out the next version

```bash
git fetch origin --tags
git checkout dev
git pull --ff-only origin dev
git describe --tags --abbrev=0        # fails if there is no tag yet: the first release is 1.0.0
git log --oneline <last-tag>..dev
```

Semver: bug fixes → patch, backward-compatible features → minor, breaking change (renamed hook, option, constant, or a config change production must make before the new code is safe, e.g. a new required constant) → major. When in doubt, ask.

If the release depends on config or other repos (for example `PITEA_SAML_GROUPS` in `config/plugins.php`, or a paired `modularity-*` release), say so in the changelog entry.

## 2. Draft the changelog entry — confirm before writing

Write all changelog entries and GitHub release text in English, even when the request or conversation is in Swedish. Keep the GitHub release body consistent with the corresponding `CHANGELOG.md` entry.

Summarize unreleased commits into user-facing bullets: "Fixed X happening when Y", not class or method names. Group under `### Added` / `### Changed` / `### Fixed`. Show the draft and the version number before writing files.

Skip this pause only when the user already named the version and asked for the full release in the same request.

If `CHANGELOG.md` does not exist, create it with this header, and make the first entry (1.0.0) an `### Added` list with an "Initial release" line:

```markdown
# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
```

## 3. Merge dev into main

```bash
git checkout main
git pull --ff-only origin main
git merge --no-ff dev -m "Merge dev for release X.Y.Z"
```

If the merge conflicts, stop and show the conflicts. Do not resolve silently.

## 4. Apply the version bump

Set both version strings in `pitea-customisation.php`.

```bash
npm version <X.Y.Z> --no-git-tag-version --allow-same-version
```

Insert the confirmed entry at the top of `CHANGELOG.md`, after the header, before the previous release:

```markdown
## [X.Y.Z] - YYYY-MM-DD

### Fixed
- ...
```

## 5. Verify

```bash
php -l pitea-customisation.php
npm run build       # Vite build + Gutenberg build must still succeed
```

Confirm the version locations all equal `X.Y.Z`, and that `composer.json` still has no `version` field:

```bash
grep -n "Version:\|PITEA_CUSTOMISATION_VERSION" pitea-customisation.php
grep -n '"version"' package.json composer.json
```

## 6. Commit

Behavior changes belong in their own commits on `dev` before the release starts. The release commit touches only the version files, `CHANGELOG.md`, and this skill when the skill itself changed:

```bash
git add pitea-customisation.php package.json package-lock.json CHANGELOG.md
git commit -m "Release X.Y.Z"
```

Do not add attribution lines to commit messages.

## 7. Push and tag — confirm before this step

This publishes a release on the shared remote. Confirm before running it, unless the user already asked for the full release including tag and publish.

```bash
git push origin main

git tag -a X.Y.Z -m "Release X.Y.Z"
git push origin X.Y.Z
```

Then bring the release commit back so `dev` does not fall behind `main`:

```bash
git checkout dev
git merge --ff-only main || git merge main
git push origin dev
```

If `main` has diverged from `origin/main`, stop. Do not force-push.

## 8. Publish the GitHub release

Title `vX.Y.Z`. Body is the changelog entry, starting with `## [X.Y.Z] - YYYY-MM-DD`. Do not add "Generated with Claude Code" or similar to the body.

```bash
gh release create X.Y.Z --repo Considbrs-Webdev/pitea-customisation \
  --title "vX.Y.Z" \
  --notes "$(cat <<'EOF'
## [X.Y.Z] - YYYY-MM-DD

### Fixed
- ...
EOF
)"
```

## 9. Pin the deployment

Tagging does not change production. `pitea.se` installs this plugin from `composer.local.json`, currently:

```json
"considbrs-webdev/pitea-customisation": "dev-dev"
```

Do not change it unless the user asks. When they want the site to run this release, set it to `"X.Y.Z"`. Until then, say which constraint is still installed (`dev-dev` or the previous tag) and what to change.
