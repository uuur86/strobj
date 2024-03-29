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

namespace StrObj\Data;

use UnexpectedValueException;

class Validation
{
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
     * Constructor
     *
     * @param DataObject $obj  The object to use
     */
    public function __construct(DataObject $obj, array $options)
    {
        $this->obj = $obj;

        if (isset($options['rules'])) {
            $this->rules = $options['rules'];
        }

        if (isset($options['patterns'])) {
            $this->patterns = $options['patterns'];
        }
    }

    /**
     * Validates the value with the given regex on the given path
     *
     * @throws UnexpectedValueException
     */
    public function validate(): void
    {
        foreach ($this->rules as ["path" => $path, "pattern" => $pattern, "required" => $required]) {
            $value = $this->obj->query($path);
            $this->addValidationStatus($path, $value, $pattern, $required);
        }
    }

    /**
     * Check error status for the given path
     *
     * @param string $path      requested path
     * @param string $pattern   regex pattern
     * @param mixed  $value     value to be checked
     * @param bool   $required  is required
     *
     * @return bool
     *
     * @throws UnexpectedValueException
     */
    public function checkErrorStatus(string $path, string $pattern, $value, bool $required): bool
    {
        $value   = (string) $value;
        $pattern = $this->getPattern($pattern);
        $result  = preg_match($pattern, $value);

        if ($result === false) {
            throw new UnexpectedValueException("StrObj Error: Validation error!");
        }

        return (!$required && empty($value)) || $result === 1;
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
        if (isset($this->patterns[$pattern])) {
            return $this->patterns[$pattern];
        }

        return $pattern;
    }

    /**
     * Registers new pattern
     *
     * @param array $patterns  patterns array to be registered
     */
    public function setPatterns(array $patterns): void
    {
        $this->patterns = $patterns;
    }

    /**
     * Adds new rule to the validation list
     *
     * @param array $rules  rules array to be added
     */
    public function setRules(array $rules): void
    {
        $this->rules = array_merge($this->rules, $rules);
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
        if (!isset($this->validationStatus[$path])) {
            $this->validate();
        }

        if ($path === '' || $path === '*') {
            foreach ($this->validationStatus as $status) {
                if (!$status) {
                    return false;
                }
            }
            return true;
        }

        return $this->validationStatus[$path] ?? true;
    }

    /**
     * Adds new validation error status to the validationStatus array
     *
     * @param string $path    requested path
     * @param bool   $status  validation status
     *
     * @return bool
     */
    public function setValidationStatus(string $path, $value, string $pattern, bool $required): bool
    {
        $status = $this->checkErrorStatus($path, $pattern, $value, $required);
        $this->validationStatus[$path] = $status;

        return $status;
    }

    /**
     * Sets the status to the all parent paths.
     *
     * @param string $path    Data path
     * @param mixed  $value   Data value
     * @param string $pattern Validation pattern
     * @param bool   $status  Is value required
     */
    public function addValidationStatus(string $path, $value, string $pattern, bool $required): void
    {
        $status   = true;
        $path     = DataPath::init($path);
        $path_txt = $path->getRaw();

        if ($path->valid()) {
            $parent_branches = $path->getBranches();

            if (is_array($value)) {
                $relative_path = $path->findPaths(
                    $path_txt,
                    $value,
                    function ($path_sub, $val) use (&$status, $required, $pattern) {
                        if (! $this->setValidationStatus($path_sub, $val, $pattern, $required)) {
                            $status = false;
                        }
                    }
                );
            } else {
                if (! $this->setValidationStatus($path_txt, $value, $pattern, $required)) {
                    $status = false;
                }
            }

            if (count($parent_branches) > 0) {
                $parent_branches = array_combine(
                    $parent_branches,
                    array_fill(0, count($parent_branches), $status)
                );
                $diff = array_diff_key($parent_branches, $this->validationStatus);
                $this->validationStatus = array_merge($this->validationStatus, $diff);
            }
        }
    }
}
