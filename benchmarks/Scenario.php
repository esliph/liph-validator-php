<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use LogicException;

/**
 * Guards a benchmark scenario before it is measured: a subject named "all invalid"
 * that accidentally passes measures the wrong path and still prints a number.
 */
final class Scenario {

  public static function expectSuccess(Schema $schema, mixed $input): void {
    $result = $schema->safeParse($input);

    if (!$result->success) {
      throw new LogicException('Scenario expected to pass, but it failed with ' . count($result->issues) . ' issue(s)');
    }
  }

  public static function expectIssues(Schema $schema, mixed $input, int $issues): void {
    $result = $schema->safeParse($input);

    if ($result->success || count($result->issues) !== $issues) {
      $received = $result->success ? 'success' : count($result->issues) . ' issue(s)';

      throw new LogicException("Scenario expected {$issues} issue(s), received {$received}");
    }
  }
}
