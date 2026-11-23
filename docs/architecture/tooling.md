# Tooling reference

Everything runs in Docker through the `Makefile`. PHP and Composer are not expected on the host. Extra arguments go in the variable `ARGS`. Every entry below has the same fields: what runs in the container, how `ARGS` is used, and an example. The heading is the command.

## Make targets

### `make deps`

- **Runs:** `composer install` in the `composer` service (image `composer:latest`).
- **`ARGS`:** passed to `composer install`.
- **Example:** `make deps`

### `make test`

- **Runs:** `composer run test` in the `validator` service, which runs `vendor/bin/phpunit`.
- **`ARGS`:** forwarded to `vendor/bin/phpunit`.
- **Example:** `make test ARGS="--filter test_min_WithValueGreaterThanMin_ShouldPass"`. To run one file: `make test ARGS="tests/Schemas/Primitive/IntegerSchemaTest.php"`.

### `make analyse`

- **Runs:** `composer run analyse`, which runs `vendor/bin/phpstan analyse --memory-limit=1G`.
- **`ARGS`:** forwarded to PHPStan.
- **Example:** `make analyse ARGS="--level=max"`

### `make cov`

- **Runs:** `composer run test:coverage`: PHPUnit with `pcov.enabled=1` and a text coverage report.
- **`ARGS`:** forwarded to PHPUnit.
- **Example:** `make cov`

### `make cov_html`

- **Runs:** `composer run test:coverage:html`: the same, with an HTML report in `cache/tests/coverage`.
- **`ARGS`:** forwarded to PHPUnit.
- **Example:** `make cov_html`

### `make bench`

- **Runs:** `composer run bench`, which runs `vendor/bin/phpbench run --report=aggregate`. The target passes the variable `BENCH_SRC` to the container.
- **`ARGS`:** forwarded to `phpbench run`.
- **Example:** `make bench ARGS="--filter=UnionInvalid --tag=before"` saves a tagged run to use as a baseline. A later run compares against it with `--ref=before`.

### `make cli`

- **Runs:** a `bash` shell in the `validator` service.
- **`ARGS`:** appended to the `bash` command.
- **Example:** `make cli`

### `make down`

- **Runs:** `docker compose down -v`.
- **`ARGS`:** appended to that command.
- **Example:** `make down`

## PHPStan

PHPStan runs at level 8 over `src`, `tests` and `benchmarks`. `phpstan.neon` sets the cache directory to `cache/phpstan` and includes `phpstan-baseline.neon`, which lists the errors that are accepted for now. No formatter is configured.

## PHPUnit

`phpunit.xml` sets `failOnDeprecation="true"`: a PHP deprecation fails the suite. The `pcov` extension is installed, and `docker/php/conf.d/pcov.ini` turns it off by default. Only the coverage scripts turn it on, with `php -d pcov.enabled=1`.

## PHPBench

The subjects are the classes `benchmarks/*Bench.php`, in the namespace `Esliph\Validator\Benchmarks\`. `phpbench.json` sets the bootstrap, the path and the repetitions. Tagged runs are stored in `cache/phpbench`, which is local to the machine, and compared with `--ref=<tag>`. Composer's process timeout is disabled for the bench script.

### Measuring another version of `src/`

`benchmarks/bootstrap.php` reads the variable `BENCH_SRC`. When it points at a directory, the library classes load from that directory instead of `src/`, so the same benchmarks measure another version of the library without touching the working tree.

To compare your change with the previous commit, in one sitting:

1. Export the `src/` of that commit (`<sha>` is the commit you compare with, for example `HEAD` before you commit your change):

```sh
mkdir -p cache/bench/<sha> && git archive <sha> src | tar -x -C cache/bench/<sha>
```

2. Run the benchmarks on the old copy and save the run under a tag:

```sh
make bench BENCH_SRC=cache/bench/<sha>/src ARGS="--tag=base"
```

3. Run the same benchmarks on the working tree (no `BENCH_SRC`) and compare with the tag. `--filter` takes the name of a benchmark class from `benchmarks/`, such as `UnionMemberFailureBench`:

```sh
make bench ARGS="--ref=base"
```

A class that is missing from `BENCH_SRC` is an error, never a fallback to `src/`: a fallback would measure a mix of two versions.

### Reading the numbers

The container has no opcache and no JIT. The numbers are comparable only inside the same session, so compare a baseline and a change in one sitting.
