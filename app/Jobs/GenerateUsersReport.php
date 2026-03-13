<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateUsersReport implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    { 
        $users = DB::table('users')->get();
        $csv = "id,name,email\n";

        foreach ($users as $user) {
            $csv .= "{$user->id},{$user->name},{$user->email}\n";
        }

        Storage::put('reports/users.csv', $csv);

        Log::info('Users report generated');
    }
}
