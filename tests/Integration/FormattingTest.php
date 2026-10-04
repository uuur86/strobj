<?php

declare(strict_types=1);

namespace StrObj\Tests\Integration;

use PHPUnit\Framework\TestCase;

/** Verifies the formatter's output, comment preservation and repeatability. */
final class FormattingTest extends TestCase
{
    /** @dataProvider spacingExamples */
    public function testFormattingPreservesCodeAndMatchesProjectSpacing(string $input, string $expected): void
    {
        $root = dirname(__DIR__, 2);
        $directory = $root . '/build/formatting-test-' . bin2hex(random_bytes(6));
        mkdir($directory, 0770, true);
        $file = $directory . '/example.php';
        file_put_contents($file, $input);
        $php = PHP_SAPI === 'phpdbg'
        ? dirname(PHP_BINARY) . '/php' . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '') : PHP_BINARY;
        $command = [$php, $root . '/tools/format.php', $file];
        $result = $this->runCommand($command, $root);
        self::assertSame(0, $result['status'], $result['output']);
        $formatted = file_get_contents($file);
        self::assertSame($expected, $formatted);
        self::assertSame($this->significantTokens($input), $this->significantTokens($formatted));

        // PHPSAB formats an editor buffer through stdin, rather than modifying a file.
        $result = $this->runCommand([
            $php, $root . '/vendor/squizlabs/php_codesniffer/bin/phpcbf',
            '--standard=' . $root . '/phpcs.xml', '--stdin-path=' . $file, '-q', '-',
        ], $root, $input);
        self::assertSame(1, $result['status'], $result['output']);
        self::assertSame($expected, $result['output']);

        $result = $this->runCommand($command, $root);
        self::assertSame(0, $result['status'], $result['output']);
        self::assertSame($formatted, file_get_contents($file), 'A second format must not change the file.');
        $result = $this->runCommand([
            $php, $root . '/vendor/squizlabs/php_codesniffer/bin/phpcs', '--standard=' . $root . '/phpcs.xml', $file,
        ], $root);
        self::assertSame(0, $result['status'], $result['output']);
        $result = $this->runCommand([$php, '-l', $file], $root);
        self::assertSame(0, $result['status'], $result['output']);
    }

    /** Uses complete files so PSR-12 and the custom spacing rule run together. */
    public function spacingExamples(): array
    {
        $header = "<?php\n\ndeclare(strict_types=1);\n\nnamespace StrObj\\Tests\\FormattingFixture;\n\n";

        return [
            'spacing from the CRUD helper' => [$header . <<<'PHP'
/** Returns safe form text without removing false or zero values. */
function formValue($value): string
{
    $default = '';
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    return is_scalar($value) ? (string) $value : $default;
}
PHP
                . "\n", $header . <<<'PHP'
/** Returns safe form text without removing false or zero values. */
function formValue($value): string
{
    $default = '';

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return is_scalar($value) ? (string) $value : $default;
}
PHP
                . "\n"],
            'related branches and do while' => [$header . <<<'PHP'
/** Keeps each branch together and separates the following statement. */
function chooseValue(int $value): int
{
    if ($value === 0) {
        $value = 1;
    } elseif ($value === 2) {
        $value = 3;
    } else {
        $value = 4;
    }
    $value += 1;
    try {
        $value += 1;
    } catch (\RuntimeException $exception) {
        $value = 0;
    } finally {
        $value += 1;
    }
    $value += 1;
    do {
        $value += 1;
    } while ($value < 10);
    $value += 1;
    return $value;
}
PHP
                . "\n", $header . <<<'PHP'
/** Keeps each branch together and separates the following statement. */
function chooseValue(int $value): int
{
    if ($value === 0) {
        $value = 1;
    } elseif ($value === 2) {
        $value = 3;
    } else {
        $value = 4;
    }

    $value += 1;

    try {
        $value += 1;
    } catch (\RuntimeException $exception) {
        $value = 0;
    } finally {
        $value += 1;
    }

    $value += 1;

    do {
        $value += 1;
    } while ($value < 10);

    $value += 1;

    return $value;
}
PHP
                . "\n"],
            'attached comments and literal contents' => [$header . <<<'PHP'
/** Documentation must survive formatting unchanged. */
function commentExample(): string
{
    $value = 'if ($value) { return $value; }'; // Keep this on the assignment.
    // This comment describes the condition.
    if ($value !== '') {
        $value .= ' return ';
    } // Keep this on the closing brace.
    /* This comment describes the return
     * and must stay attached to it. */
    return $value;
}
PHP
                . "\n", $header . <<<'PHP'
/** Documentation must survive formatting unchanged. */
function commentExample(): string
{
    $value = 'if ($value) { return $value; }'; // Keep this on the assignment.

    // This comment describes the condition.
    if ($value !== '') {
        $value .= ' return ';
    } // Keep this on the closing brace.

    /* This comment describes the return
     * and must stay attached to it. */
    return $value;
}
PHP
                . "\n"],
            'misaligned declaration documentation' => [$header . <<<'PHP'
/** Keeps documentation content and corrects its alignment. */
final class DocumentedValue
{
    /**
         * Stores the exact supplied text.
         *
         * @var string
         */
    private string $value = 'unchanged';
}
PHP
                . "\n", $header . <<<'PHP'
/** Keeps documentation content and corrects its alignment. */
final class DocumentedValue
{
    /**
     * Stores the exact supplied text.
     *
     * @var string
     */
    private string $value = 'unchanged';
}
PHP
                . "\n"],
        ];
    }

    /** Includes every comment and literal; ignores whitespace and docblock star indentation. */
    private function significantTokens(string $source): array
    {
        $tokens = array_filter(token_get_all($source), static function ($token): bool {
            return !is_array($token) || $token[0] !== T_WHITESPACE;
        });

        return array_values(array_map(static function ($token) {
            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                $token[1] = preg_replace('/\n[ \t]+(?=\*)/', "\n", $token[1]);
            }

            return is_array($token) ? [$token[0], $token[1]] : $token;
        }, $tokens));
    }

    /** Runs local development tools without shell interpolation. */
    private function runCommand(array $command, string $root, string $input = ''): array
    {
        // Keep the PHP 8.4 CLI independent of deprecated local session.ini defaults.
        array_splice($command, 1, 0, ['-d', 'session.sid_length=32', '-d', 'session.sid_bits_per_character=4']);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        self::assertIsResource($process);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['status' => proc_close($process), 'output' => $output];
    }
}
