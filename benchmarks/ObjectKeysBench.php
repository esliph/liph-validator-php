<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\V;
use Generator;
use PhpBench\Attributes as Bench;

/**
 * Cost of the unknown-key modes and of absent optional fields in `ObjectSchema`.
 *
 * The shape has 5 `int` fields and the input carries them plus `extras` unknown
 * keys. `benchDefault` measures the default mode (passthrough before the strip
 * change, strip after it), so against the baseline it shows what the user sees.
 * `benchPassthrough` copies the unknown keys in both versions, isolating the
 * cost of `get_object_vars` from the change of default.
 * `benchOptionalAbsent` parses an empty object against 5 optional fields, which
 * measures the absence check and the omitted output property.
 */
#[Bench\Revs(1000)]
final class ObjectKeysBench {

  private Schema $schema;
  private mixed $input;

  /**
   * @return Generator<string, array{extras: int}>
   */
  public function provideExtras(): Generator {
    yield 'extras=0' => ['extras' => 0];
    yield 'extras=10' => ['extras' => 10];
    yield 'extras=100' => ['extras' => 100];
  }

  /**
   * @param array{extras: int} $params
   */
  public function setUpDefault(array $params): void {
    $this->schema = V::object($this->shape());
    $this->input = $this->inputWithExtras($params['extras']);

    Scenario::expectSuccess($this->schema, $this->input);
  }

  /**
   * @param array{extras: int} $params
   */
  public function setUpPassthrough(array $params): void {
    $this->schema = V::object($this->shape())->passthrough();
    $this->input = $this->inputWithExtras($params['extras']);

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpOptionalAbsent(): void {
    $shape = [];

    foreach (range(0, 4) as $i) {
      $shape["f{$i}"] = V::int()->optional();
    }

    $this->schema = V::object($shape);
    $this->input = new \stdClass();

    Scenario::expectSuccess($this->schema, $this->input);
  }

  #[Bench\ParamProviders('provideExtras')]
  #[Bench\BeforeMethods('setUpDefault')]
  public function benchDefault(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\ParamProviders('provideExtras')]
  #[Bench\BeforeMethods('setUpPassthrough')]
  public function benchPassthrough(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpOptionalAbsent')]
  public function benchOptionalAbsent(): void {
    $this->schema->safeParse($this->input);
  }

  /**
   * @return array<string, Schema>
   */
  private function shape(): array {
    $shape = [];

    foreach (range(0, 4) as $i) {
      $shape["f{$i}"] = V::int();
    }

    return $shape;
  }

  private function inputWithExtras(int $extras): \stdClass {
    $input = new \stdClass();

    foreach (range(0, 4) as $i) {
      $input->{"f{$i}"} = $i;
    }

    for ($i = 0; $i < $extras; $i++) {
      $input->{"x{$i}"} = $i;
    }

    return $input;
  }
}
