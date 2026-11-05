<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\V;
use Generator;
use PhpBench\Attributes as Bench;

/**
 * End-to-end parse of a realistic payload: a list of users with nested objects,
 * arrays, unions and rules. Not tied to a single cost — it is the regression
 * guard for changes that do not have a dedicated benchmark.
 *
 * `Invalid` breaks every user in a few places, exercising the failure path at
 * every level of nesting.
 */
#[Bench\ParamProviders('provideSizes')]
final class PayloadBench {

  private Schema $schema;
  /** @var list<object> */
  private array $input;

  /**
   * @return Generator<string, array{size: int}>
   */
  public function provideSizes(): Generator {
    yield 'users=1' => ['size' => 1];
    yield 'users=100' => ['size' => 100];
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpValid(array $params): void {
    $this->schema = $this->usersSchema();
    $this->input = array_map(fn(int $i): object => $this->user($i, valid: true), range(1, $params['size']));

    Scenario::expectSuccess($this->schema, $this->input);
  }

  /**
   * @param array{size: int} $params
   */
  public function setUpInvalid(array $params): void {
    $this->schema = $this->usersSchema();
    $this->input = array_map(fn(int $i): object => $this->user($i, valid: false), range(1, $params['size']));

    Scenario::expectIssues($this->schema, $this->input, 4 * $params['size']);
  }

  #[Bench\BeforeMethods('setUpValid')]
  public function benchValid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpInvalid')]
  public function benchInvalid(): void {
    $this->schema->safeParse($this->input);
  }

  private function usersSchema(): Schema {
    $address = V::object([
      'street' => V::string()->min(1)->max(120),
      'city' => V::string()->min(1),
      'zip' => V::string()->regex('/^\d{5}-?\d{3}$/'),
    ]);

    $user = V::object([
      'id' => V::union(V::int()->positive(), V::string()->min(1)),
      'name' => V::string()->min(2)->max(80)->trim(),
      'email' => V::string()->email(),
      'age' => V::int()->between(0, 150)->optional(),
      'active' => V::boolean(),
      'tags' => V::_array(V::string()->min(1))->max(10),
      'address' => $address,
    ])->strict();

    return V::_array($user);
  }

  /**
   * Invalid users fail in 4 places: `email`, `age`, one element of `tags` and
   * `address.zip`.
   */
  private function user(int $i, bool $valid): object {
    return (object) [
      'id' => $i,
      'name' => "User {$i}",
      'email' => $valid ? "user{$i}@example.com" : "user{$i}",
      'age' => $valid ? 30 : 200,
      'active' => true,
      'tags' => $valid ? ['admin', 'staff'] : ['admin', ''],
      'address' => (object) [
        'street' => 'Rua A, 100',
        'city' => 'Curitiba',
        'zip' => $valid ? '80000-000' : 'zip',
      ],
    ];
  }
}
