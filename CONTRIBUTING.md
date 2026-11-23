# Contributing

Thank you for helping with `esliph/validator`. This guide covers the branch model, how to run the checks and what a pull request needs.

By taking part you agree to the [Code of Conduct](CODE_OF_CONDUCT.md). To report a vulnerability, follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

## Branches

The project uses gitflow.

- `master` holds released commits only. Each release is tagged `vX.Y.Z`.
- `develop` integrates ongoing work. **Open your pull request against `develop`.** A check fails any pull request to `master` that does not come from `release/*` or `hotfix/*`.
- Topic branches start from `develop` and are named `feature/<name>` or `fix/<name>`.
- A hotfix starts from `master` as `hotfix/<x.y.z>`. It is merged into `master`, tagged, then merged back into `develop`.

Contributors work from a fork and target `develop`.

## Commits and pull request titles

Use the format `type: Description`, in English.

| Type | Use for |
| --- | --- |
| `feat` | New behavior |
| `fix` | A bug fix |
| `refactor` | A change with no behavior change |
| `test` | Tests only |
| `config` | Project and repository configuration |
| `build` | Dependencies, CI and release tooling |
| `remove` | Removing code or files |

Topic pull requests are squash-merged, so the pull request title becomes the commit message on `develop`. The maintainer fixes a title that does not follow the format.

## Running the checks

PHP 8.3 or newer is required. Both ways below run the same PHPUnit suite and PHPStan (level 8).

With Docker and `make`, no PHP on the host:

```sh
make deps      # composer install
make test      # PHPUnit
make analyse   # PHPStan
```

With a local PHP and Composer:

```sh
composer install
composer run test
composer run analyse
```

To run one test, pass the filter after `--` (`make test ARGS="--filter <name>"` does the same with `make`):

```sh
composer run test -- --filter test_min_WithValueGreaterThanMin_ShouldPass
```

CI runs the suite on PHP 8.3, 8.4 and 8.5, and PHPStan on 8.3. A pull request needs all of them green.

## Tests

Name tests `test_<method>_<Condition>_Should<Result>`. A new schema extends the matching base test case, so it inherits the common tests. [`docs/architecture/testing.md`](docs/architecture/testing.md) lists the base classes and when to use each one. Before you change `src/`, read [`docs/architecture/README.md`](docs/architecture/README.md).

Every behavior change comes with a test, and a bug fix comes with a test that fails before the fix.

## Changelog

Add a line under `## [Unreleased]` in `CHANGELOG.md` for every change a user can notice, grouped as `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed` or `Security`.

## Before you open the pull request

- The tests and static analysis pass.
- The change has tests.
- `CHANGELOG.md` is updated.
- The title follows `type: Description`.
