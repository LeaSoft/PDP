<?php

namespace App\Support;

readonly class HealthReport
{
    public const OK = 'ok';

    public const ERROR = 'error';

    public function __construct(
        public string $database,
        public ?string $version,
        public string $checkedAt,
    ) {}

    public function isHealthy(): bool
    {
        return $this->database === self::OK;
    }

    /**
     * The shape the DevOps platform and the deploy job read.
     *
     * @return array{status: string, checks: array{database: string}, version: ?string, timestamp: string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->isHealthy() ? 'ok' : 'degraded',
            'checks' => ['database' => $this->database],
            'version' => $this->version,
            'timestamp' => $this->checkedAt,
        ];
    }
}
