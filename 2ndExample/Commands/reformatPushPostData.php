<?php

namespace App\Console\Commands;

use App\Jobs\SetStatByDateJob;
use App\Models\Application;
use App\Models\DevicePostStat;
use App\Models\PushPost;
use App\Models\PushPostSchedule;
use App\Services\DevicePostStatService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class reformatPushPostData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'custom:push_post_reform';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description;


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $recordIds = DB::table('push_posts AS t1')
            ->join('push_posts AS t2', function ($join) {
                $join->on('t1.application_id', '=', 't2.application_id')
                    ->on('t1.post_id', '=', 't2.post_id')
                    ->on('t1.location_id', '=', 't2.location_id')
                    ->on('t1.type', '=', 't2.type')
                    ->where('t1.provider', '=', 1)
                    ->where('t2.provider', '=', 2)
                    ->whereBetween('t2.created_at', [
                        DB::raw('DATE_SUB(t1.created_at, INTERVAL 25 MINUTE)'),
                        DB::raw('DATE_ADD(t1.created_at, INTERVAL 25 MINUTE)')
                    ]);
            })
            ->select('t1.id AS t1_id', 't2.id AS t2_id')
            ->pluck('t1_id', 't2_id');

        $countBadData = PushPost::query()
            ->where('application_id', null)
            ->orWhere('location_id', null)
            ->orWhere('post_id', null)
            ->count();
        if ($countBadData) {
            PushPost::query()
                ->where('application_id', null)
                ->orWhere('location_id', null)
                ->orWhere('post_id', null)
                ->delete();
        }

        $oneProviders = PushPost::query()->where('provider', 1)->where('push_post_schedule_id', null)->get();

        foreach ($oneProviders as $push) {

            $pushPostSchedule = PushPostSchedule::create([
                'application_id' => $push->application_id,
                'post_id' => $push->post_id,
                'location_id' => $push->location_id,
                'type' => $push->type,
                'sent_time' => $push->created_at,
                'is_sent' => true
            ]);
            $id = $pushPostSchedule->id;
            $push->push_post_schedule_id = $id;
            $push->save();

        }

        $recordIds = $recordIds->toArray();

        $twoProv = PushPost::query()->where('provider', 2)->get();
        foreach ($twoProv as $prov) {
            if (array_key_exists($prov->id, $recordIds)) {
                $onePr = PushPost::find($recordIds[$prov->id]);
                $prov->push_post_schedule_id = $onePr->push_post_schedule_id;
                $prov->save();
            } else {
                $pushPostSchedule = PushPostSchedule::create([
                    'application_id' => $prov->application_id,
                    'post_id' => $prov->post_id,
                    'location_id' => $prov->location_id,
                    'type' => $prov->type,
                    'sent_time' => $prov->created_at,
                    'is_sent' => true
                ]);
                $id = $pushPostSchedule->id;
                $prov->push_post_schedule_id = $id;
                $prov->save();
            }
        }
        return true;
    }
}


