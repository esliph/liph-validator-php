<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\V;
use Generator;
use PhpBench\Attributes as Bench;

/**
 * Cost of the default message of a rule that fails, alone and once per element
 * of an array.
 *
 * The `*Closure` twins give the same message as a user Closure, which must not
 * get slower whatever happens to the default message.
 */
#[Bench\Revs(1000)]
final class RuleFailureBench {

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

  public function setUpSingle(): void {
    $this->schema = V::string()->min(5);
    $this->input = 'ab';

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  public function setUpSingleClosure(): void {
    $this->schema = V::string()->min(5, static fn(string $value, array $params): string => "Must be at least {$params['length']} characters");
    $this->input = 'ab';

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpArray(array $params): void {
    $this->schema = V::_array(V::string()->min(5));
    $this->input = array_fill(0, $params['size'], 'ab');

    Scenario::expectIssues($this->schema, $this->input, $params['size']);
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpArrayClosure(array $params): void {
    $this->schema = V::_array(V::string()->min(5, static fn(string $value, array $params): string => "Must be at least {$params['length']} characters"));
    $this->input = array_fill(0, $params['size'], 'ab');

    Scenario::expectIssues($this->schema, $this->input, $params['size']);
  }

  #[Bench\BeforeMethods('setUpSingle')]
  public function benchSingle(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpSingleClosure')]
  public function benchSingleClosure(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpArray')]
  #[Bench\ParamProviders('provideSizes')]
  public function benchArray(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpArrayClosure')]
  #[Bench\ParamProviders('provideSizes')]
  public function benchArrayClosure(): void {
    $this->schema->safeParse($this->input);
  }
}
