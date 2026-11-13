<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\V;
use PhpBench\Attributes as Bench;

/**
 * Cost of `coerce()` on a single value: the numeric string formats of Int, Float
 * and Number, the Bool vocabulary and the JSON decoding of Array and Object.
 *
 * Every subject gives the same result (success, or one issue) on the library
 * before the coercion pipeline and after it, except `ObjectJson`: the old
 * Object had no JSON coercion, so its scenario only holds on the new code.
 */
#[Bench\Revs(1000)]
final class CoercionBench {

  private Schema $schema;
  private mixed $input;

  public function setUpIntValid(): void {
    $this->schema = V::int()->coerce();
    $this->input = ' 12345 ';

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpIntInvalid(): void {
    $this->schema = V::int()->coerce();
    $this->input = 'abc';

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  public function setUpIntNegative(): void {
    $this->schema = V::int()->coerce();
    $this->input = '-42';

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpIntGarbage(): void {
    $this->schema = V::int()->coerce();
    $this->input = '12abc';

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  public function setUpFloatValid(): void {
    $this->schema = V::float()->coerce();
    $this->input = '3.14159';

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpFloatInvalid(): void {
    $this->schema = V::float()->coerce();
    $this->input = 'abc';

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  public function setUpFloatGarbage(): void {
    $this->schema = V::float()->coerce();
    $this->input = '12abc';

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  public function setUpNumberValid(): void {
    $this->schema = V::number()->coerce();
    $this->input = '1e3';

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpNumberInvalid(): void {
    $this->schema = V::number()->coerce();
    $this->input = 'abc';

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  public function setUpBoolValid(): void {
    $this->schema = V::boolean()->coerce();
    $this->input = ' YES ';

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpBoolInvalid(): void {
    $this->schema = V::boolean()->coerce();
    $this->input = 'maybe';

    Scenario::expectIssues($this->schema, $this->input, 1);
  }

  public function setUpArrayJson(): void {
    $this->schema = V::_array(V::int())->coerce();
    $this->input = json_encode(range(1, 20));

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpObjectJson(): void {
    $this->schema = V::object(['a' => V::int(), 'b' => V::string()])->coerce();
    $this->input = '{"a":1,"b":"x"}';

    Scenario::expectSuccess($this->schema, $this->input);
  }

  #[Bench\BeforeMethods('setUpIntValid')]
  public function benchIntValid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpIntInvalid')]
  public function benchIntInvalid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpIntNegative')]
  public function benchIntNegative(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpIntGarbage')]
  public function benchIntGarbage(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpFloatValid')]
  public function benchFloatValid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpFloatInvalid')]
  public function benchFloatInvalid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpFloatGarbage')]
  public function benchFloatGarbage(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpNumberValid')]
  public function benchNumberValid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpNumberInvalid')]
  public function benchNumberInvalid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpBoolValid')]
  public function benchBoolValid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpBoolInvalid')]
  public function benchBoolInvalid(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpArrayJson')]
  public function benchArrayJson(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpObjectJson')]
  public function benchObjectJson(): void {
    $this->schema->safeParse($this->input);
  }
}
