<?php

namespace App\Console\Commands;

use App\Support\Diagnostics;
use Illuminate\Console\Command;

class Diagnose extends Command
{
    protected $signature = 'goldentor:diagnose {--no-probe : Ne renderelje le az admin oldalakat}';

    protected $description = 'Golden Tor admin állapotellenőrzés (környezet, táblák, jogosultságok, hibák, oldalak) JSON-ban';

    public function handle(): int
    {
        $report = Diagnostics::report(! $this->option('no-probe'));
        $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));

        return self::SUCCESS;
    }
}
