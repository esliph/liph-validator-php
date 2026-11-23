# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- MIT `LICENSE`, `CODE_OF_CONDUCT.md` (Contributor Covenant 2.1) and `SECURITY.md`.
- CI on GitHub Actions: tests on PHP 8.3, 8.4 and 8.5, PHPStan, and a guard that only lets `release/*` and `hotfix/*` target `master`.
- Release workflow: a `vX.Y.Z` tag publishes a GitHub Release with the notes of its changelog section.
- `CONTRIBUTING.md`, issue forms and a pull request template.
- A PHPStan baseline for errors that already existed.
- Repository documentation: a usage guide in `docs/guide/` and architecture notes in `docs/architecture/`.

### Changed

- Comments and docblocks are now in English. There is no behavior change.
- The Packagist archive carries only `src/`, `composer.json`, `LICENSE`, `README.md` and `CHANGELOG.md`.

### Fixed

- The `not_finite` message no longer casts `NAN` to string, which raised a warning on PHP 8.5. The text is unchanged.
