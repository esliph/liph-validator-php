<?php

namespace Esliph\Validator\Benchmarks;

use Esliph\Validator\Schemas\Schema;
use Esliph\Validator\V;
use PhpBench\Attributes as Bench;

/**
 * Cost of the StringSchema paths touched by block 6: the normalizers (moved from
 * transforms to before the rules), the UTF-8 check in the count rules and the
 * case rule.
 *
 * Every subject gives the same result on the library before and after block 6,
 * except `CountInvalidUtf8`: the old count rules counted the invalid bytes and
 * passed, the new ones fail `min` and `max` (2 issues). That subject has no
 * scenario guard, because no single expectation holds on both versions.
 */
#[Bench\Revs(1000)]
final class StringBench {

  private const ASCII = 'hello world example';
  private const MULTIBYTE = 'ação, coração e informação';
  private const INVALID_UTF8 = "hello \xff world";

  private Schema $schema;
  private mixed $input;

  public function setUpPlain(): void {
    $this->schema = V::string();
    $this->input = self::ASCII;

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpTrimLower(): void {
    $this->schema = V::string()->trim()->toLowerCase();
    $this->input = '  Hello World Example  ';

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpCountAscii(): void {
    $this->schema = V::string()->min(1)->max(100);
    $this->input = self::ASCII;

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpCountMultibyte(): void {
    $this->schema = V::string()->min(1)->max(100);
    $this->input = self::MULTIBYTE;

    Scenario::expectSuccess($this->schema, $this->input);
  }

  public function setUpCountInvalidUtf8(): void {
    $this->schema = V::string()->min(1)->max(100);
    $this->input = self::INVALID_UTF8;
  }

  public function setUpLowercase(): void {
    $this->schema = V::string()->lowercase();
    $this->input = self::ASCII;

    Scenario::expectSuccess($this->schema, $this->input);
  }

  #[Bench\BeforeMethods('setUpPlain')]
  public function benchPlain(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpTrimLower')]
  public function benchTrimLower(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpCountAscii')]
  public function benchCountAscii(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpCountMultibyte')]
  public function benchCountMultibyte(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpCountInvalidUtf8')]
  public function benchCountInvalidUtf8(): void {
    $this->schema->safeParse($this->input);
  }

  #[Bench\BeforeMethods('setUpLowercase')]
  public function benchLowercase(): void {
    $this->schema->safeParse($this->input);
  }
}
