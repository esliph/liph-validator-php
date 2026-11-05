<?php

namespace Esliph\Validator\Tests\Schemas\Primitive;

use PHPUnit\Framework\Attributes\DataProvider;

use Esliph\Validator\Errors\ValidatorException;
use Esliph\Validator\Schemas\CoercibleSchema;
use Esliph\Validator\Schemas\Complex\ObjectSchema;
use Esliph\Validator\Schemas\Primitive\DateSchema;

use Esliph\Validator\Tests\Schemas\BaseCoercibleSchemaTestCase;

use Closure;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Override;

class DateSchemaTest extends BaseCoercibleSchemaTestCase {

  #[Override]
  protected function createSchemaCoercible(): CoercibleSchema {
    return new DateSchema();
  }

  #[Override]
  protected function getValidValue(): mixed {
    return new DateTimeImmutable('2024-01-15T10:30:00+00:00');
  }

  #[Override]
  protected function getInvalidValue(): mixed {
    return '2024-01-15';
  }

  #[Override]
  protected function getDefaultValue(): mixed {
    return new DateTimeImmutable('2000-01-01T00:00:00+00:00');
  }

  #[Override]
  protected function getTransformFunction(): Closure {
    return fn(mixed $value) => $value;
  }

  #[Override]
  protected function getRefineCheck(): Closure {
    return fn(mixed $value, array $params): bool => $value instanceof DateTimeImmutable && $value->format('Y') === '2024';
  }

  private static function utc(string $datetime): DateTimeImmutable {
    return new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
  }

  // parseType

  public function test_parseType_WithDateTimeImmutable_ShouldAcceptSameInstant(): void {
    $schema = new DateSchema();
    $input = self::utc('2024-01-15 10:30:00');

    $result = $schema->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertInstanceOf(DateTimeImmutable::class, $result->data);
    $this->assertEquals($input, $result->data);
  }

  public function test_parseType_WithDateTimeImmutable_ShouldReturnNewInstance(): void {
    $schema = new DateSchema();
    $input = self::utc('2024-01-15 10:30:00');

    $result = $schema->safeParse($input);

    $this->assertNotSame($input, $result->data);
  }

  public function test_parseType_WithMutableDateTime_ShouldConvertToDateTimeImmutable(): void {
    $schema = new DateSchema();
    $input = new DateTime('2024-01-15 10:30:00', new DateTimeZone('UTC'));

    $result = $schema->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertInstanceOf(DateTimeImmutable::class, $result->data);
    $this->assertSame($input->getTimestamp(), $result->data->getTimestamp());
  }

  public function test_parseType_WithMutableDateTimeModifiedAfterParse_ShouldNotAffectResult(): void {
    $schema = new DateSchema();
    $input = new DateTime('2024-01-15 10:30:00', new DateTimeZone('UTC'));

    $result = $schema->safeParse($input);
    $input->modify('+1 year');

    $this->assertSame('2024-01-15T10:30:00+00:00', $result->data->format(DateTimeInterface::ATOM));
  }

  public function test_parseType_WithDateTimeInOtherTimezone_ShouldPreserveTimezone(): void {
    $schema = new DateSchema();
    $input = new DateTimeImmutable('2024-01-15 10:30:00', new DateTimeZone('America/Sao_Paulo'));

    $result = $schema->safeParse($input);

    $this->assertSame('America/Sao_Paulo', $result->data->getTimezone()->getName());
  }

  public function test_parseType_WithDateStringWithoutCoerce_ShouldFailWithInvalidType(): void {
    $schema = new DateSchema();

    $result = $schema->safeParse('2024-01-15');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected DateTimeImmutable, received string', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_parseType_WithTimestampWithoutCoerce_ShouldFailWithInvalidType(): void {
    $schema = new DateSchema();

    $result = $schema->safeParse(1700000000);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected DateTimeImmutable, received integer', $result->issues[0]->message);
  }

  public function test_parseType_WithStdClass_ShouldFailWithInvalidType(): void {
    $schema = new DateSchema();

    $result = $schema->safeParse(new \stdClass());

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected DateTimeImmutable, received object', $result->issues[0]->message);
  }

  public function test_parseType_WithNull_ShouldFailWithRequired(): void {
    $schema = new DateSchema();

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('required', $result->issues[0]->code);
  }

  public function test_parseType_WithInvalidTypeInsideObject_ShouldReportFieldPath(): void {
    $schema = new ObjectSchema(['createdAt' => new DateSchema()]);

    $result = $schema->safeParse((object) ['createdAt' => 'not a date']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame(['createdAt'], $result->issues[0]->path);
  }

  public function test_parseType_WithInvalidDateStringInsideObject_ShouldReportFieldPath(): void {
    $schema = new ObjectSchema(['createdAt' => (new DateSchema())->coerce()]);

    $result = $schema->safeParse((object) ['createdAt' => 'not a date']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_date', $result->issues[0]->code);
    $this->assertSame(['createdAt'], $result->issues[0]->path);
  }

  #[DataProvider('provideAllInputTypes')]
  public function test_parseType_WithAllInputTypes_ShouldHandleCorrectly(mixed $value, string $typeName): void {
    $schema = new DateSchema();

    $result = $schema->safeParse($value);

    $shouldBeValid = $value instanceof DateTimeInterface;

    if ($shouldBeValid) {
      $this->assertTrue($result->success, "Type $typeName should be valid");
      $this->assertInstanceOf(DateTimeImmutable::class, $result->data);
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
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse($value);

    // Nenhuma string do provider é uma data válida ('123', '45.67', 'abc', '', '   '...);
    // só inteiros (timestamps) e DateTimeInterface devem ser aceitos.
    $shouldSucceed = is_int($value) || $value instanceof DateTimeInterface;

    if ($shouldSucceed) {
      $this->assertTrue($result->success, "Type $typeName should coerce successfully");
      $this->assertInstanceOf(DateTimeImmutable::class, $result->data);
    } else {
      $this->assertFalse($result->success, "Type $typeName should fail coercion");

      if ($value === null) {
        $this->assertSame('required', $result->issues[0]->code);
      } elseif (is_string($value)) {
        $this->assertSame('invalid_date', $result->issues[0]->code);
      } else {
        $this->assertSame('invalid_type', $result->issues[0]->code);
      }
    }
  }

  // coerce

  public function test_coerce_WithIsoDateString_ShouldParseToDateTimeImmutable(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse('2024-01-15T10:30:00+00:00');

    $this->assertTrue($result->success);
    $this->assertInstanceOf(DateTimeImmutable::class, $result->data);
    $this->assertSame('2024-01-15T10:30:00+00:00', $result->data->format(DateTimeInterface::ATOM));
  }

  public function test_coerce_WithStringWithOffset_ShouldKeepOffset(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse('2024-01-15T10:30:00+02:00');

    $this->assertTrue($result->success);
    $this->assertSame('2024-01-15T10:30:00+02:00', $result->data->format(DateTimeInterface::ATOM));
  }

  public function test_coerce_WithIntegerTimestamp_ShouldConvertToDate(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse(1700000000);

    $this->assertTrue($result->success);
    $this->assertInstanceOf(DateTimeImmutable::class, $result->data);
    $this->assertSame(1700000000, $result->data->getTimestamp());
  }

  public function test_coerce_WithZeroTimestamp_ShouldConvertToEpoch(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse(0);

    $this->assertTrue($result->success);
    $this->assertSame(0, $result->data->getTimestamp());
  }

  public function test_coerce_WithNegativeTimestamp_ShouldConvertToDateBeforeEpoch(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse(-86400);

    $this->assertTrue($result->success);
    $this->assertSame('1969-12-31', $result->data->format('Y-m-d'));
  }

  public function test_coerce_WithAtTimestampString_ShouldParse(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse('@1700000000');

    $this->assertTrue($result->success);
    $this->assertSame(1700000000, $result->data->getTimestamp());
  }

  public function test_coerce_WithNumericString_ShouldFailWithInvalidDate(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse('1700000000');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_date', $result->issues[0]->code);
  }

  public function test_coerce_WithGarbageString_ShouldFailWithInvalidDate(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse('not a date');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_date', $result->issues[0]->code);
    $this->assertSame('Invalid date string', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_coerce_WithOutOfRangeMonthAndDay_ShouldFailWithInvalidDate(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse('2024-13-45');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_date', $result->issues[0]->code);
  }

  public function test_coerce_WithEmptyString_ShouldFailWithInvalidDate(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse('');

    $this->assertFalse($result->success, 'Empty string must not be coerced to the current date');
    $this->assertSame('invalid_date', $result->issues[0]->code);
  }

  public function test_coerce_WithWhitespaceString_ShouldFailWithInvalidDate(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse('   ');

    $this->assertFalse($result->success, 'Whitespace-only string must not be coerced to the current date');
    $this->assertSame('invalid_date', $result->issues[0]->code);
  }

  public function test_coerce_WithOverflowingDayOfMonth_ShouldRollOverToNextMonth(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse('2024-02-30');

    // Comportamento atual (suspeito): o PHP aceita e normaliza para 2024-03-01.
    $this->assertTrue($result->success);
    $this->assertSame('2024-03-01', $result->data->format('Y-m-d'));
  }

  public function test_coerce_WithFloat_ShouldFailWithInvalidType(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse(1700000000.5);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
    $this->assertSame('Expected DateTimeImmutable, received double', $result->issues[0]->message);
  }

  public function test_coerce_WithBoolean_ShouldFailWithInvalidType(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse(true);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithArray_ShouldFailWithInvalidType(): void {
    $schema = (new DateSchema())->coerce();

    $result = $schema->safeParse(['2024-01-15']);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_coerce_WithDateTimeImmutable_ShouldStillAccept(): void {
    $schema = (new DateSchema())->coerce();
    $input = self::utc('2024-01-15 10:30:00');

    $result = $schema->safeParse($input);

    $this->assertTrue($result->success);
    $this->assertEquals($input, $result->data);
  }

  public function test_coerce_ShouldNotMutateOriginalSchema(): void {
    $base = new DateSchema();

    $coercible = $base->coerce();

    $this->assertFalse($base->isValid('2024-01-15'));
    $this->assertTrue($coercible->isValid('2024-01-15'));
  }

  // format

  public function test_format_WithMatchingString_ShouldParseUsingFormat(): void {
    $schema = (new DateSchema())->coerce()->format('d/m/Y');

    $result = $schema->safeParse('15/01/2024');

    $this->assertTrue($result->success);
    $this->assertSame('2024-01-15', $result->data->format('Y-m-d'));
  }

  public function test_format_WithResetModifier_ShouldZeroUnspecifiedFields(): void {
    $schema = (new DateSchema())->coerce()->format('!d/m/Y');

    $result = $schema->safeParse('15/01/2024');

    $this->assertTrue($result->success);
    $this->assertSame('2024-01-15 00:00:00', $result->data->format('Y-m-d H:i:s'));
  }

  public function test_format_WithNonMatchingString_ShouldFailWithInvalidDate(): void {
    $schema = (new DateSchema())->coerce()->format('d/m/Y');

    $result = $schema->safeParse('2024-01-15');

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_date', $result->issues[0]->code);
    $this->assertSame('Invalid date string', $result->issues[0]->message);
  }

  public function test_format_WithTrailingData_ShouldFailWithInvalidDate(): void {
    $schema = (new DateSchema())->coerce()->format('Y-m-d');

    $result = $schema->safeParse('2024-01-15 extra');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_date', $result->issues[0]->code);
  }

  public function test_format_WithEmptyString_ShouldFailWithInvalidDate(): void {
    $schema = (new DateSchema())->coerce()->format('Y-m-d');

    $result = $schema->safeParse('');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_date', $result->issues[0]->code);
  }

  public function test_format_WithStringAcceptedByConstructorButNotByFormat_ShouldFail(): void {
    $schema = (new DateSchema())->coerce()->format('Y-m-d');

    $result = $schema->safeParse('now');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_date', $result->issues[0]->code);
  }

  public function test_format_WithOverflowingDayOfMonth_ShouldRollOverToNextMonth(): void {
    $schema = (new DateSchema())->coerce()->format('!Y-m-d');

    $result = $schema->safeParse('2024-02-30');

    // Comportamento atual (suspeito): createFromFormat aceita overflow e normaliza para 2024-03-01.
    $this->assertTrue($result->success);
    $this->assertSame('2024-03-01', $result->data->format('Y-m-d'));
  }

  public function test_format_WithoutCoerce_ShouldStillRejectStrings(): void {
    $schema = (new DateSchema())->format('d/m/Y');

    $result = $schema->safeParse('15/01/2024');

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_format_WithIntegerTimestamp_ShouldIgnoreFormat(): void {
    $schema = (new DateSchema())->coerce()->format('d/m/Y');

    $result = $schema->safeParse(0);

    $this->assertTrue($result->success);
    $this->assertSame(0, $result->data->getTimestamp());
  }

  public function test_format_WithDateTimeObject_ShouldIgnoreFormat(): void {
    $schema = (new DateSchema())->format('d/m/Y');

    $result = $schema->safeParse(self::utc('2024-01-15'));

    $this->assertTrue($result->success);
  }

  public function test_format_ShouldNotMutateOriginalSchema(): void {
    $base = (new DateSchema())->coerce();

    $formatted = $base->format('d/m/Y');

    $this->assertTrue($base->isValid('2024-01-15'));
    $this->assertFalse($base->isValid('15/01/2024'));
    $this->assertTrue($formatted->isValid('15/01/2024'));
    $this->assertFalse($formatted->isValid('2024-01-15'));
  }

  // timezone

  public function test_timezone_WithStringName_ShouldApplyToParsedString(): void {
    $schema = (new DateSchema())->coerce()->timezone('America/Sao_Paulo');

    $result = $schema->safeParse('2024-01-15 10:00:00');

    $this->assertTrue($result->success);
    $this->assertSame('America/Sao_Paulo', $result->data->getTimezone()->getName());
    $this->assertSame('2024-01-15T10:00:00-03:00', $result->data->format(DateTimeInterface::ATOM));
  }

  public function test_timezone_WithDateTimeZoneObject_ShouldApplyToParsedString(): void {
    $schema = (new DateSchema())->coerce()->timezone(new DateTimeZone('Asia/Tokyo'));

    $result = $schema->safeParse('2024-01-15 10:00:00');

    $this->assertTrue($result->success);
    $this->assertSame('2024-01-15T10:00:00+09:00', $result->data->format(DateTimeInterface::ATOM));
  }

  public function test_timezone_WithFormat_ShouldApplyToFormattedString(): void {
    $schema = (new DateSchema())->coerce()->format('!d/m/Y')->timezone('America/Sao_Paulo');

    $result = $schema->safeParse('15/01/2024');

    $this->assertTrue($result->success);
    $this->assertSame('2024-01-15T00:00:00-03:00', $result->data->format(DateTimeInterface::ATOM));
  }

  public function test_timezone_WithStringContainingOffset_ShouldPreferStringOffset(): void {
    $schema = (new DateSchema())->coerce()->timezone('America/Sao_Paulo');

    $result = $schema->safeParse('2024-01-15T10:00:00+02:00');

    $this->assertTrue($result->success);
    $this->assertSame('2024-01-15T10:00:00+02:00', $result->data->format(DateTimeInterface::ATOM));
  }

  public function test_timezone_WithIntegerTimestamp_ShouldKeepDefaultTimezone(): void {
    $schema = (new DateSchema())->coerce()->timezone('Asia/Tokyo');

    $result = $schema->safeParse(0);

    // Comportamento atual (suspeito): o timezone configurado é ignorado na coerção de inteiros.
    $this->assertTrue($result->success);
    $this->assertSame(0, $result->data->getTimestamp());
    $this->assertSame(date_default_timezone_get(), $result->data->getTimezone()->getName());
  }

  public function test_timezone_WithInvalidName_ShouldThrow(): void {
    $this->expectException(Exception::class);

    (new DateSchema())->timezone('Invalid/Timezone');
  }

  public function test_timezone_ShouldNotMutateOriginalSchema(): void {
    $base = (new DateSchema())->coerce();

    $base->timezone('Asia/Tokyo');
    $result = $base->safeParse('2024-01-15 10:00:00');

    $this->assertSame(date_default_timezone_get(), $result->data->getTimezone()->getName());
  }

  // after

  public function test_after_WithLaterDate_ShouldPass(): void {
    $schema = (new DateSchema())->after(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-15 00:00:01'));

    $this->assertTrue($result->success);
  }

  public function test_after_WithEqualDate_ShouldFailWithTooEarly(): void {
    $schema = (new DateSchema())->after(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-15'));

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('too_early', $result->issues[0]->code);
  }

  public function test_after_WithEarlierDate_ShouldFailWithTooEarlyAndDefaultMessage(): void {
    $schema = (new DateSchema())->after(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-14 23:59:59'));

    $this->assertFalse($result->success);
    $this->assertSame('too_early', $result->issues[0]->code);
    $this->assertSame('Must be after 2024-01-15T00:00:00+00:00', $result->issues[0]->message);
    $this->assertSame([], $result->issues[0]->path);
  }

  public function test_after_WithCustomStringMessage_ShouldUseIt(): void {
    $schema = (new DateSchema())->after(self::utc('2024-01-15'), 'Too soon');

    $result = $schema->safeParse(self::utc('2024-01-01'));

    $this->assertSame('Too soon', $result->issues[0]->message);
  }

  public function test_after_WithCustomClosureMessage_ShouldReceiveValueAndParams(): void {
    $schema = (new DateSchema())->after(
      self::utc('2024-01-15'),
      fn(DateTimeImmutable $value, array $params): string => $value->format('Y-m-d') . ' <= ' . $params['min']->format('Y-m-d')
    );

    $result = $schema->safeParse(self::utc('2024-01-01'));

    $this->assertSame('2024-01-01 <= 2024-01-15', $result->issues[0]->message);
  }

  public function test_after_WithIntegerTimestampBound_ShouldCompareInstants(): void {
    $schema = (new DateSchema())->after(1700000000);

    $this->assertFalse($schema->isValid(self::utc('@1700000000')));
    $this->assertTrue($schema->isValid(self::utc('@1700000001')));
  }

  public function test_after_WithStringBound_ShouldParseBound(): void {
    $schema = (new DateSchema())->after('2024-01-15T00:00:00+00:00');

    $this->assertFalse($schema->isValid(self::utc('2024-01-15')));
    $this->assertTrue($schema->isValid(self::utc('2024-01-16')));
  }

  public function test_after_WithMutableDateTimeBoundModifiedLater_ShouldKeepOriginalBound(): void {
    $bound = new DateTime('2024-01-15', new DateTimeZone('UTC'));
    $schema = (new DateSchema())->after($bound);

    $bound->modify('+1 year');

    $this->assertTrue($schema->isValid(self::utc('2024-06-01')));
  }

  public function test_after_WithBoundInDifferentTimezone_ShouldCompareInstants(): void {
    $schema = (new DateSchema())->after(new DateTimeImmutable('2024-01-15 10:00:00', new DateTimeZone('America/Sao_Paulo')));

    $this->assertFalse($schema->isValid(self::utc('2024-01-15 13:00:00')));
    $this->assertTrue($schema->isValid(self::utc('2024-01-15 13:00:01')));
  }

  public function test_after_WithInvalidStringBound_ShouldThrow(): void {
    $this->expectException(Exception::class);

    (new DateSchema())->after('not a date');
  }

  public function test_after_WithCoercedString_ShouldValidateAfterCoercion(): void {
    $schema = (new DateSchema())->coerce()->after(self::utc('2024-01-15'));

    $this->assertTrue($schema->isValid('2024-02-01T00:00:00+00:00'));
    $this->assertSame('too_early', $schema->safeParse('2024-01-01T00:00:00+00:00')->issues[0]->code);
  }

  public function test_after_WithInvalidType_ShouldShortCircuitBeforeRules(): void {
    $schema = (new DateSchema())->after(self::utc('2024-01-15'));

    $result = $schema->safeParse('2024-02-01');

    $this->assertCount(1, $result->issues);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_after_ShouldNotMutateOriginalSchema(): void {
    $base = new DateSchema();

    $base->after(self::utc('2024-01-15'));

    $this->assertTrue($base->isValid(self::utc('2000-01-01')));
  }

  // before

  public function test_before_WithEarlierDate_ShouldPass(): void {
    $schema = (new DateSchema())->before(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-14 23:59:59'));

    $this->assertTrue($result->success);
  }

  public function test_before_WithEqualDate_ShouldFailWithTooLate(): void {
    $schema = (new DateSchema())->before(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-15'));

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('too_late', $result->issues[0]->code);
  }

  public function test_before_WithLaterDate_ShouldFailWithTooLateAndDefaultMessage(): void {
    $schema = (new DateSchema())->before(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-15 00:00:01'));

    $this->assertFalse($result->success);
    $this->assertSame('too_late', $result->issues[0]->code);
    $this->assertSame('Must be before 2024-01-15T00:00:00+00:00', $result->issues[0]->message);
  }

  public function test_before_WithCustomStringMessage_ShouldUseIt(): void {
    $schema = (new DateSchema())->before(self::utc('2024-01-15'), 'Too late');

    $result = $schema->safeParse(self::utc('2024-02-01'));

    $this->assertSame('Too late', $result->issues[0]->message);
  }

  public function test_before_WithCustomClosureMessage_ShouldReceiveParams(): void {
    $schema = (new DateSchema())->before(
      self::utc('2024-01-15'),
      fn(DateTimeImmutable $value, array $params): string => 'max ' . $params['max']->format('Y-m-d')
    );

    $result = $schema->safeParse(self::utc('2024-02-01'));

    $this->assertSame('max 2024-01-15', $result->issues[0]->message);
  }

  public function test_before_WithIntegerTimestampBound_ShouldCompareInstants(): void {
    $schema = (new DateSchema())->before(1700000000);

    $this->assertTrue($schema->isValid(self::utc('@1699999999')));
    $this->assertFalse($schema->isValid(self::utc('@1700000000')));
  }

  public function test_before_ShouldNotMutateOriginalSchema(): void {
    $base = new DateSchema();

    $base->before(self::utc('2024-01-15'));

    $this->assertTrue($base->isValid(self::utc('2030-01-01')));
  }

  // min / max

  public function test_min_WithValueGreaterThanMin_ShouldPass(): void {
    $schema = (new DateSchema())->min(self::utc('2024-01-15'));

    $this->assertTrue($schema->isValid(self::utc('2024-01-16')));
  }

  public function test_min_WithValueEqualToMin_ShouldPass(): void {
    $schema = (new DateSchema())->min(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-15'));

    // `min` é inclusivo (>=), como NumberSchema::min e z.date().min do Zod.
    $this->assertTrue($result->success, 'min() must be inclusive: a date equal to the minimum is valid');
  }

  public function test_min_WithValueLessThanMin_ShouldFail(): void {
    $schema = (new DateSchema())->min(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-14'));

    $this->assertFalse($result->success);
    $this->assertSame('too_early', $result->issues[0]->code);
  }

  public function test_min_WithCustomMessage_ShouldUseIt(): void {
    $schema = (new DateSchema())->min(self::utc('2024-01-15'), 'Date too early');

    $result = $schema->safeParse(self::utc('2024-01-01'));

    $this->assertSame('Date too early', $result->issues[0]->message);
  }

  public function test_max_WithValueLessThanMax_ShouldPass(): void {
    $schema = (new DateSchema())->max(self::utc('2024-01-15'));

    $this->assertTrue($schema->isValid(self::utc('2024-01-14')));
  }

  public function test_max_WithValueEqualToMax_ShouldPass(): void {
    $schema = (new DateSchema())->max(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-15'));

    // `max` é inclusivo (<=), como NumberSchema::max e z.date().max do Zod.
    $this->assertTrue($result->success, 'max() must be inclusive: a date equal to the maximum is valid');
  }

  public function test_max_WithValueGreaterThanMax_ShouldFail(): void {
    $schema = (new DateSchema())->max(self::utc('2024-01-15'));

    $result = $schema->safeParse(self::utc('2024-01-16'));

    $this->assertFalse($result->success);
    $this->assertSame('too_late', $result->issues[0]->code);
  }

  public function test_max_WithCustomMessage_ShouldUseIt(): void {
    $schema = (new DateSchema())->max(self::utc('2024-01-15'), 'Date too late');

    $result = $schema->safeParse(self::utc('2024-02-01'));

    $this->assertSame('Date too late', $result->issues[0]->message);
  }

  // between

  public function test_between_WithValueInsideRange_ShouldPass(): void {
    $schema = (new DateSchema())->between(self::utc('2024-01-01'), self::utc('2024-12-31'));

    $this->assertTrue($schema->isValid(self::utc('2024-06-15')));
  }

  public function test_between_WithValueEqualToMin_ShouldPass(): void {
    $schema = (new DateSchema())->between(self::utc('2024-01-01'), self::utc('2024-12-31'));

    $this->assertTrue($schema->isValid(self::utc('2024-01-01')));
  }

  public function test_between_WithValueEqualToMax_ShouldPass(): void {
    $schema = (new DateSchema())->between(self::utc('2024-01-01'), self::utc('2024-12-31'));

    $this->assertTrue($schema->isValid(self::utc('2024-12-31')));
  }

  public function test_between_WithValueJustBelowMin_ShouldFailWithOutOfRange(): void {
    $schema = (new DateSchema())->between(self::utc('2024-01-01'), self::utc('2024-12-31'));

    $result = $schema->safeParse(self::utc('2023-12-31 23:59:59'));

    $this->assertFalse($result->success);
    $this->assertCount(1, $result->issues);
    $this->assertSame('out_of_range', $result->issues[0]->code);
    $this->assertSame(
      'Must be between 2024-01-01T00:00:00+00:00 and 2024-12-31T00:00:00+00:00',
      $result->issues[0]->message
    );
  }

  public function test_between_WithValueJustAboveMax_ShouldFailWithOutOfRange(): void {
    $schema = (new DateSchema())->between(self::utc('2024-01-01'), self::utc('2024-12-31'));

    $result = $schema->safeParse(self::utc('2024-12-31 00:00:01'));

    $this->assertFalse($result->success);
    $this->assertSame('out_of_range', $result->issues[0]->code);
  }

  public function test_between_WithMixedBoundTypes_ShouldNormalizeBoth(): void {
    $schema = (new DateSchema())->between(0, '1970-01-02T00:00:00+00:00');

    $this->assertTrue($schema->isValid(self::utc('@0')));
    $this->assertTrue($schema->isValid(self::utc('@86400')));
    $this->assertFalse($schema->isValid(self::utc('@86401')));
  }

  public function test_between_WithCustomMessage_ShouldUseIt(): void {
    $schema = (new DateSchema())->between(self::utc('2024-01-01'), self::utc('2024-12-31'), 'Out of 2024');

    $result = $schema->safeParse(self::utc('2025-01-01'));

    $this->assertSame('Out of 2024', $result->issues[0]->message);
  }

  public function test_between_WithMinGreaterThanMax_ShouldRejectEverything(): void {
    $schema = (new DateSchema())->between(self::utc('2024-12-31'), self::utc('2024-01-01'));

    $this->assertFalse($schema->isValid(self::utc('2024-06-15')));
    $this->assertFalse($schema->isValid(self::utc('2024-01-01')));
    $this->assertFalse($schema->isValid(self::utc('2024-12-31')));
  }

  // regras combinadas

  public function test_rules_WithMultipleFailingRules_ShouldReportAllIssues(): void {
    $schema = (new DateSchema())
      ->after(self::utc('2024-06-01'))
      ->before(self::utc('2024-01-01'))
      ->between(self::utc('2025-01-01'), self::utc('2025-12-31'));

    $result = $schema->safeParse(self::utc('2024-03-01'));

    $this->assertFalse($result->success);
    $this->assertSame(
      ['too_early', 'too_late', 'out_of_range'],
      array_map(fn($issue) => $issue->code, $result->issues)
    );
  }

  public function test_rules_WithAfterAndBefore_ShouldActAsOpenInterval(): void {
    $schema = (new DateSchema())->after(self::utc('2024-01-01'))->before(self::utc('2024-01-31'));

    $this->assertFalse($schema->isValid(self::utc('2024-01-01')));
    $this->assertTrue($schema->isValid(self::utc('2024-01-15')));
    $this->assertFalse($schema->isValid(self::utc('2024-01-31')));
  }

  public function test_rules_WithFailingRule_ShouldNotApplyTransforms(): void {
    $called = false;
    $schema = (new DateSchema())
      ->after(self::utc('2024-01-15'))
      ->transform(function (mixed $value) use (&$called) {
        $called = true;

        return $value;
      });

    $schema->safeParse(self::utc('2024-01-01'));

    $this->assertFalse($called);
  }

  // refine / transform / default

  public function test_refine_WithFailingCheck_ShouldFailWithCustomCode(): void {
    $schema = (new DateSchema())->refine(
      fn(DateTimeImmutable $value, array $params): bool => $value->format('N') < 6,
      'Must be a weekday'
    );

    $result = $schema->safeParse(self::utc('2024-01-13'));

    $this->assertFalse($result->success);
    $this->assertSame('custom', $result->issues[0]->code);
    $this->assertSame('Must be a weekday', $result->issues[0]->message);
  }

  public function test_refine_WithCoercion_ShouldReceiveDateTimeImmutable(): void {
    $received = null;
    $schema = (new DateSchema())->coerce()->refine(function (mixed $value, array $params) use (&$received): bool {
      $received = $value;

      return true;
    });

    $schema->safeParse('2024-01-15');

    $this->assertInstanceOf(DateTimeImmutable::class, $received);
  }

  public function test_transform_WithFormatter_ShouldReturnTransformedValue(): void {
    $schema = (new DateSchema())->coerce()->transform(fn(DateTimeImmutable $value): string => $value->format('d/m/Y'));

    $result = $schema->safeParse('2024-01-15T10:30:00+00:00');

    $this->assertTrue($result->success);
    $this->assertSame('15/01/2024', $result->data);
  }

  public function test_transform_WithChainedTransforms_ShouldApplyInOrder(): void {
    $schema = (new DateSchema())
      ->transform(fn(DateTimeImmutable $value): DateTimeImmutable => $value->modify('+1 day'))
      ->transform(fn(DateTimeImmutable $value): string => $value->format('Y-m-d'));

    $result = $schema->safeParse(self::utc('2024-01-15'));

    $this->assertSame('2024-01-16', $result->data);
  }

  public function test_default_WithDateTimeValue_ShouldReturnEquivalentDate(): void {
    $default = self::utc('2024-01-15');
    $schema = (new DateSchema())->_default($default);

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertEquals($default, $result->data);
  }

  public function test_default_WithClosure_ShouldEvaluateOnEachParse(): void {
    $calls = 0;
    $schema = (new DateSchema())->_default(function () use (&$calls): DateTimeImmutable {
      $calls++;

      return self::utc('2024-01-15');
    });

    $schema->safeParse(null);
    $result = $schema->safeParse(null);

    $this->assertSame(2, $calls);
    $this->assertSame('2024-01-15', $result->data->format('Y-m-d'));
  }

  public function test_default_WithStringAndCoerce_ShouldCoerceDefault(): void {
    $schema = (new DateSchema())->coerce()->_default('2024-01-15T00:00:00+00:00');

    $result = $schema->safeParse(null);

    $this->assertTrue($result->success);
    $this->assertInstanceOf(DateTimeImmutable::class, $result->data);
    $this->assertSame('2024-01-15', $result->data->format('Y-m-d'));
  }

  public function test_default_WithStringWithoutCoerce_ShouldFailWithInvalidType(): void {
    $schema = (new DateSchema())->_default('2024-01-15');

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('invalid_type', $result->issues[0]->code);
  }

  public function test_default_WithValueFailingRule_ShouldFail(): void {
    $schema = (new DateSchema())->after(self::utc('2024-01-15'))->_default(self::utc('2024-01-01'));

    $result = $schema->safeParse(null);

    $this->assertFalse($result->success);
    $this->assertSame('too_early', $result->issues[0]->code);
  }

  public function test_optional_WithDateValue_ShouldStillValidate(): void {
    $schema = (new DateSchema())->optional();

    $this->assertTrue($schema->isValid(self::utc('2024-01-15')));
    $this->assertFalse($schema->isValid('2024-01-15'));
  }

  // clone / parse

  public function test_clone_WithRulesAndTimezone_ShouldBehaveIdentically(): void {
    $schema = (new DateSchema())->coerce()->timezone('Asia/Tokyo')->after(self::utc('2024-01-15'));

    $cloned = clone $schema;

    $this->assertNotSame($schema, $cloned);
    $this->assertSame(
      $schema->safeParse('2024-02-01 10:00:00')->data->format(DateTimeInterface::ATOM),
      $cloned->safeParse('2024-02-01 10:00:00')->data->format(DateTimeInterface::ATOM)
    );
    $this->assertFalse($cloned->isValid('2024-01-01 10:00:00'));
  }

  public function test_clone_AddingRuleToDerived_ShouldNotAffectBase(): void {
    $base = (new DateSchema())->after(self::utc('2024-01-01'));

    $derived = $base->before(self::utc('2024-02-01'));

    $this->assertTrue($base->isValid(self::utc('2024-06-01')));
    $this->assertFalse($derived->isValid(self::utc('2024-06-01')));
  }

  public function test_parse_WithValidDate_ShouldReturnDateTimeImmutable(): void {
    $schema = (new DateSchema())->coerce();

    $data = $schema->parse('2024-01-15T10:30:00+00:00');

    $this->assertInstanceOf(DateTimeImmutable::class, $data);
  }

  public function test_parse_WithRuleViolation_ShouldThrowValidatorException(): void {
    $this->expectException(ValidatorException::class);

    (new DateSchema())->before(self::utc('2024-01-01'))->parse(self::utc('2024-06-01'));
  }
}
