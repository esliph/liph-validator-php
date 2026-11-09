<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\Schemas\Primitive\StringSchema;
use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Tests\Schemas\BaseCoercibleSchemaTestCase;

use Closure;
use InvalidArgumentException;
use Override;

class StringSchemaTest extends BaseCoercibleSchemaTestCase {

  #[Override]
  protected function createSchemaCoercible(): CoercibleSchema {
    return new StringSchema();
  }

  #[Override]
  protected function blankCoercesToNull(): bool {
    return false;
  }

  #[Override]
  protected function getValidValue(): mixed {
    return 'valid string';
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return 12345;
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return 'default value';
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(mixed $value) => $value;
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value): bool => strlen($value) > 5;
  }

  public function test_parseType_WithValidString_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse('hello world');

    $this->assertTrue($result->success);
    $this->assertSame('hello world', $result->data);
    $this->assertIsString($result->data);
  }

  public function test_parseType_WithEmptyString_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse('');

    $this->assertTrue($result->success);
    $this->assertSame('', $result->data);
  }

  public function test_parseType_WithNumericString_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse('12345');

    $this->assertTrue($result->success);
    $this->assertSame('12345', $result->data);
  }

  public function test_parseType_WithSpecialCharacters_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse('!@#$%^&*()_+-=[]{}|;:,.<>?');

    $this->assertTrue($result->success);
    $this->assertStringContainsString('!@#$%', $result->data);
  }

  public function test_parseType_WithUnicode_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse('Hello 世界 مرحبا мир');

    $this->assertTrue($result->success);
    $this->assertStringContainsString('世界', $result->data);
  }

  public function test_parseType_WithNewlines_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse("line1\nline2\nline3");

    $this->assertTrue($result->success);
    $this->assertStringContainsString("\n", $result->data);
  }

  public function test_parseType_WithTabs_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse("col1\tcol2\tcol3");

    $this->assertTrue($result->success);
    $this->assertStringContainsString("\t", $result->data);
  }

  public function test_parseType_WithInteger_ShouldReject(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse(42);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithFloat_ShouldReject(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse(3.14);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithBoolean_ShouldReject(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse(true);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithArray_ShouldReject(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse(['string']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithObject_ShouldReject(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_parseType_WithCoerceAndInteger_ShouldCoerce(): void {
    $schema = (new StringSchema())->coerce();

    $result = $schema->safeParse(42);

    $this->assertTrue($result->success);
    $this->assertSame('42', $result->data);
    $this->assertIsString($result->data);
  }

  public function test_parseType_WithCoerceAndFloat_ShouldCoerce(): void {
    $schema = (new StringSchema())->coerce();

    $result = $schema->safeParse(3.14);

    $this->assertTrue($result->success);
    $this->assertSame('3.14', $result->data);
  }

  public function test_parseType_WithCoerceAndBoolean_ShouldCoerce(): void {
    $schema = (new StringSchema())->coerce();

    $result = $schema->safeParse(true);

    $this->assertTrue($result->success);
    $this->assertSame('1', $result->data);
  }

  public function test_parseType_WithCoerceAndArray_ShouldFail(): void {
    $schema = (new StringSchema())->coerce();

    $result = $schema->safeParse(['string']);

    $this->assertFalse($result->success);
  }

  public function test_min_WithStringLongerThanMin_ShouldPass(): void {
    $schema = (new StringSchema())->min(5);

    $result = $schema->safeParse('hello world');

    $this->assertTrue($result->success);
  }

  public function test_min_WithStringEqualToMin_ShouldPass(): void {
    $schema = (new StringSchema())->min(5);

    $result = $schema->safeParse('hello');

    $this->assertTrue($result->success);
    $this->assertSame('hello', $result->data);
  }

  public function test_min_WithStringShorterThanMin_ShouldFail(): void {
    $schema = (new StringSchema())->min(5);

    $result = $schema->safeParse('hi');

    $this->assertFalse($result->success);
    $this->assertSame('too_small', $result->issues[0]->code);
  }

  public function test_min_WithZero_ShouldAcceptAny(): void {
    $schema = (new StringSchema())->min(0);

    $this->assertTrue($schema->safeParse('')->success);
    $this->assertTrue($schema->safeParse('a')->success);
  }

  public function test_min_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'String must be at least 10 characters';
    $schema = (new StringSchema())->min(10, $customMessage);

    $result = $schema->safeParse('short');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_min_WithClosureMessage_ShouldUseDynamicMessage(): void {
    $schema = (new StringSchema())->min(10, function (string $value, array $params): string {
      return "String '$value' must be at least {$params['length']} characters";
    });

    $result = $schema->safeParse('hi');

    $this->assertFalse($result->success);
    $this->assertStringContainsString("String 'hi'", $result->issues[0]->message);
  }

  public function test_min_WithUnicode_ShouldUseMbStrlen(): void {
    $schema = (new StringSchema())->length(3);

    $result = $schema->safeParse('日本語');

    $this->assertTrue($result->success);
  }

  public function test_max_WithStringShorterThanMax_ShouldPass(): void {
    $schema = (new StringSchema())->max(10);

    $result = $schema->safeParse('hello');

    $this->assertTrue($result->success);
  }

  public function test_max_WithStringEqualToMax_ShouldPass(): void {
    $schema = (new StringSchema())->max(5);

    $result = $schema->safeParse('hello');

    $this->assertTrue($result->success);
  }

  public function test_max_WithStringLongerThanMax_ShouldFail(): void {
    $schema = (new StringSchema())->max(5);

    $result = $schema->safeParse('hello world');

    $this->assertFalse($result->success);
    $this->assertSame('too_big', $result->issues[0]->code);
  }

  public function test_max_WithEmptyString_ShouldPass(): void {
    $schema = (new StringSchema())->max(100);

    $result = $schema->safeParse('');

    $this->assertTrue($result->success);
  }

  public function test_max_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'String must be at most 5 characters';
    $schema = (new StringSchema())->max(5, $customMessage);

    $result = $schema->safeParse('toolong');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_max_WithUnicode_ShouldUseMbStrlen(): void {
    $schema = (new StringSchema())->max(2);

    $result = $schema->safeParse('日本');

    $this->assertTrue($result->success);
  }

  public function test_email_WithValidEmail_ShouldPass(): void {
    $schema = (new StringSchema())->email();

    $validEmails = [
      'user@example.com',
      'test.email@domain.co.uk',
      'user+tag@example.com',
      'first.last@example.com',
    ];

    foreach ($validEmails as $email) {
      $result = $schema->safeParse($email);
      $this->assertTrue($result->success, "Email '$email' should be valid");
    }
  }

  public function test_email_WithInvalidEmail_ShouldFail(): void {
    $schema = (new StringSchema())->email();

    $invalidEmails = [
      'notanemail',
      'user@',
      '@example.com',
      'user @example.com',
      'user@example',
    ];

    foreach ($invalidEmails as $email) {
      $result = $schema->safeParse($email);
      $this->assertFalse($result->success, "Email '$email' should be invalid");
      $this->assertSame('invalid_format', $result->issues[0]->code);
    }
  }

  public function test_email_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Please provide a valid email address';
    $schema = (new StringSchema())->email($customMessage);

    $result = $schema->safeParse('invalid-email');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_url_WithValidUrl_ShouldPass(): void {
    $schema = (new StringSchema())->url();

    $validUrls = [
      'http://example.com',
      'https://example.com',
      'https://example.com/path',
      'https://example.com/path?query=value',
      'https://example.com:8080/path',
    ];

    foreach ($validUrls as $url) {
      $result = $schema->safeParse($url);
      $this->assertTrue($result->success, "URL '$url' should be valid");
    }
  }

  public function test_url_WithInvalidUrl_ShouldFail(): void {
    $schema = (new StringSchema())->url();

    $invalidUrls = [
      'not a url',
      'example.com',
      'http://',
      '://example.com',
    ];

    foreach ($invalidUrls as $url) {
      $result = $schema->safeParse($url);
      $this->assertFalse($result->success, "URL '$url' should be invalid");
      $this->assertSame('invalid_format', $result->issues[0]->code);
    }
  }

  public function test_url_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Please provide a valid URL';
    $schema = (new StringSchema())->url($customMessage);

    $result = $schema->safeParse('invalid-url');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_regex_WithMatchingPattern_ShouldPass(): void {
    $schema = (new StringSchema())->regex('/^[a-z]+$/');

    $result = $schema->safeParse('abc');

    $this->assertTrue($result->success);
  }

  public function test_regex_WithNonMatchingPattern_ShouldFail(): void {
    $schema = (new StringSchema())->regex('/^[a-z]+$/');

    $result = $schema->safeParse('ABC123');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_format', $result->issues[0]->code);
  }

  public function test_regex_WithNumericPattern_ShouldWork(): void {
    $schema = (new StringSchema())->regex('/^\d{3}-\d{3}-\d{4}$/');

    $this->assertTrue($schema->safeParse('123-456-7890')->success);
    $this->assertFalse($schema->safeParse('123-45-6789')->success);
  }

  public function test_regex_WithPhonePattern_ShouldWork(): void {
    $schema = (new StringSchema())->regex('/^(\+\d{1,3})?\s?\d{1,4}[-.\s]?\d{1,4}[-.\s]?\d{1,9}$/');

    $this->assertTrue($schema->safeParse('+1 555-123-4567')->success);
    $this->assertTrue($schema->safeParse('555.123.4567')->success);
  }

  public function test_regex_WithCaseSensitive_ShouldRespectCase(): void {
    $schema = (new StringSchema())->regex('/^[A-Z]+$/');

    $this->assertTrue($schema->safeParse('HELLO')->success);
    $this->assertFalse($schema->safeParse('hello')->success);
  }

  public function test_regex_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Must contain only lowercase letters';
    $schema = (new StringSchema())->regex('/^[a-z]+$/', $customMessage);

    $result = $schema->safeParse('ABC');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_nonempty_WithNonEmptyString_ShouldPass(): void {
    $schema = (new StringSchema())->nonempty();

    $result = $schema->safeParse('text');

    $this->assertTrue($result->success);
  }

  public function test_nonempty_WithEmptyString_ShouldFail(): void {
    $schema = (new StringSchema())->nonempty();

    $result = $schema->safeParse('');

    $this->assertFalse($result->success);
    $this->assertSame('too_small', $result->issues[0]->code);
  }

  public function test_nonempty_WithWhitespaceOnly_ShouldPass(): void {
    $schema = (new StringSchema())->nonempty();

    $result = $schema->safeParse('   ');

    $this->assertTrue($result->success);
  }

  public function test_nonempty_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'This field cannot be empty';
    $schema = (new StringSchema())->nonempty($customMessage);

    $result = $schema->safeParse('');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_startsWith_WithMatchingPrefix_ShouldPass(): void {
    $schema = (new StringSchema())->startsWith('Hello');

    $result = $schema->safeParse('Hello World');

    $this->assertTrue($result->success);
  }

  public function test_startsWith_WithNonMatchingPrefix_ShouldFail(): void {
    $schema = (new StringSchema())->startsWith('Hello');

    $result = $schema->safeParse('Hi World');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_format', $result->issues[0]->code);
  }

  public function test_startsWith_WithExactMatch_ShouldPass(): void {
    $schema = (new StringSchema())->startsWith('test');

    $result = $schema->safeParse('test');

    $this->assertTrue($result->success);
  }

  public function test_startsWith_WithEmptyPrefix_ShouldPass(): void {
    $schema = (new StringSchema())->startsWith('');

    $result = $schema->safeParse('anything');

    $this->assertTrue($result->success);
  }

  public function test_startsWith_WithCaseSensitive_ShouldRespectCase(): void {
    $schema = (new StringSchema())->startsWith('Hello');

    $this->assertTrue($schema->safeParse('Hello World')->success);
    $this->assertFalse($schema->safeParse('hello World')->success);
  }

  public function test_startsWith_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Must start with "Dr"';
    $schema = (new StringSchema())->startsWith('Dr', $customMessage);

    $result = $schema->safeParse('Mr Smith');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_startsWith_WithDynamicMessage_ShouldBuildMessage(): void {
    $schema = (new StringSchema())->startsWith('prefix', function (string $value, array $params): string {
      return "String must start with '{$params['prefix']}'";
    });

    $result = $schema->safeParse('other');

    $this->assertFalse($result->success);
    $this->assertStringContainsString("'prefix'", $result->issues[0]->message);
  }

  public function test_endsWith_WithMatchingSuffix_ShouldPass(): void {
    $schema = (new StringSchema())->endsWith('World');

    $result = $schema->safeParse('Hello World');

    $this->assertTrue($result->success);
  }

  public function test_endsWith_WithNonMatchingSuffix_ShouldFail(): void {
    $schema = (new StringSchema())->endsWith('World');

    $result = $schema->safeParse('Hello Earth');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_format', $result->issues[0]->code);
  }

  public function test_endsWith_WithExactMatch_ShouldPass(): void {
    $schema = (new StringSchema())->endsWith('test');

    $result = $schema->safeParse('test');

    $this->assertTrue($result->success);
  }

  public function test_endsWith_WithEmptySuffix_ShouldPass(): void {
    $schema = (new StringSchema())->endsWith('');

    $result = $schema->safeParse('anything');

    $this->assertTrue($result->success);
  }

  public function test_endsWith_WithCaseSensitive_ShouldRespectCase(): void {
    $schema = (new StringSchema())->endsWith('World');

    $this->assertTrue($schema->safeParse('Hello World')->success);
    $this->assertFalse($schema->safeParse('Hello world')->success);
  }

  public function test_endsWith_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Must end with ".txt"';
    $schema = (new StringSchema())->endsWith('.txt', $customMessage);

    $result = $schema->safeParse('document.pdf');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_endsWith_WithDynamicMessage_ShouldBuildMessage(): void {
    $schema = (new StringSchema())->endsWith('suffix', function (string $value, array $params): string {
      return "String must end with '{$params['suffix']}'";
    });

    $result = $schema->safeParse('prefix');

    $this->assertFalse($result->success);
    $this->assertStringContainsString("'suffix'", $result->issues[0]->message);
  }

  public function test_includes_WithSubstringPresent_ShouldPass(): void {
    $schema = (new StringSchema())->includes('World');

    $result = $schema->safeParse('Hello World');

    $this->assertTrue($result->success);
  }

  public function test_includes_WithSubstringAbsent_ShouldFail(): void {
    $schema = (new StringSchema())->includes('World');

    $result = $schema->safeParse('Hello Earth');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_format', $result->issues[0]->code);
  }

  public function test_includes_WithSingleCharacter_ShouldWork(): void {
    $schema = (new StringSchema())->includes('o');

    $this->assertTrue($schema->safeParse('Hello')->success);
    $this->assertFalse($schema->safeParse('Hi')->success);
  }

  public function test_includes_WithEmptySubstring_ShouldPass(): void {
    $schema = (new StringSchema())->includes('');

    $result = $schema->safeParse('anything');

    $this->assertTrue($result->success);
  }

  public function test_includes_WithCaseSensitive_ShouldRespectCase(): void {
    $schema = (new StringSchema())->includes('World');

    $this->assertTrue($schema->safeParse('Hello World')->success);
    $this->assertFalse($schema->safeParse('Hello world')->success);
  }

  public function test_includes_WithSubstringMultipleTimes_ShouldPass(): void {
    $schema = (new StringSchema())->includes('o');

    $result = $schema->safeParse('Hello World');

    $this->assertTrue($result->success);
  }

  public function test_includes_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'Must contain "admin"';
    $schema = (new StringSchema())->includes('admin', $customMessage);

    $result = $schema->safeParse('user');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_includes_WithDynamicMessage_ShouldBuildMessage(): void {
    $schema = (new StringSchema())->includes('substring', function (string $value, array $params): string {
      return "String must include '{$params['substring']}'";
    });

    $result = $schema->safeParse('other');

    $this->assertFalse($result->success);
    $this->assertStringContainsString("'substring'", $result->issues[0]->message);
  }

  public function test_length_WithExactLength_ShouldPass(): void {
    $schema = (new StringSchema())->length(5);

    $result = $schema->safeParse('hello');

    $this->assertTrue($result->success);
  }

  public function test_length_WithDifferentLength_ShouldFail(): void {
    $schema = (new StringSchema())->length(5);

    $result = $schema->safeParse('hi');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_length', $result->issues[0]->code);
  }

  public function test_length_WithZero_ShouldOnlyAcceptEmpty(): void {
    $schema = (new StringSchema())->length(0);

    $this->assertTrue($schema->safeParse('')->success);
    $this->assertFalse($schema->safeParse('a')->success);
  }

  public function test_length_WithUnicode_ShouldUseMbStrlen(): void {
    $schema = (new StringSchema())->length(3);

    $result = $schema->safeParse('日本語');

    $this->assertTrue($result->success);
  }

  public function test_length_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'String must be exactly 5 characters';
    $schema = (new StringSchema())->length(5, $customMessage);

    $result = $schema->safeParse('hello world');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_length_WithDynamicMessage_ShouldBuildMessage(): void {
    $schema = (new StringSchema())->length(5, function (string $value, array $params): string {
      return "String must be exactly {$params['length']} characters";
    });

    $result = $schema->safeParse('hi');

    $this->assertFalse($result->success);
    $this->assertStringContainsString('exactly 5', $result->issues[0]->message);
  }

  public function test_lowercase_WithLowercaseString_ShouldPass(): void {
    $schema = (new StringSchema())->lowercase();

    $result = $schema->safeParse('hello world');

    $this->assertTrue($result->success);
  }

  public function test_lowercase_WithUppercaseString_ShouldFail(): void {
    $schema = (new StringSchema())->lowercase();

    $result = $schema->safeParse('Hello World');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_case', $result->issues[0]->code);
  }

  public function test_lowercase_WithMixedCase_ShouldFail(): void {
    $schema = (new StringSchema())->lowercase();

    $result = $schema->safeParse('hElLo');

    $this->assertFalse($result->success);
  }

  public function test_lowercase_WithNumbers_ShouldPass(): void {
    $schema = (new StringSchema())->lowercase();

    $result = $schema->safeParse('hello123world');

    $this->assertTrue($result->success);
  }

  public function test_lowercase_WithSpecialCharacters_ShouldPass(): void {
    $schema = (new StringSchema())->lowercase();

    $result = $schema->safeParse('hello!@#$world');

    $this->assertTrue($result->success);
  }

  public function test_lowercase_WithUnicode_ShouldWork(): void {
    $schema = (new StringSchema())->lowercase();

    $result = $schema->safeParse('café');

    $this->assertTrue($result->success);
  }

  public function test_lowercase_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'String must be all lowercase';
    $schema = (new StringSchema())->lowercase($customMessage);

    $result = $schema->safeParse('Hello');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_uppercase_WithUppercaseString_ShouldPass(): void {
    $schema = (new StringSchema())->uppercase();

    $result = $schema->safeParse('HELLO WORLD');

    $this->assertTrue($result->success);
  }

  public function test_uppercase_WithLowercaseString_ShouldFail(): void {
    $schema = (new StringSchema())->uppercase();

    $result = $schema->safeParse('hello world');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_case', $result->issues[0]->code);
  }

  public function test_uppercase_WithMixedCase_ShouldFail(): void {
    $schema = (new StringSchema())->uppercase();

    $result = $schema->safeParse('HeLLo');

    $this->assertFalse($result->success);
  }

  public function test_uppercase_WithNumbers_ShouldPass(): void {
    $schema = (new StringSchema())->uppercase();

    $result = $schema->safeParse('HELLO123WORLD');

    $this->assertTrue($result->success);
  }

  public function test_uppercase_WithSpecialCharacters_ShouldPass(): void {
    $schema = (new StringSchema())->uppercase();

    $result = $schema->safeParse('HELLO!@#$WORLD');

    $this->assertTrue($result->success);
  }

  public function test_uppercase_WithCustomMessage_ShouldUseCustomMessage(): void {
    $customMessage = 'String must be all uppercase';
    $schema = (new StringSchema())->uppercase($customMessage);

    $result = $schema->safeParse('hello');

    $this->assertFalse($result->success);
    $this->assertSame($customMessage, $result->issues[0]->message);
  }

  public function test_trim_WithLeadingWhitespace_ShouldRemove(): void {
    $schema = (new StringSchema())->trim();

    $result = $schema->safeParse('  hello');

    $this->assertTrue($result->success);
    $this->assertSame('hello', $result->data);
  }

  public function test_trim_WithTrailingWhitespace_ShouldRemove(): void {
    $schema = (new StringSchema())->trim();

    $result = $schema->safeParse('hello  ');

    $this->assertTrue($result->success);
    $this->assertSame('hello', $result->data);
  }

  public function test_trim_WithBothSides_ShouldRemove(): void {
    $schema = (new StringSchema())->trim();

    $result = $schema->safeParse('  hello world  ');

    $this->assertTrue($result->success);
    $this->assertSame('hello world', $result->data);
  }

  public function test_trim_WithTabs_ShouldRemove(): void {
    $schema = (new StringSchema())->trim();

    $result = $schema->safeParse("\t\thello\t\t");

    $this->assertTrue($result->success);
    $this->assertSame('hello', $result->data);
  }

  public function test_trim_WithNewlines_ShouldRemove(): void {
    $schema = (new StringSchema())->trim();

    $result = $schema->safeParse("\n\nhello\n\n");

    $this->assertTrue($result->success);
    $this->assertSame('hello', $result->data);
  }

  public function test_trim_WithInternalWhitespace_ShouldKeep(): void {
    $schema = (new StringSchema())->trim();

    $result = $schema->safeParse('  hello world  ');

    $this->assertTrue($result->success);
    $this->assertStringContainsString(' ', $result->data);
  }

  public function test_toLowerCase_WithUppercaseString_ShouldConvert(): void {
    $schema = (new StringSchema())->toLowerCase();

    $result = $schema->safeParse('HELLO WORLD');

    $this->assertTrue($result->success);
    $this->assertSame('hello world', $result->data);
  }

  public function test_toLowerCase_WithMixedCase_ShouldConvert(): void {
    $schema = (new StringSchema())->toLowerCase();

    $result = $schema->safeParse('HeLLo WoRLd');

    $this->assertTrue($result->success);
    $this->assertSame('hello world', $result->data);
  }

  public function test_toLowerCase_WithUnicode_ShouldConvert(): void {
    $schema = (new StringSchema())->toLowerCase();

    $result = $schema->safeParse('CAFÉ');

    $this->assertTrue($result->success);
    $this->assertSame('café', $result->data);
  }

  public function test_toLowerCase_WithNumbers_ShouldKeep(): void {
    $schema = (new StringSchema())->toLowerCase();

    $result = $schema->safeParse('HELLO123');

    $this->assertTrue($result->success);
    $this->assertSame('hello123', $result->data);
  }

  public function test_toUpperCase_WithLowercaseString_ShouldConvert(): void {
    $schema = (new StringSchema())->toUpperCase();

    $result = $schema->safeParse('hello world');

    $this->assertTrue($result->success);
    $this->assertSame('HELLO WORLD', $result->data);
  }

  public function test_toUpperCase_WithMixedCase_ShouldConvert(): void {
    $schema = (new StringSchema())->toUpperCase();

    $result = $schema->safeParse('HeLLo WoRLd');

    $this->assertTrue($result->success);
    $this->assertSame('HELLO WORLD', $result->data);
  }

  public function test_toUpperCase_WithUnicode_ShouldConvert(): void {
    $schema = (new StringSchema())->toUpperCase();

    $result = $schema->safeParse('café');

    $this->assertTrue($result->success);
    $this->assertSame('CAFÉ', $result->data);
  }

  public function test_toUpperCase_WithNumbers_ShouldKeep(): void {
    $schema = (new StringSchema())->toUpperCase();

    $result = $schema->safeParse('hello123');

    $this->assertTrue($result->success);
    $this->assertSame('HELLO123', $result->data);
  }

  public function test_combined_MinAndMax_ShouldEnforceBounds(): void {
    $schema = (new StringSchema())
      ->min(5)
      ->max(10);

    $this->assertTrue($schema->safeParse('hello')->success);
    $this->assertTrue($schema->safeParse('hi world')->success);
    $this->assertFalse($schema->safeParse('hi')->success);
    $this->assertFalse($schema->safeParse('this is a very long string')->success);
  }

  public function test_combined_StartsWithAndEndsWith_ShouldEnforceBoth(): void {
    $schema = (new StringSchema())
      ->startsWith('Mr')
      ->endsWith('Jr');

    $this->assertTrue($schema->safeParse('Mr Smith Jr')->success);
    $this->assertFalse($schema->safeParse('Dr Smith Jr')->success);
    $this->assertFalse($schema->safeParse('Mr Smith Sr')->success);
  }

  public function test_combined_IncludesAndLength_ShouldEnforceBoth(): void {
    $schema = (new StringSchema())
      ->includes('@')
      ->length(20);

    $this->assertTrue($schema->safeParse('user@example.com1234')->success);
    $this->assertFalse($schema->safeParse('user@example.com')->success);
    $this->assertFalse($schema->safeParse('no-at-sign1234567890')->success);
  }

  public function test_combined_LowercaseAndIncludesNumber_ShouldEnforceBoth(): void {
    $schema = (new StringSchema())
      ->lowercase()
      ->regex('/[0-9]/');

    $this->assertTrue($schema->safeParse('hello123')->success);
    $this->assertTrue($schema->safeParse('password1')->success);
    $this->assertFalse($schema->safeParse('HELLO123')->success);
    $this->assertFalse($schema->safeParse('hello')->success);
  }

  public function test_combined_TrimAndMin_ShouldTrimThenValidate(): void {
    $schema = (new StringSchema())
      ->trim()
      ->min(5);

    $result = $schema->safeParse('  hello  ');

    $this->assertTrue($result->success);
    $this->assertSame('hello', $result->data);
    $this->assertSame(5, mb_strlen($result->data));
  }

  public function test_combined_ToLowercaseAndLength_ShouldConvertThenValidate(): void {
    $schema = (new StringSchema())
      ->toLowerCase()
      ->length(5);

    $result = $schema->safeParse('HELLO');

    $this->assertTrue($result->success);
    $this->assertSame('hello', $result->data);
  }

  public function test_combined_AllValidationRules_ExtremeCase(): void {
    $schema = (new StringSchema())
      ->min(10)
      ->max(50)
      ->startsWith('user')
      ->endsWith('.com')
      ->lowercase()
      ->regex('/[a-z0-9._-]+/');

    $this->assertTrue($schema->safeParse('user.name@example.com')->success);
    $this->assertFalse($schema->safeParse('USER.NAME@EXAMPLE.COM')->success);
    $this->assertFalse($schema->safeParse('user.name@example')->success);
    $this->assertFalse($schema->safeParse('admin.name@example.com')->success);
  }

  public function test_combined_EmailAndMaxLength_ShouldEnforceBoth(): void {
    $schema = (new StringSchema())
      ->email()
      ->max(20);

    $this->assertTrue($schema->safeParse('user@test.com')->success);
    $this->assertFalse($schema->safeParse('verylongemailaddress@verylongdomain.com')->success);
  }

  public function test_combined_UrlAndMin_ShouldEnforceBoth(): void {
    $schema = (new StringSchema())
      ->url()
      ->min(10);

    $this->assertTrue($schema->safeParse('http://example.com')->success);
    $this->assertFalse($schema->safeParse('http://ex')->success);
  }

  public function test_combined_TransformsAndValidation_ShouldApplyInOrder(): void {
    $schema = (new StringSchema())
      ->trim()
      ->toLowerCase()
      ->min(5);

    $result = $schema->safeParse('  HELLO WORLD  ');

    $this->assertTrue($result->success);
    $this->assertSame('hello world', $result->data);
  }

  public function test_combined_MultipleTransforms_ShouldChain(): void {
    $schema = (new StringSchema())
      ->trim()
      ->toLowerCase()
      ->toUpperCase();

    $result = $schema->safeParse('  hello world  ');

    $this->assertTrue($result->success);
    $this->assertSame('HELLO WORLD', $result->data);
  }

  public function test_edgecase_VeryLongString_ShouldHandle(): void {
    $schema = new StringSchema();
    $longString = str_repeat('a', 10000);

    $result = $schema->safeParse($longString);

    $this->assertTrue($result->success);
    $this->assertSame(10000, mb_strlen($result->data));
  }

  public function test_edgecase_StringWithOnlyWhitespace_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse('     ');

    $this->assertTrue($result->success);
  }

  public function test_edgecase_StringWithMixedUnicodeAndASCII_ShouldHandle(): void {
    $schema = (new StringSchema())->min(10);

    $result = $schema->safeParse('hello世界world');

    $this->assertTrue($result->success);
  }

  public function test_edgecase_EmptyStringWithNonempty_ShouldFail(): void {
    $schema = (new StringSchema())->nonempty();

    $result = $schema->safeParse('');

    $this->assertFalse($result->success);
  }

  public function test_edgecase_RegexWithSpecialCharacters_ShouldWork(): void {
    $schema = (new StringSchema())->regex('/^[!@#$%^&*()]+$/');

    $this->assertTrue($schema->safeParse('!@#$%^&*()')->success);
    $this->assertFalse($schema->safeParse('hello')->success);
  }

  public function test_edgecase_StartsWithWholeString_ShouldPass(): void {
    $schema = (new StringSchema())->startsWith('hello world');

    $result = $schema->safeParse('hello world');

    $this->assertTrue($result->success);
  }

  public function test_edgecase_EndsWithWholeString_ShouldPass(): void {
    $schema = (new StringSchema())->endsWith('hello world');

    $result = $schema->safeParse('hello world');

    $this->assertTrue($result->success);
  }

  public function test_edgecase_IncludesWholeString_ShouldPass(): void {
    $schema = (new StringSchema())->includes('hello world');

    $result = $schema->safeParse('hello world');

    $this->assertTrue($result->success);
  }

  public function test_edgecase_MinGreaterThanMax_ShouldFail(): void {
    $schema = (new StringSchema())
      ->min(20)
      ->max(10);

    $result = $schema->safeParse('any string');

    $this->assertFalse($result->success);
  }

  public function test_edgecase_LengthWithUnicodeEmojis_ShouldCountCorrectly(): void {
    $schema = (new StringSchema())->length(3);

    $result = $schema->safeParse('😀😁😂');

    $this->assertTrue($result->success);
  }

  public function test_edgecase_RefineWithComplexCondition_ShouldEnforce(): void {
    $schema = (new StringSchema())->refine(
      fn(mixed $value): bool =>
      preg_match('/^[a-z0-9._-]+@[a-z0-9.-]+\.[a-z]{2,}$/', $value) === 1,
      'Invalid email format'
    );

    $this->assertTrue($schema->safeParse('user@example.com')->success);
    $this->assertFalse($schema->safeParse('invalid-email')->success);
  }

  public function test_edgecase_CloneWithRules_ShouldCreateIndependentCopy(): void {
    $schema1 = (new StringSchema())->min(5);
    $schema2 = clone $schema1;
    $schema2 = $schema2->max(10);

    $value = 'hello';

    $this->assertTrue($schema1->safeParse($value)->success);
    $this->assertTrue($schema2->safeParse($value)->success);

    $value = 'this is a very long string';
    $this->assertTrue($schema1->safeParse($value)->success);
    $this->assertFalse($schema2->safeParse($value)->success);
  }

  public function test_edgecase_DefaultValueWithValidation_ShouldApplyDefault(): void {
    $defaultValue = 'default@example.com';
    $schema = (new StringSchema())
      ->email()
      ->_default($defaultValue);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertSame($defaultValue, $result->data);
  }

  public function test_edgecase_OptionalWithValidation_ShouldAllowNull(): void {
    $schema = (new StringSchema())
      ->min(5)
      ->optional();

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertNull($result->data);
  }

  public function test_edgecase_ChainedRulesWithCustomMessages_ShouldPreserveOrder(): void {
    $schema = (new StringSchema())
      ->min(5, 'Min error')
      ->max(10, 'Max error');

    $resultShort = $schema->safeParse('hi');
    $this->assertFalse($resultShort->success);
    $this->assertSame('Min error', $resultShort->issues[0]->message);

    $resultLong = $schema->safeParse('this is a very long string');
    $this->assertFalse($resultLong->success);
    $this->assertSame('Max error', $resultLong->issues[0]->message);
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new StringSchema();

    $result = $schema->safeParse($value);

    $shouldBeValid = is_string($value);

    if ($shouldBeValid) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
    } else {
      $this->assertFalse($result->success, "Type $typeName should be invalid");

      if ($value === null) {
        $this->assertSame('required', $result->issues[0]->code);
      } else {
        $this->assertSame('invalid_type', $result->issues[0]->code);
      }
    }
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithCoercionAndAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = (new StringSchema())->coerce();

    $result = $schema->safeParse($value);

    $shouldCoerce = is_string($value) || is_int($value) || is_float($value) || is_bool($value);

    if ($shouldCoerce) {
      $this->assertTrue($result->success, "Type $typeName should coerce successfully");
      $this->assertIsString($result->data);
    } else {
      $this->assertFalse($result->success, "Type $typeName should not coerce");
    }
  }

  public function test_boundary_WithEmptyString_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse('');

    $this->assertTrue($result->success);
    $this->assertSame('', $result->data);
  }

  public function test_boundary_WithSingleCharacter_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse('a');

    $this->assertTrue($result->success);
    $this->assertSame('a', $result->data);
  }

  public function test_boundary_WithMaximumUnicodeCharacter_ShouldAccept(): void {
    $schema = new StringSchema();

    $result = $schema->safeParse('🌈');

    $this->assertTrue($result->success);
    $this->assertSame('🌈', $result->data);
  }

  public function test_chainedOperations_ShouldReturnValidSchema(): void {
    $schema = (new StringSchema())
      ->coerce()
      ->trim()
      ->toLowerCase()
      ->min(5)
      ->max(20)
      ->includes('test');

    $result = $schema->safeParse('  TEST STRING  ');

    $this->assertFalse($result->success);

    $result2 = $schema->safeParse('  test string  ');

    $this->assertTrue($result2->success);
    $this->assertSame('test string', $result2->data);
  }

  public function test_validateThenTransform_ShouldTransformValueValidated(): void {
    $schema = (new StringSchema())
      ->transform(static fn(mixed $value) => strtoupper($value))
      ->includes('TEST');

    $result = $schema->safeParse('hello');

    $this->assertFalse($result->success);

    $result2 = $schema->safeParse('test string');

    $this->assertFalse($result2->success);

    $result3 = $schema->safeParse('TEST STRING');

    $this->assertTrue($result3->success);
    $this->assertSame('TEST STRING', $result3->data);
  }

  public function test_refine_WithNativeSingleArgumentCallable_ShouldValidate(): void {
    $schema = (new StringSchema())->refine(is_numeric(...));

    $valid = $schema->safeParse('12');
    $invalid = $schema->safeParse('ab');

    $this->assertTrue($valid->success);
    $this->assertFalse($invalid->success);
    $this->assertSame('custom', $invalid->issues[0]->code);
  }

  public function test_messages_WithInvalidTypeString_ShouldUseCustomMessage(): void {
    $result = (new StringSchema())->messages(['invalid_type' => 'Texto, por favor'])->safeParse(42);

    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Texto, por favor', $result->issues[0]->message);
  }

  public function test_messages_WithInvalidTypeClosure_ShouldReceiveValueAndParams(): void {
    $received = null;
    $schema = (new StringSchema())->messages([
      'invalid_type' => function (mixed $value, array $params) use (&$received): string {
        $received = [$value, $params];
        return 'custom';
      },
    ]);

    $result = $schema->safeParse(42);

    $this->assertSame('custom', $result->issues[0]->message);
    $this->assertSame([42, ['expected' => 'string', 'received' => 'int']], $received);
  }

  public function test_parseType_WithInt_ShouldDescribeAsInt(): void {
    $result = (new StringSchema())->safeParse(42);

    $this->assertSame('Expected string, received int', $result->issues[0]->message);
  }

  public function test_messages_CalledTwiceWithDifferentCodes_ShouldMergeMaps(): void {
    $schema = (new StringSchema())->messages(['required' => 'A'])->messages(['invalid_type' => 'B']);

    $this->assertSame('A', $schema->safeParse(null)->issues[0]->message);
    $this->assertSame('B', $schema->safeParse(1)->issues[0]->message);
  }

  /**
   * @return iterable<string, array{Closure(StringSchema, int): StringSchema, string}>
   */
  public static function provideCountMethods(): iterable {
    yield 'min' => [fn(StringSchema $s, int $n) => $s->min($n), 'min'];
    yield 'max' => [fn(StringSchema $s, int $n) => $s->max($n), 'max'];
    yield 'length' => [fn(StringSchema $s, int $n) => $s->length($n), 'length'];
  }

  /**
   * @param Closure(StringSchema, int): StringSchema $call
   */
  #[DataProvider('provideCountMethods')]
  public function test_count_WithNegative_ShouldThrow(Closure $call, string $method): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage("$method length must be a non-negative int");

    $call(new StringSchema(), -1);
  }

  /**
   * @param Closure(StringSchema, int): StringSchema $call
   */
  #[DataProvider('provideCountMethods')]
  public function test_count_WithZero_ShouldNotThrow(Closure $call, string $method): void {
    $this->assertInstanceOf(StringSchema::class, $call(new StringSchema(), 0));
  }

  public function test_regex_WithInvalidPattern_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('invalid regex pattern: /(/');

    (new StringSchema())->regex('/(/');
  }

  public function test_regex_WithoutDelimiters_ShouldThrow(): void {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('invalid regex pattern: abc');

    (new StringSchema())->regex('abc');
  }
}
