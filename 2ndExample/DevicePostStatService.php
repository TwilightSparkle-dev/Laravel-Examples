<?php

namespace secondExample;

use App\Models\DevicePost;
use App\Models\DevicePostStat;
use Carbon\Carbon;

class DevicePostStatService
{

    public function makeStatByDate(Carbon $date, int $appId): DevicePostStat
    {
        $statsQuery = DevicePost::where('application_id', $appId)
            ->whereBetween('created_at', [
                $date->startOfDay()->format("Y-m-d H:i:s"),
                $date->endOfDay()->format("Y-m-d H:i:s")
            ])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(`like`) as likes')
            ->selectRaw('SUM(dislike) as dislikes')
            ->first();

        $statData = [
            'views' => $statsQuery->total ?? 0,
            'likes' => $statsQuery->likes ?? 0,
            'dislikes' => $statsQuery->dislikes ?? 0,
            'application_id' => $appId,
            'date' => $date->format("Y-m-d H:i:s")
        ];

        $devicePostStat = DevicePostStat::where('application_id', $appId)
            ->whereBetween('date', [
                $date->startOfDay()->format("Y-m-d H:i:s"),
                $date->endOfDay()->format("Y-m-d H:i:s")
            ])->first();

        if ($devicePostStat) {
            $devicePostStat->views = $statData['views'];
            $devicePostStat->likes = $statData['likes'];
            $devicePostStat->dislikes = $statData['dislikes'];
            $devicePostStat->save();

        } else {
            $devicePostStat = DevicePostStat::create($statData);
        }

         return $devicePostStat;
    }
}
