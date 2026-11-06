<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\Tests\Fixtures\IntBacked;
use Esliph\Validator\V;
use Generator;
use PhpBench\Attributes as Bench;

/**
 * Cost of the `EnumSchema` membership check: strict `in_array` over the list
 * (O(n), no allocation) against `tryFrom` on a native backed enum (a hash lookup
 * done by the engine).
 *
 * The list subject looks up the last value, the worst case. Sizes grow 10x per
 * step: a ~10x ratio between steps means linear, ~1x means constant. Compare
 * `benchListLastValue` n=50 against `benchBackedEnum` to judge whether a hash set
 * would pay off.
 */
final class EnumBench {

  private Schema $schema;
  private mixed $input;

  /**
   * @return Generator<string, array{size: int}>
   */
  public function provideSizes(): Generator {
    yield 'n=5' => ['size' => 5];
    yield 'n=50' => ['size' => 50];
    yield 'n=500' => ['size' => 500];
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpList(array $params): void {
    $values = array_map(
      static fn(int $i): string => "v{$i}",
      range(0, $params['size'] - 1)
    );

    $this->schema = V::enum($values);
    $this->input = $values[$params['size'] - 1];

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpBackedEnum(): void {
    $this->schema = V::enum(IntBacked::class);
    $this->input = 42;

    Scenario::expectSuccess($this->schema, $this->input);
  }

  #[Bench\ParamProviders('provideSizes')]
  #[Bench\BeforeMethods('setUpList')]
  public function benchListLastValue(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpBackedEnum')]
  public function benchBackedEnum(): void {
    $this->schema->safeParse($this->input);
  }
}
