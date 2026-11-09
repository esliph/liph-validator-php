<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\V;
use PhpBench\Attributes as Bench;

/**
 * Cost of the `invalid_type` issues a union builds and then discards: every
 * member that rejects the type creates its issue (message, params) before the
 * union moves on to the next member, and none of them reaches the caller.
 *
 * - `ThirdOption`: two members fail on type before the third one passes.
 * - `AllFail`: every member fails on type and the union reports a single
 *   `invalid_union`, so all the member issues are thrown away.
 * - `AllFailWithClosureMessage`: the same with a Closure message on each member,
 *   the path that needs the params and must not get slower.
 */
final class UnionMemberFailureBench {

  private Schema $schema;
  private mixed $input;

  public function setUpThirdOption(): void {
    $this->schema = V::union(V::string(), V::boolean(), V::int());
    $this->input = 5;

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpAllFail(): void {
    $this->schema = V::union(V::string(), V::int());
    $this->input = [];

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  public function setUpAllFailWithClosureMessage(): void {
    $messages = ['invalid_type' => static fn(mixed $value, array $params): string => "custom {$params['received']}"];

    $this->schema = V::union(V::string()->messages($messages), V::int()->messages($messages));
    $this->input = [];

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  #[Bench\BeforeMethods('setUpThirdOption')]
  public function benchThirdOption(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpAllFail')]
  public function benchAllFail(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpAllFailWithClosureMessage')]
  public function benchAllFailWithClosureMessage(): void {
    $this->schema->safeParse($this->input);
  }
}
