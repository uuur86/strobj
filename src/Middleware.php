<?php

namespace StrObj;

use StrObj\Helpers\Adapters;

class Middleware
{
    use Adapters;

    /**
     * Memory limit in bytes
     *
     * @var int
     */
    private int $memoryLimit = 0;

    public function __construct(int $memory = 50)
    {
        $this->setMemoryLimit($memory);
    }

    /**
     * Memory leak protection
     *
     * @param int     $mem
     */
    private function setMemoryLimit(int $memory): void
    {
        // Its check only once for performance.
        if ($this->memoryLimit > 0) {
            return;
        }

        $mbToByte = 1024 * 1024;
        $default = 50 * $mbToByte;

        $mem *= $mbToByte;

        $iniGetMem = ini_get('memory_limit') ?
            $this->convertToByte(ini_get('memory_limit')) : 0;

        if (empty($iniGetMem)) {
            $mem = $default;
        } elseif ($mem > $iniGetMem) {
            $mem = $iniGetMem;
        }

        $this->memoryLimit = $mem;
    }
}
