<?php

namespace App\Console\Commands;

use App\Services\WakaTimeService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class WakaTimeCommand extends Command
{
    protected $signature = 'app:waka-time-command';

    protected $description = 'Command description';

    public function handle()
    {
        $this->info('WakaTimeCommand executed successfully.');

        $service = new WakaTimeService();
        $service->authenticate();

        return CommandAlias::SUCCESS;
    }
}
