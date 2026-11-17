<?php

namespace App\Console\Commands;

use App\Services\RideAppointments;
use Illuminate\Console\Command;

class DispatchRideAppointments extends Command
{
    protected $signature = 'rides:dispatch-appointments';
    protected $description = 'Release due passenger appointments to approved drivers';

    public function handle(): int
    {
        if (!RideAppointments::ready()) {
            $this->error('The ride-appointment schema has not been installed.');
            return 1;
        }
        $count = RideAppointments::dispatchDue();
        $this->info("Released {$count} due ride appointment(s).");
        return 0;
    }
}
