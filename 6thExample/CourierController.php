<?php


use App\Enums\CourierEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Couriers\UpdateStatusRequest;
use App\Http\Resources\DefaultCollection;
use App\Services\CourierService;
use Illuminate\Support\Facades\Auth;

class CourierController extends Controller
{

    public function __construct(private CourierService $courierService)
    {

    }

    public function resetStatusDelivery($userId)
    {
        try {
            $result = $this->courierService->resetStatusDelivery($userId);
            return \App\Http\Controllers\Api\Courier\response()->json($result->toArray(), $result->getResponseStatus());
        } catch (\Exception $exception) {
            return \App\Http\Controllers\Api\Courier\response()->json(['error' => $exception->getMessage()]);
        }
    }

    public function returnTransportTypes()
    {
        return new DefaultCollection(
            \App\Http\Controllers\Api\Courier\collect(CourierEnum::COURIER_TRANSPORT_TYPES)
        );
    }

    public function returnCourierStatuses()
    {
        return new DefaultCollection(
            \App\Http\Controllers\Api\Courier\collect(CourierEnum::COURIER_STATUSES)
        );
    }

    public function updateStatus(UpdateStatusRequest $request)
    {
        try {
            $result = $this->courierService->updateStatus(Auth::user(), $request->get('status'));
            return \App\Http\Controllers\Api\Courier\response()->json($result->toArray(), $result->getResponseStatus());
        } catch (\Exception $exception) {
            return \App\Http\Controllers\Api\Courier\response()->json(
                [
                    'success' => false,
                    'message' => "Помилка: ", $exception->getMessage()
                ]);
        }
    }

    public function courierStatus()
    {
        try {
            $result = $this->courierService->returnCourierStatus(Auth::user());
            return \App\Http\Controllers\Api\Courier\response()->json($result);
        } catch (\Exception $exception) {
            return \App\Http\Controllers\Api\Courier\response()->json(
                [
                    'success' => false,
                    'message' => "Помилка: ", $exception->getMessage()
                ]);
        }
    }

    public function courierInformation()
    {
        try {
            $result = $this->courierService->courierInformation(Auth::user());
            return \App\Http\Controllers\Api\Courier\response()->json($result);
        } catch (\Exception $exception) {
            return \App\Http\Controllers\Api\Courier\response()->json(
                [
                    'success' => false,
                    'message' => "Помилка: ", $exception->getMessage()
                ]);
        }
    }
}
