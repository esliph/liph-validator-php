<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\V;
use Generator;
use PhpBench\Attributes as Bench;

/**
 * Cost of telling an indexed list from an associative array.
 *
 * `V::_array()` without an element schema does no per-element work, so the
 * parse is dominated by the list check. Implementations differ in complexity
 * (a packed array without holes can be recognized in O(1); comparing against
 * rebuilt key arrays is O(n)) and in the temporary arrays they allocate — read
 * `mem_peak` alongside the time.
 *
 * Sizes grow 100x per step: flat times across sizes mean O(1).
 */
#[Bench\ParamProviders('provideSizes')]
#[Bench\BeforeMethods('setUp')]
final class ListCheckBench {

  private Schema $schema;
  /** @var list<int> */
  private array $input;

  /**
   * @return Generator<string, array{size: int}>
   */
  public function provideSizes(): Generator {
    yield 'n=10' => ['size' => 10];
    yield 'n=1000' => ['size' => 1000];
    yield 'n=100000' => ['size' => 100000];
  }

  /**
   * @param array{size: int} $params
   */
  public function setUp(array $params): void {
    $this->schema = V::_array();
    $this->input = range(1, $params['size']);

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function benchIndexedList(): void {
    $this->schema->safeParse($this->input);
  }
}
