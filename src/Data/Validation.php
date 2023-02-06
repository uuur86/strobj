<?php

namespace StrObj\Data;

use ArrayIterator;
use Iterator;
use JsonSerializable;
use StrObj\Interfaces\DataStructures\DataInterface;

class Validation
{
    /**
     * The object paths which have validation errors
     *
     * @var array
     */
    private array $validationErrors = [];

    /**
     * User defined regex templates
     *
     * @var array
     */
    private array $regexType = [];

    /**
     * Validates the object path
     *
     * @param string $path
     * @param string $regex
     * @param bool   $required
     *
     * @return bool  true if valid, false if not
     *
     * @throws UnexpectedValueException
     */
    private function validate(string $path, string $regex, bool $required): bool
    {
        $result = true;

        if (!$this->isPathExists($path)) {
            $this->setAllPaths($this->validationErrors, $path, false);
        }

        $values = $this->get($path);

        if (!$required && !$values) {
            return true;
        }

        if (is_string($values) && !empty($values)) {
            $result = preg_match($regex, $values);

            if ($result === 0 || ($required && empty($values))) {
                $this->setAllPaths($this->validationErrors, $path, false);
                return false;
            } elseif ($result === 1) {
                return true;
            } elseif ($result === false) {
                throw new UnexpectedValueException("StrObj Error: Validation error!");
            }

            return $result;
        }

        if (!$result && !empty($path)) {
            $this->setAllPaths($this->validationErrors, $path, false);
        }

        return $result;
    }

    /**
     * Registers regex type
     *
     * @param string  $key type key name
     * @param string  $regex regex pattern
     */
    public function addRegexType(string $key, string $regex): void
    {
        if (!empty($key) && !empty($regex)) {
            $this->regexType[$key] = $regex;
        }
    }

    /**
     * Checks if the value is valid or not
     *
     * @param string  $path       requested path
     * @param string  $type       pre-defined validator type
     * @param bool    $required   field is required?
     * @param string  $selfRegex  self defined regex text
     */
    public function validator(string $path, string $type, $required = false, $selfRegex = ""): void
    {
        if (isset($this->regexType[$type])) {
            $regex = $this->regexType[$type];
        } elseif (!empty($selfRegex)) {
            $regex = $selfRegex;
        }

        if (!empty($regex)) {
            $this->validate($path, $regex, $required);
        }
    }

    /**
     * Checks whether the value which is in the desired path
     * and added to the control list is valid or not
     *
     * @param string $path  requested path
     *
     * @return bool
     */
    public function isValid(?string $path): bool
    {
        return $this->isPathExists($path) && !isset($this->validationErrors[$path]);
    }

    /**
     * Sets the value to the all parent paths.
     *
     * @param array     $data
     * @param string    $path
     * @param mixed     $value
     */
    public function setAllPaths(array &$data, string $path, $value): void
    {
        $pathArray = explode('/', $path);

        if (!is_array($pathArray)) {
            return;
        }

        $totalPath = [];

        foreach ($pathArray as $pathPart) {
            $totalPath[] = $pathPart;

            if (is_array($totalPath) && !empty($totalPath)) {
                $newPath = implode('/', $totalPath);
                $data[$newPath] = $value;
            }
        }
    }
}
