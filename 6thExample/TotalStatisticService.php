<?php

use App\Models\Location;
use App\Models\Order\Order;
use App\Models\Restaurant;
use App\Repositories\TotalStatisticRepository;
use App\Services\AbstractService;
use App\Transformers\Report\ReportTransformer;
use Illuminate\Support\Carbon;

class TotalStatisticService extends AbstractService
{

    public function __construct(private TotalStatisticRepository $totalStatisticRepository)
    {
    }

    public function updateTotalStatistic()
    {
        $restaurants = Restaurant::where('status', 1)->get();
        foreach ($restaurants as $restaurant) {
            $locations = Location::where('restaurant', $restaurant->code)->with(['city'])->get();
            foreach ($locations as $location) {
                $orders = Order::where([
                    ['restaurant', '=', $location->restaurant ?? null],
                    ['city_id', '=', $location->city->id ?? null],
                    ['kitchen_code', '=', $location->kitchen_code ?? null],
                ])->with(['iikoPaymentsFromTotalStatistic', 'iikoPaymentsFromTotalStatisticWithoutTechOrder'])->wherehas('iikoPaymentsFromTotalStatisticWithoutTechOrder')->whereDate('created_at', Carbon::today())->get();
                $countOrders = $orders->count() ?? 0;
                if ($countOrders) {
                    $cost = $orders->sum(function ($order) {
                        return $order->iikoPaymentsFromTotalStatistic->sum('sum') ?? 0;
                    });
                    $average_check = $cost / $countOrders;
                    $circulation = 0;
                    $data = [
                        'restaurant_code' => $location->restaurant,
                        'kitchen_code' => $location->kitchen_code,
                        'city_code' => $location->city_sync_id,
                        'order_count' => $countOrders,
                        'average_check' => $average_check,
                        'cost' => $cost,
                        'circulation' => $circulation,
                        'created_at' => \App\Services\Statistics\now(),
                        'updated_at' => \App\Services\Statistics\now(),
                    ];
                    $this->totalStatisticRepository->updateOrCreate($data, Carbon::today());
                }
            }
        }
        return true;
    }


    public function showTotals(array $validated)
    {
        $results = $this->totalStatisticRepository->showTotals($validated);

        return ReportTransformer::totalStatis($results);
    }

}
