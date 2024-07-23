<?php

namespace App\Console\Commands;

use App\Jobs\SetStatByDateJob;
use App\Models\Application;
use App\Models\DevicePostStat;
use App\Services\DevicePostStatService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MakeStatsByBetween extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'custom:make_stats_by_between {date}';
    protected DevicePostStatService $devicePostStatService;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'example: custom:make_stats_by_between 2023-01-01';


    public function __construct(DevicePostStatService $devicePostStatService)
    {
        parent::__construct();
        $this->devicePostStatService = $devicePostStatService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dateStart = Carbon::parse($this->argument('date'));
        $dateEnd = Carbon::now();

        $numberOfDays = $dateStart->diffInDays($dateEnd);

        $applicationIds = Application::all()->pluck('id');

        for ($i = 0; $i <= $numberOfDays; $i++) {
            $date = $dateStart->copy()->addDays($i);
            echo $date->toDateString() . "\n";
            foreach ($applicationIds as $appId) {
                $isExistDevicePostStat = DevicePostStat::where('application_id', $appId)
                    ->whereBetween('date', [
                        $date->startOfDay()->format("Y-m-d H:i:s"),
                        $date->endOfDay()->format("Y-m-d H:i:s")
                    ])->count();

                if (!$isExistDevicePostStat) {
                    dispatch(new SetStatByDateJob($date, $appId, $this->devicePostStatService));
                }
            }
        }
        echo 'Выполнено!';
        return true;
    }
}
