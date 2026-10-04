<?php

/**
 * This file is part of the StrObj package.
 *
 * (c) Uğur Biçer <contact@codeplus.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package  StrObj
 * @version  GIT: <git_id>
 * @link     https://github.com/uuur86/strobj
 */

declare(strict_types=1);

namespace StrObj\Data;

use InvalidArgumentException;
use StrObj\Helpers\DataParsers;
use UnexpectedValueException;

class Validation
{
    use DataParsers;

    /**
     * PCRE runtime errors caused by the validated value rather than the pattern.
     */
    private const VALUE_ERRORS = [
        PREG_BACKTRACK_LIMIT_ERROR,
        PREG_RECURSION_LIMIT_ERROR,
        PREG_BAD_UTF8_ERROR,
        PREG_BAD_UTF8_OFFSET_ERROR,
        PREG_JIT_STACKLIMIT_ERROR,
    ];

    /**
     * The object paths which have validation errors
     *
     * @var array
     */
    private array $validationStatus = [];
    /**
     * Rules
     */
    private array $rules = [];
    /**
     * User defined regex patterns
     *
     * @var array
     */
    private array $patterns = [];
    /**
     * The object which will be validated
     *
     * @var DataObject
     */
    private DataObject $obj;
    /**
     * The data revision reflected by the stored validation results.
     *
     * @var int|null
     */
    private ?int $validatedRevision = null;
    /** @var bool Whether rule flags require exact boolean values. */
    private bool $consistent;

    /**
     * Constructor
     *
     * @param DataObject $obj  The object to use
     * @param array      $options Named patterns and validation rules.
     * @param bool       $consistent Enable strict configuration checking.
     */
    public function __construct(DataObject $obj, array $options, bool $consistent = false)
    {
        $this->obj = $obj;
        $this->consistent = $consistent;
        $this->setPatterns($options['patterns'] ?? []);
        $this->setRules($options['rules'] ?? []);
    }
    /** @return self A validator with strict rule configuration. */
    public static function consistent(DataObject $obj, array $options): self
    {
        return new self($obj, $options, true);
    }

    /**
     * Validates current data with every configured rule and records its revision
     *
     * @throws UnexpectedValueException
     */
    public function validate(): void
    {
        $this->validationStatus = [];
        $this->validatedRevision = null;

        foreach ($this->rules as $rule) {
            $status = true;
            $matches = $this->obj->findMatches($rule['path']);

            if ($matches === []) {
                $status = $this->checkErrorStatus($rule['path'], $rule['pattern'], null, $rule['required']);
            }

            foreach ($matches as $path => $match) {
                $result = $this->setValidationStatus(
                    (string) $path,
                    $match['value'],
                    $rule['pattern'],
                    $rule['required']
                );
                $status = $result && $status;
            }

            $this->recordStatus($rule['path'], $status);
        }

        $this->validatedRevision = $this->obj->getRevision();
    }

    /**
     * Check error status for the given path
     *
     * @param string $path      requested path
     * @param string $pattern   regex pattern
     * @param mixed  $value     value to be checked
     * @param bool   $required  is required
     *
     * @return bool False also when the value makes the pattern fail at runtime
     *              (malformed UTF-8, backtrack, recursion or JIT stack limits).
     *
     * @throws UnexpectedValueException When the pattern itself cannot be used.
     */
    public function checkErrorStatus(string $path, string $pattern, $value, bool $required): bool
    {
        if (!is_scalar($value) && $value !== null) {
            return false;
        }

        $text = is_bool($value) ? (string) (int) $value : (string) $value;
        $result = @preg_match($this->getPattern($pattern), $text);

        if ($result === false) {
            // Errors caused by the value fail closed; only an unusable pattern is a configuration error.
            if (!in_array(preg_last_error(), self::VALUE_ERRORS, true)) {
                throw new UnexpectedValueException('Invalid validation pattern for path: ' . $path);
            }

            $result = 0;
        }

        if ($value === null || $value === '') {
            return !$required;
        }

        return $result === 1;
    }

    /**
     * Searches for the given pattern name in the patterns array and returns it
     * if it is found. Otherwise, it returns the given pattern name as a regex pattern.
     *
     * @param string $pattern  pattern name or regex pattern
     *
     * @return string
     */
    public function getPattern(string $pattern): string
    {
        return $this->patterns[$pattern] ?? $pattern;
    }

    /**
     * Registers new pattern
     *
     * @param array $patterns  patterns array to be registered
     */
    public function setPatterns(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            if (!is_string($pattern)) {
                throw new InvalidArgumentException('Validation patterns must be strings.');
            }
        }

        $this->patterns = $patterns;
        $this->validationStatus = [];
        $this->validatedRevision = null;
    }

    /**
     * Adds rules to the validation list; required defaults to false
     *
     * @param array $rules  rules array to be added
     */
    public function setRules(array $rules): void
    {
        $normalized = [];

        foreach ($rules as $rule) {
            if (
                !is_array($rule) || !isset($rule['path'], $rule['pattern']) ||
                ($this->consistent ? !is_string($rule['path']) : !is_scalar($rule['path'])) ||
                ($this->consistent ? !is_string($rule['pattern']) : !is_scalar($rule['pattern'])) ||
                (array_key_exists('required', $rule) &&
                    ($this->consistent ? !is_bool($rule['required']) : !is_scalar($rule['required'])))
            ) {
                throw new InvalidArgumentException('Rules require string path/pattern and a boolean required flag.');
            }

            $normalized[] = [
                'path' => $this->normalizePath((string) $rule['path']),
                'pattern' => (string) $rule['pattern'],
                'required' => (bool) ($rule['required'] ?? false),
            ];
        }

        $this->rules = array_merge($this->rules, $normalized);
        $this->validationStatus = [];
        $this->validatedRevision = null;
    }

    /**
     * Checks whether the value which is in the desired path
     * and added to the control list is valid or not
     *
     * @param string $path  requested path
     *
     * @return bool
     */
    public function isValid(string $path = ''): bool
    {
        if ($this->validatedRevision !== $this->obj->getRevision()) {
            $this->validate();
        }

        $path = $this->normalizePath($path);

        if ($path === '' || $path === '*') {
            return !in_array(false, $this->validationStatus, true);
        }

        if (array_key_exists($path, $this->validationStatus)) {
            return $this->validationStatus[$path];
        }

        $status = true;

        foreach ($this->rules as $rule) {
            if ($this->matchesPath($rule['path'], $path)) {
                $result = $this->checkErrorStatus($path, $rule['pattern'], $this->obj->query($path), $rule['required']);
                $status = $result && $status;
            }
        }

        return $status;
    }

    /**
     * Adds new validation error status to the validationStatus array
     *
     * @param string $path    requested path
     * @param mixed  $value    Value to validate.
     * @param string $pattern  Pattern name or regex.
     * @param bool   $required Whether null and empty string are forbidden.
     *
     * @return bool
     */
    public function setValidationStatus(string $path, $value, string $pattern, bool $required): bool
    {
        $status = $this->checkErrorStatus($path, $pattern, $value, $required);
        $this->recordStatus($path, $status);

        return $status;
    }

    /**
     * Sets the status to the all parent paths.
     *
     * @param string $path    Data path
     * @param mixed  $value   Data value
     * @param string $pattern Validation pattern
     * @param bool   $required Is value required
     */
    public function addValidationStatus(string $path, $value, string $pattern, bool $required): void
    {
        $status = true;

        if (is_array($value) && strpos($path, '*') !== false) {
            $paths = DataPath::init($path)->findPaths($path, $value);

            if ($paths === []) {
                $status = !$required;
            }

            foreach ($paths as $concrete => $item) {
                $result = $this->setValidationStatus((string) $concrete, $item, $pattern, $required);
                $status = $result && $status;
            }
        } else {
            $status = $this->setValidationStatus($path, $value, $pattern, $required);
        }

        $this->recordStatus($path, $status);
    }

    /**
     * Combines rule results with logical AND for a path and its parents.
     *
     * @param string $path   Concrete path or wildcard rule path.
     * @param bool   $status Result to combine.
     *
     * @return void
     */
    private function recordStatus(string $path, bool $status): void
    {
        $this->validatedRevision = $this->obj->getRevision();
        $path = $this->normalizePath($path);
        $branches = DataPath::init($path)->getBranches();
        $branches[] = $path;

        foreach ($branches as $branch) {
            $this->validationStatus[$branch] = ($this->validationStatus[$branch] ?? true) && $status;
        }
    }
}
