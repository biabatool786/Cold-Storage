<?php

namespace App\Console\Commands;

use App\Services\Notifications\OperationalAlertHooks;
use Illuminate\Console\Command;

class ScanOverdueInvoicesCommand extends Command
{
    protected $signature = 'csms:scan-overdue-invoices {--tenant= : Limit scan to one tenant id}';

    protected $description = 'Raise Module 19 overdue_invoice alerts for past-due invoices with outstanding balances';

    public function handle(OperationalAlertHooks $hooks): int
    {
        $tenant = $this->option('tenant');
        $count = $hooks->scanOverdueInvoices($tenant !== null ? (int) $tenant : null);
        $this->info('Overdue invoice alert scan complete. Candidates processed: '.$count);

        return self::SUCCESS;
    }
}
