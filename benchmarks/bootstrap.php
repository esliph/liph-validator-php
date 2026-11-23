<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/*
 * BENCH_SRC: directory holding another version of `src/` (e.g. extracted from a
 * commit with `git archive`). When set, library classes load from there instead
 * of `src/`, so the same benchmark code measures the old library code without
 * touching the working tree.
 *
 * A library class missing from BENCH_SRC is an error instead of a silent
 * fallback to `src/`: falling back would measure a mix of both versions.
 */
$alternativeSource = getenv('BENCH_SRC');

if ($alternativeSource !== false && $alternativeSource !== '') {
  $root = realpath($alternativeSource);

  if ($root === false || !is_dir($root)) {
    throw new RuntimeException("BENCH_SRC '{$alternativeSource}' is not a directory");
  }

  spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Esliph\\Validator\\';

    if (!str_starts_with($class, $prefix)) {
      return;
    }

    $relative = substr($class, strlen($prefix));

    if (str_starts_with($relative, 'Benchmarks\\') || str_starts_with($relative, 'Tests\\')) {
      return;
    }

    $file = $root . '/' . str_replace('\\', '/', $relative) . '.php';

    if (!is_file($file)) {
      throw new LogicException("Class {$class} does not exist in BENCH_SRC ({$root}); the benchmark uses API that the baseline lacks");
    }

    require $file;
  }, prepend: true);
}
