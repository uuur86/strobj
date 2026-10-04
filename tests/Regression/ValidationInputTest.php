<?php

declare(strict_types=1);

namespace StrObj\Tests\Regression;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataObject;
use StrObj\Data\Validation;
use StrObj\StringObjects;
use UnexpectedValueException;

/**
 * Untrusted values can fail validation but never make it throw.
 *
 * @see https://github.com/uuur86/strobj/issues/28
 */
final class ValidationInputTest extends TestCase
{
    /** @dataProvider hostileValues */
    public function testValuesThatBreakThePatternAtRuntimeAreInvalid(string $value, string $pattern): void
    {
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1000');

        try {
            $options = ['validation' => ['rules' => [['path' => 'field', 'pattern' => $pattern, 'required' => true]]]];
            $objects = [
                StringObjects::instance(['field' => $value], $options),
                StringObjects::consistent(['field' => $value], $options),
            ];

            foreach ($objects as $object) {
                self::assertFalse($object->isValid());
                self::assertFalse($object->isValid('field'));
            }
        } finally {
            ini_set('pcre.backtrack_limit', $limit);
        }
    }

    public function hostileValues(): array
    {
        return [
            'malformed UTF-8' => ["\xB1abc", '#^[a-z]+$#u'],
            'backtrack limit' => [str_repeat('a', 5000) . 'b', '#^(a+)+$#'],
        ];
    }

    public function testUnusablePatternsAreStillConfigurationErrors(): void
    {
        $validation = new Validation(new DataObject(['field' => 'abc']), []);
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid validation pattern for path: field');
        $validation->checkErrorStatus('field', '#(unclosed#', 'abc', true);
    }

    public function testOptionalEmptyValuesRemainValid(): void
    {
        $validation = new Validation(new DataObject([]), []);
        self::assertTrue($validation->checkErrorStatus('field', '#^[a-z]+$#u', '', false));
        self::assertTrue($validation->checkErrorStatus('field', '#^[a-z]+$#u', 'abc', true));
    }
}
