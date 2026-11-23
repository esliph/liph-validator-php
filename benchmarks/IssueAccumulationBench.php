<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\V;
use Generator;
use PhpBench\Attributes as Bench;

/**
 * Cost of collecting issues in composite schemas when many children fail.
 *
 * Each failing child hands its issues to the parent, which appends them to its
 * own list. The `Valid` twins parse the same shape without failures, isolating
 * the accumulation from the per-child parse cost.
 *
 * Sizes grow 10x per step: a time ratio of ~10x between steps is linear,
 * ~100x is quadratic.
 *
 * The union reports a single issue whatever the number of failing members, so
 * its curve measures only the cost of walking the members.
 */
#[Bench\ParamProviders('provideSizes')]
final class IssueAccumulationBench {

  private Schema $schema;
  private mixed $input;

  /**
   * @return Generator<string, array{size: int}>
   */
  public function provideSizes(): Generator {
    yield 'n=10' => ['size' => 10];
    yield 'n=100' => ['size' => 100];
    yield 'n=1000' => ['size' => 1000];
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpArrayInvalid(array $params): void {
    $this->schema = V::_array(V::int());
    $this->input = array_fill(0, $params['size'], 'x');

    Scenario::expectIssues($this->schema, $this->input, $params['size']);
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpArrayValid(array $params): void {
    $this->schema = V::_array(V::int());
    $this->input = range(1, $params['size']);

    Scenario::expectSuccess($this->schema, $this->input);
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpObjectInvalid(array $params): void {
    $this->schema = V::object($this->intShape($params['size']));
    $this->input = (object) array_fill_keys(array_keys($this->intShape($params['size'])), 'x');

    Scenario::expectIssues($this->schema, $this->input, $params['size']);
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpObjectValid(array $params): void {
    $this->schema = V::object($this->intShape($params['size']));
    $this->input = (object) array_fill_keys(array_keys($this->intShape($params['size'])), 1);

    Scenario::expectSuccess($this->schema, $this->input);
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpUnionInvalid(array $params): void {
    $this->schema = V::union(...array_map(static fn(): Schema => V::int(), range(1, $params['size'])));
    $this->input = 'x';

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  #[Bench\BeforeMethods('setUpArrayInvalid')]
  public function benchArrayInvalid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpArrayValid')]
  public function benchArrayValid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpObjectInvalid')]
  public function benchObjectInvalid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpObjectValid')]
  public function benchObjectValid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpUnionInvalid')]
  public function benchUnionInvalid(): void {
    $this->schema->safeParse($this->input);
  }

  /**
   * @return array<string, Schema>
   */
  private function intShape(int $size): array {
    $shape = [];

    for ($i = 0; $i < $size; $i++) {
      $shape["field{$i}"] = V::int();
    }

    return $shape;
  }
}
