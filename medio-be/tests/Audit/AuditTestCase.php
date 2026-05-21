<?php

namespace Tests\Audit;

use Tests\TestCase;

abstract class AuditTestCase extends TestCase
{
    protected function repoRoot(string $path = ''): string
    {
        $root = dirname(__DIR__, 3);

        return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    protected function backendRoot(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);

        return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    protected function assertSourceContains(string $path, string $needle): void
    {
        $fullPath = $this->repoRoot($path);

        $this->assertFileExists($fullPath);
        $this->assertStringContainsString($needle, file_get_contents($fullPath));
    }

    protected function assertSourceMatches(string $path, string $pattern): void
    {
        $fullPath = $this->repoRoot($path);

        $this->assertFileExists($fullPath);
        $this->assertMatchesRegularExpression($pattern, file_get_contents($fullPath));
    }
}
