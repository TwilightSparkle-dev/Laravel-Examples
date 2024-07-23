<?php

namespace secondExample\Jobs;

use App\Services\DevicePostStatService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SetStatByDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected DevicePostStatService $devicePostStatService;
    protected Carbon $date;
    protected int $appId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Carbon $date, int $appId, DevicePostStatService $devicePostStatService)
    {
        $this->devicePostStatService = $devicePostStatService;
        $this->date = $date;
        $this->appId = $appId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->devicePostStatService->makeStatByDate($this->date, $this->appId);
    }
}
