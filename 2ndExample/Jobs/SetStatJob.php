<?php

namespace secondExample\Jobs;

use App\Models\Application;
use App\Services\DevicePostStatService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use function App\Jobs\resolve;

class SetStatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected DevicePostStatService $devicePostStatService;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct()
    {
        $this->devicePostStatService = resolve(DevicePostStatService::class);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
		$applicationsIds = Application::all()->pluck('id');
		$now = Carbon::now();

		foreach ($applicationsIds as $appId)
		{
            $this->devicePostStatService->makeStatByDate($now, $appId);
		}
    }
}
