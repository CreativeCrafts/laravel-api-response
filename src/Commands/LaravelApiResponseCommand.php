<?php

namespace CreativeCrafts\LaravelApiResponse\Commands;

use Illuminate\Console\Command;

class LaravelApiResponseCommand extends Command
{
    public $signature = 'laravel-api-response';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
