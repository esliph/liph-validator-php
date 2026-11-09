<?php

namespace Esliph\Validator\Tests\Schemas;

use Esliph\Validator\Results\ParseResult;
use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Schemas\Schema;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class BaseCoercibleSchemaTestCase extends BaseSchemaTestCase {

  #[Override]
  protected function createSchema(): Schema {
    return $this->createSchemaCoercible();
  }

  abstract protected function createSchemaCoercible(): CoercibleSchema;

  /** No String, `''` é uma string válida e não um valor ausente. */
  protected function blankCoercesToNull(): bool {
    return true;
  }

  /** @return array<string, array{string}> */
  public static function provideBlankStrings(): array {
    return ['empty' => [''], 'spaces' => ['  '], 'tab_and_newline' => ["\t\n"]];
  }

  protected function assertBlankKept(ParseResult $result, string $blank): void {
    $this->assertTrue($result->success);
    $this->assertSame($blank, $result->data);
  }

  #[DataProvider('provideBlankStrings')]
  public function test_coerce_WithBlankString_ShouldFailRequired(string $blank): void {
    $result = $this->createSchemaCoercible()->coerce()->safeParse($blank);

    if (!$this->blankCoercesToNull()) {
      $this->assertBlankKept($result, $blank);
      return;
    }

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
    $this->assertSame('Value is required', $result->issues[0]->message);
  }

  #[DataProvider('provideBlankStrings')]
  public function test_coerce_WithBlankStringAndOptional_ShouldSucceedWithNull(string $blank): void {
    $result = $this->createSchemaCoercible()->coerce()->optional()->safeParse($blank);

    if (!$this->blankCoercesToNull()) {
      $this->assertBlankKept($result, $blank);
      return;
    }

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  #[DataProvider('provideBlankStrings')]
  public function test_coerce_WithBlankStringAndDefault_ShouldUseDefault(string $blank): void {
    $result = $this->createSchemaCoercible()->coerce()->_default($this->getDefaultValue())->safeParse($blank);

    if (!$this->blankCoercesToNull()) {
      $this->assertBlankKept($result, $blank);
      return;
    }

    $this->assertTrue($result->success);
    $this->assertEquals($this->getDefaultValue(), $result->data);
  }

  #[DataProvider('provideBlankStrings')]
  public function test_coerce_WithBlankStringAndRequiredClosure_ShouldReceiveOriginalBlank(string $blank): void {
    $received = null;
    $schema = $this->createSchemaCoercible()->coerce()->messages([
      'required' => function (mixed $value) use (&$received): string {
        $received = $value;

        return 'custom';
      },
    ]);

    $result = $schema->safeParse($blank);

    if (!$this->blankCoercesToNull()) {
      $this->assertBlankKept($result, $blank);
      return;
    }

    $this->assertFalse($result->success);
    $this->assertSame($blank, $received);
  }

  #[DataProvider('provideBlankStrings')]
  public function test_parse_WithBlankStringWithoutCoerce_ShouldFailInvalidType(string $blank): void {
    $result = $this->createSchemaCoercible()->safeParse($blank);

    if (!$this->blankCoercesToNull()) {
      $this->assertBlankKept($result, $blank);
      return;
    }

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }
}
