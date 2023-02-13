<?php

namespace StrObj;

use OverflowException;
use StrObj\Helpers\Adapters;

class Middleware
{
    use Adapters;

    /**
     * Memory limit in bytes
     *
     * @var int
     */
    private array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Set middleware options
     *
     * @param array $options
     */
    public function set(string $name, $value): void
    {
        $this->options[$name] = $value;
    }

    /**
     * Get middleware options
     *
     * @return array
     */
    public function get(string $name)
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Memory leak protection
     *
     * @param int     $memory
     */
    private function setMemoryLimit(int $memory): void
    {
        // Its check only once for performance.
        if ($this->get('memory_limit') > 0) {
            return;
        }

        $mbToByte = 1024 * 1024;
        $default = 50 * $mbToByte;

        $memory *= $mbToByte;

        $iniGetMem = ini_get('memory_limit') ?
            $this->convertToByte(ini_get('memory_limit')) : 0;

        if (empty($iniGetMem)) {
            $memory = $default;
        } elseif ($memory > $iniGetMem) {
            $memory = $iniGetMem;
        }

        $this->set('memory_limit', $memory);
    }

    /**
     * Memory leak protection
     */
    public function memoryLeakProtection(): void
    {
        $memoryUsage = memory_get_usage();

        if ($memoryUsage > $this->get('memory_limit')) {
            throw new OverflowException(
                sprintf(
                    'Memory limit exceeded. Memory usage: %s',
                    $this->convertToString($memoryUsage)
                )
            );
        }
    }
}
