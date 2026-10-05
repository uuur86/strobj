<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StrObj\Data\DataObject;
use StrObj\Data\Validation;
use UnexpectedValueException;

final class ValidationTest extends TestCase
{
    /** @dataProvider values */
    public function testRequiredAndOptionalScalarValues($value, bool $required, bool $expected): void
    {
        $validation = Validation::consistent(DataObject::snapshot([]), ['patterns' => ['digits' => '#^[0-9]+$#']]);
        self::assertSame($expected, $validation->checkErrorStatus('age', 'digits', $value, $required));
    }

    public function values(): array
    {
        return [[0, true, true], ['0', true, true], [false, true, true], [true, true, true],
            [null, false, true], ['', false, true], [null, true, false], ['', true, false],
            ['bad', false, false], [[], false, false], [(object) ['age' => 12], false, false]];
    }

    public function testInvalidRegexThrowsEvenForAnOptionalEmptyValue(): void
    {
        $validation = Validation::consistent(DataObject::snapshot([]), []);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('age');
        $validation->checkErrorStatus('age', '#[invalid#', null, false);
    }

    public function testRulesAndPatternsCanBeUpdatedAfterValidation(): void
    {
        $data = DataObject::snapshot(['age' => 'twelve']);
        $validation = Validation::consistent($data, []);
        self::assertTrue($validation->isValid());
        $validation->setPatterns(['age' => '#^twelve$#']);
        $validation->setRules([['path' => '/age/', 'pattern' => 'age']]);
        self::assertTrue($validation->isValid('age'));
        $validation->setPatterns(['age' => '#^[0-9]+$#']);
        self::assertFalse($validation->isValid('age'));
        $data['age'] = 12;
        self::assertTrue($validation->isValid());
        $validation->setRules([['path' => 'name', 'pattern' => '#.+#', 'required' => true]]);
        self::assertFalse($validation->isValid());
        self::assertTrue($validation->isValid('age'));
    }

    /** @dataProvider emptyCollections */
    public function testEmptyWildcardCollectionsRespectRequired(array $data, bool $required): void
    {
        $validation = Validation::consistent(DataObject::snapshot($data), ['rules' => [
            ['path' => 'persons/*/age', 'pattern' => '#^[0-9]+$#', 'required' => $required],
        ]]);
        self::assertSame(!$required, $validation->isValid('persons/*/age'));
        self::assertSame(!$required, $validation->isValid());
        self::assertSame(!$required, $validation->isValid('persons/new/age'));
        self::assertTrue($validation->isValid('unruled'));
    }

    public function emptyCollections(): array
    {
        return [[['persons' => []], true], [['persons' => []], false],
            [['persons' => 12], true], [['persons' => 12], false]];
    }

    public function testRulesAreCombinedRegardlessOfOrder(): void
    {
        $rules = [['path' => 'age', 'pattern' => '#^[0-9]+$#'],
            ['path' => 'age', 'pattern' => '#^[A-Z]+$#']];

        foreach ([$rules, array_reverse($rules)] as $ordered) {
            $validation = Validation::consistent(DataObject::snapshot(['age' => 12]), ['rules' => $ordered]);
            self::assertFalse($validation->isValid('age'));
            self::assertFalse($validation->isValid('*'));
        }
    }

    public function testProjectedStatusHelpersKeepKeysAndParentFailures(): void
    {
        $validation = Validation::consistent(DataObject::snapshot([]), []);
        $validation->addValidationStatus('persons/*/age', ['u1' => 'bad', 5 => 12], '#^[0-9]+$#', true);
        self::assertFalse($validation->isValid('persons/u1/age'));
        self::assertTrue($validation->isValid('persons/5/age'));
        self::assertFalse($validation->isValid('persons'));
        self::assertFalse($validation->isValid());
        $validation->addValidationStatus('other', 12, '#^[0-9]+$#', true);
        self::assertTrue($validation->isValid('other'));
        self::assertFalse($validation->isValid());
    }

    public function testEmptyProjectedStatusAndScalarStatusHelpers(): void
    {
        $validation = Validation::consistent(DataObject::snapshot([]), []);
        $validation->addValidationStatus('a/*/age', [], '#.+#', false);
        self::assertTrue($validation->isValid('a'));
        $validation->addValidationStatus('b/*/age', [], '#.+#', true);
        self::assertFalse($validation->isValid('b'));
        $validation->addValidationStatus('c/*/age', null, '#.+#', true);
        self::assertFalse($validation->isValid('c'));
    }

    /** @dataProvider invalidRules */
    public function testMalformedRulesAreRejected($rule): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validation::consistent(DataObject::snapshot([]), ['rules' => [$rule]]);
    }

    public function invalidRules(): array
    {
        return [[null], [[]], [['path' => 0, 'pattern' => '#.+#']],
            [['path' => 'a', 'pattern' => []]], [['path' => 'a', 'pattern' => '#.+#', 'required' => 1]],
            [['path' => 'a', 'pattern' => '#.+#', 'required' => null]]];
    }

    public function testMalformedPatternIsRejectedWithoutChangingPreviousPatterns(): void
    {
        $validation = Validation::consistent(DataObject::snapshot([]), ['patterns' => ['ok' => '#.+#']]);

        try {
            $validation->setPatterns(['ok' => []]);
            self::fail('A non-string pattern should be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('#.+#', $validation->getPattern('ok'));
        }
    }

    public function testRuleBatchIsAtomicWhenALaterRuleIsInvalid(): void
    {
        $validation = Validation::consistent(DataObject::snapshot([]), []);

        try {
            $validation->setRules([['path' => 'a', 'pattern' => '#.+#', 'required' => true], []]);
            self::fail('The malformed batch should be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertTrue($validation->isValid());
        }
    }
}
