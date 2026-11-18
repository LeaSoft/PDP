<?php

namespace App\Services;

use App\Repositories\HealthRepository;
use App\Support\HealthReport;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthService
{
    public function __construct(
        private readonly HealthRepository $health,
    ) {}

    public function check(): HealthReport
    {
        return new HealthReport(
            database: $this->databaseStatus(),
            version: $this->version(),
            checkedAt: now()->toIso8601ZuluString(),
        );
    }

    /**
     * The reason stays in the log; the public answer only says ok or error.
     */
    private function databaseStatus(): string
    {
        try {
            $this->health->pingDatabase();

            return HealthReport::OK;
        } catch (Throwable $e) {
            Log::warning('Health check: the database did not answer', ['error' => $e->getMessage()]);

            return HealthReport::ERROR;
        }
    }

    /**
     * The image tag of this deploy (the commit SHA), or null outside a deploy.
     */
    private function version(): ?string
    {
        $version = config('app.version');

        return is_string($version) && $version !== '' ? $version : null;
    }
}
