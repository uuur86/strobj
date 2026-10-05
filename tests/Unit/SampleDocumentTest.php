<?php

declare(strict_types=1);

namespace StrObj\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StrObj\Data\DataFilters;
use StrObj\Data\DataObject;
use StrObj\Data\Validation;

/** Exercises the components with the sample document used since v2.1. */
final class SampleDocumentTest extends TestCase
{
    public function testDataObjectReadsAndWritesTheSampleDocument(): void
    {
        $data = new DataObject(json_decode($this->sampleDocument()));

        self::assertSame('John Doe', $data->get('persons/0/name'));
        self::assertSame('21', $data->get('persons/3/age'));

        $data->set('persons/4/name', 'Neo Doe');
        $data->set('persons/4/age', 199);

        self::assertSame('Neo Doe', $data->get('persons/4/name'));
        self::assertSame(199, $data->get('persons/4/age'));
    }

    public function testFiltersCastAndRejectValuesInTheSampleDocument(): void
    {
        $data = json_decode($this->sampleDocument(), true);
        $filters = new DataFilters([
            'persons/*/age' => [
                'type' => 'int',
                'callback' => static function ($value): bool {
                    return $value > 10;
                },
            ],
            'persons/*/name' => [
                'type' => 'string',
                'callback' => static function ($value): bool {
                    return preg_match('#^[a-z ]+$#iu', $value) === 1;
                },
            ],
        ]);
        $names = $filters->filter('persons/*/name', $data);
        $ages = $filters->filter('persons/*/age', $data);

        self::assertSame('John Doe', $names['persons'][0]['name']);
        self::assertFalse($ages['persons'][0]['age']);
        self::assertSame(34, $ages['persons'][2]['age']);
    }

    public function testValidationChecksTheSampleDocument(): void
    {
        $validation = new Validation(new DataObject(json_decode($this->sampleDocument())), [
            'patterns' => [
                'age' => '#^[0-9]+$#siu',
                'name' => '#^[a-zA-Z ]+$#siu',
            ],
            'rules' => [
                ['path' => 'persons/*/age', 'pattern' => 'age', 'required' => true],
                ['path' => 'persons/*/name', 'pattern' => 'name', 'required' => true],
            ],
        ]);

        self::assertFalse($validation->isValid('persons/0/age'));
        self::assertTrue($validation->isValid('persons/1/age'));
        self::assertFalse($validation->isValid('persons/*/age'));
        self::assertFalse($validation->isValid('persons'));
    }

    private function sampleDocument(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/Fixtures/sample-document.json');
    }
}
