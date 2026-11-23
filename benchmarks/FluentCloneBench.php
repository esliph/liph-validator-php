<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Schemas\Primitive\StringSchema;
use Esliph\Validator\V;
use Generator;
use PhpBench\Attributes as Bench;

/**
 * Cost of fluent calls, which return a modified clone of the schema.
 *
 * - `OptionalOn*`: one fluent call on a composite with N children (each child
 *   carries a rule), so the cost tracks how much of the tree a clone copies.
 * - `RuleChain`: builds a schema by chaining N rules; each call clones the rules
 *   accumulated so far, so the time ratio between sizes exposes the total cost.
 *
 * Sizes grow 10x per step: a time ratio of ~10x between steps is linear,
 * ~100x is quadratic.
 */
final class FluentCloneBench {

  private Schema $schema;

  /**
   * @return Generator<string, array{size: int}>
   */
  public function provideSizes(): Generator {
    yield 'n=10' => ['size' => 10];
    yield 'n=100' => ['size' => 100];
    yield 'n=1000' => ['size' => 1000];
  }

  /**
   * @return Generator<string, array{size: int}>
   */
  public function provideChainLengths(): Generator {
    yield 'n=5' => ['size' => 5];
    yield 'n=50' => ['size' => 50];
    yield 'n=500' => ['size' => 500];
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpObject(array $params): void {
    $shape = [];

    for ($i = 0; $i < $params['size']; $i++) {
      $shape["field{$i}"] = V::string()->min(1);
    }

    $this->schema = V::object($shape);
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpUnion(array $params): void {
    $this->schema = V::union(...array_map(static fn(): Schema => V::string()->min(1), range(1, $params['size'])));
  }

  /**
   * @param array{size: int} $params
   */
  #[Bench\ParamProviders('provideSizes')]
  #[Bench\BeforeMethods('setUpObject')]
  public function benchOptionalOnObject(array $params): void {
    $this->schema->optional();
  }

  /**
   * @param array{size: int} $params
   */
  #[Bench\ParamProviders('provideSizes')]
  #[Bench\BeforeMethods('setUpUnion')]
  public function benchOptionalOnUnion(array $params): void {
    $this->schema->optional();
  }

  /**
   * @param array{size: int} $params
   */
  #[Bench\ParamProviders('provideChainLengths')]
  #[Bench\Revs(10)]
  public function benchRuleChain(array $params): StringSchema {
    $schema = V::string();

    for ($i = 0; $i < $params['size']; $i++) {
      $schema = $schema->min(1);
    }

    return $schema;
  }
}
