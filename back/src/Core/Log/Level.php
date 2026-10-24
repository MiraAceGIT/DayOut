<?php
declare(strict_types=1);

namespace App\Core\Log;

/** Niveaux PSR-3, severite RFC 5424 (0 = plus grave). */
enum Level: string
{
    case Emergency = 'emergency';
    case Alert     = 'alert';
    case Critical  = 'critical';
    case Error     = 'error';
    case Warning   = 'warning';
    case Notice    = 'notice';
    case Info      = 'info';
    case Debug     = 'debug';

    public function severity(): int
    {
        return match ($this) {
            self::Emergency => 0,
            self::Alert     => 1,
            self::Critical  => 2,
            self::Error     => 3,
            self::Warning   => 4,
            self::Notice    => 5,
            self::Info      => 6,
            self::Debug     => 7,
        };
    }

    public function isAtLeast(self $threshold): bool
    {
        return $this->severity() <= $threshold->severity();
    }

    public function channel(): string
    {
        return $this->isAtLeast(self::Warning) ? 'error' : 'info';
    }
}
