<?php

namespace App\Console\Commands;

use App\Services\AgentService;
use Illuminate\Console\Command;

class CheckRemindersCommand extends Command
{
    protected $signature = 'bot:check-reminders';

    protected $description = 'Check and send reminders for inactive conversations';

    public function handle()
    {
        $agentService = app(AgentService::class);
        $reminders = $agentService->checkReminders();

        $this->info("Sent " . count($reminders) . " reminders");

        return 0;
    }
}
