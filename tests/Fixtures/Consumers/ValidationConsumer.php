<?php

declare(strict_types=1);

namespace StrObj\Tests\Fixtures\Consumers;

use StrObj\Data\DataObject;
use StrObj\Data\Validation;

/**
 * Overrides every recorded v2.1 method of {@see Validation} with its recorded signature.
 * PHP checks the signatures when it loads this class; each override records its call and delegates.
 */
final class ValidationConsumer extends Validation
{
    use RecordsCalls;

    /** {@inheritdoc} */
    public function __construct(DataObject $obj, array $options)
    {
        self::record(__FUNCTION__);
        parent::__construct($obj, $options);
    }

    /** {@inheritdoc} */
    public function validate(): void
    {
        self::record(__FUNCTION__);
        parent::validate();
    }

    /** {@inheritdoc} */
    public function checkErrorStatus(string $path, string $pattern, $value, bool $required): bool
    {
        self::record(__FUNCTION__);

        return parent::checkErrorStatus($path, $pattern, $value, $required);
    }

    /** {@inheritdoc} */
    public function getPattern(string $pattern): string
    {
        self::record(__FUNCTION__);

        return parent::getPattern($pattern);
    }

    /** {@inheritdoc} */
    public function setPatterns(array $patterns): void
    {
        self::record(__FUNCTION__);
        parent::setPatterns($patterns);
    }

    /** {@inheritdoc} */
    public function setRules(array $rules): void
    {
        self::record(__FUNCTION__);
        parent::setRules($rules);
    }

    /** {@inheritdoc} */
    public function isValid(string $path = ''): bool
    {
        self::record(__FUNCTION__);

        return parent::isValid($path);
    }

    /** {@inheritdoc} */
    public function setValidationStatus(string $path, $value, string $pattern, bool $required): bool
    {
        self::record(__FUNCTION__);

        return parent::setValidationStatus($path, $value, $pattern, $required);
    }

    /** {@inheritdoc} */
    public function addValidationStatus(string $path, $value, string $pattern, bool $required): void
    {
        self::record(__FUNCTION__);
        parent::addValidationStatus($path, $value, $pattern, $required);
    }
}
