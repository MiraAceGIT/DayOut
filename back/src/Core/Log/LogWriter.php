<?php
declare(strict_types=1);

namespace App\Core\Log;

use RuntimeException;

final class LogWriter
{
    /** @param array<string, int> $retentionDays canal => jours de conservation */
    public function __construct(
        private readonly string $directory,
        private readonly array $retentionDays,
    ) {
    }

    public function append(string $channel, string $line): void
    {
        $file      = $this->directory . '/' . $channel . '-' . gmdate('Y-m-d') . '.log';
        $isNewFile = !is_file($file);

        if ($isNewFile) {
            $this->ensureDirectory();
        }
        if (file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Cannot write log file: $file");
        }
        // Premiere ecriture du jour sur ce canal : purge une fois par jour, sans cron.
        if ($isNewFile) {
            $this->purge($channel);
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException("Cannot create log directory: {$this->directory}");
        }
    }

    private function purge(string $channel): void
    {
        $days = $this->retentionDays[$channel] ?? null;
        if ($days === null) {
            return;
        }
        $cutoff = gmdate('Y-m-d', time() - $days * 86400);
        foreach (glob($this->directory . '/' . $channel . '-*.log') ?: [] as $file) {
            $date = substr(basename($file, '.log'), strlen($channel) + 1);
            if ($date < $cutoff) {
                unlink($file);
            }
        }
    }
}
