<?php

declare(strict_types=1);

namespace StrObj\Examples\Crud;

use RuntimeException;

/** Reports that the demo cannot prepare its session or database storage. */
final class DemoStorageException extends RuntimeException
{
}
