<?php

namespace App\Console\Commands;

use App\Jobs\GenerateRecurrences;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recurrences:materialize {--days=1 : How many days ahead to generate}')]
#[Description('Generate recurring todo instances for today and the look-ahead window.')]
class MaterializeRecurrencesCommand extends Command
{
    public function handle(): int
    {
        GenerateRecurrences::dispatchSync((int) $this->option('days'));

        $this->info('Recurrences gematerialiseerd.');

        return self::SUCCESS;
    }
}
