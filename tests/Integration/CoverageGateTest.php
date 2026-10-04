<?php

declare(strict_types=1);

namespace StrObj\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class CoverageGateTest extends TestCase
{
    private string $reportPath;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'strobj-coverage-');
        self::assertNotFalse($path);
        $this->reportPath = $path;
    }

    protected function tearDown(): void
    {
        if (is_file($this->reportPath)) {
            unlink($this->reportPath);
        }
    }

    public function testCompleteReportPasses(): void
    {
        file_put_contents($this->reportPath, $this->fixture('complete'));
        [$status, $output] = $this->runGate();
        self::assertSame(0, $status, $output);
        self::assertStringContainsString('Coverage gate passed: 100%', $output);
    }

    /** @dataProvider incompleteReports */
    public function testIncompleteOrInvalidReportFails(string $kind, string $message): void
    {
        if ($kind === 'missing') {
            unlink($this->reportPath);
        } else {
            file_put_contents($this->reportPath, $this->fixture($kind));
        }

        [$status, $output] = $this->runGate();
        self::assertSame(1, $status, $output);
        self::assertStringContainsString($message, $output);
    }

    public function incompleteReports(): array
    {
        return [['missing', 'Coverage report not found'], ['invalid', 'Invalid Clover'],
            ['empty', 'Source file missing'], ['omitted', 'Source file missing'],
            ['uncovered', 'is uncovered'], ['inconsistent', 'Metrics disagree'],
            ['badmetrics', 'Invalid metrics'], ['duplicate', 'Duplicate file'],
            ['zero', 'no executable production lines']];
    }

    private function fixture(string $kind): string
    {
        if ($kind === 'invalid') {
            return '<not-valid-xml';
        }

        $entries = [];
        $source = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            dirname(__DIR__, 2) . '/src',
            \FilesystemIterator::SKIP_DOTS
        ));

        foreach ($source as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $name = htmlspecialchars($file->getRealPath(), ENT_QUOTES | ENT_XML1, 'UTF-8');
            $count = $kind === 'uncovered' ? 0 : 1;
            $covered = $kind === 'inconsistent' ? 0 : $count;
            $statements = $kind === 'badmetrics' ? 'bad' : '1';
            $entries[] = $kind === 'zero'
            ? '<file name="' . $name . '"><metrics statements="0" coveredstatements="0"/></file>'
            : '<file name="' . $name . '"><line num="1" type="stmt" count="' . $count . '"/>' .
            '<metrics statements="' . $statements . '" coveredstatements="' . $covered . '"/></file>';
        }

        if ($kind === 'empty') {
            $entries = [];
        } elseif ($kind === 'omitted') {
            array_pop($entries);
        } elseif ($kind === 'duplicate') {
            $entries[] = $entries[0];
        }

        return '<coverage><project>' . implode('', $entries) . '</project></coverage>';
    }

    private function runGate(): array
    {
        $command = [PHP_BINARY];

        if (strpos(strtolower(basename(PHP_BINARY)), 'phpdbg') !== false) {
            $command[] = '-qrr';
        }

        $command[] = dirname(__DIR__, 2) . '/tools/check-coverage.php';
        $command[] = $this->reportPath;
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
