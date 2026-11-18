<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class HealthRepository
{
    /**
     * Throws when MySQL does not answer a trivial query.
     */
    public function pingDatabase(): void
    {
        DB::select('SELECT 1');
    }
}
