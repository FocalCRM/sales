<?php

declare(strict_types=1);

namespace Focal\Sales\Console\Commands;

use Focal\Sales\Actions\ProcessCadencesAction;
use Illuminate\Console\Command;

class ProcessCadencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:process-cadences';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process due outbound sales sequences: queue email steps for delivery and create rep tasks for manual steps';

    /**
     * Execute the console command.
     */
    public function handle(ProcessCadencesAction $action): int
    {
        $this->info('Starting automated sales sequence execution...');

        $stats = $action->execute();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Enrollments Evaluated', $stats['processed']],
                ['Emails Queued for Delivery', $stats['emails_sent']],
                ['Emails Skipped (no address or template)', $stats['emails_skipped']],
                ['Cockpit Tasks / Calls Queued', $stats['tasks_created']],
                ['Auto-Unenrolled Contacts', $stats['unenrolled']],
                ['Cadences Completed', $stats['completed']],
            ]
        );

        $this->info('Cadence processing completed successfully.');

        return self::SUCCESS;
    }
}
