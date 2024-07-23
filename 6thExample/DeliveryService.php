<?php


use App\Enums\CityEnum;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\iiko\CourierIiko;
use App\Models\Location;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\AbstractService;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryService extends AbstractService
{

    public function __construct()
    {

    }

    /**
     * @param string $courierUuid
     * @param int $userId
     * @param array $validated
     * @return array
     */
    public function store(User $user, array $validated): array
    {
        $success = false;
        DB::beginTransaction();
        Log::channel('mobile_courier')->info($user->courier->id . ' | Створення поїздки | ' . json_encode($validated));
        try {
            $this->deleteExistsDeliveryOrders($validated['orders']);
            $location = Location::where('id', $validated['location_id'])->first();
            $delivery = new Delivery();
            $delivery->location_id = $validated['location_id'];
            $delivery->courier_id = $user->courier->id;
            $delivery->user_id = $user->id;
            $delivery->kitchen_code = $location->kitchen_code ?? $user->kitchen_code;
            $delivery->started_at = date('Y-m-d H:i:s');
            if ($delivery->save()) {
                $success = $this->createRelatedDeliveryOrders($delivery, $validated);
                $success = $success && $this->setCourierStatus($user->courier, $delivery->id);
                OrderService::updateStatusDeliveringOrders($validated['orders'], $user->id);
                Log::channel('mobile_courier')->info($user->courier->id . ' |  Поїздка створення:  ' . $delivery->id);
            }
        } catch (\Exception $e) {
            Log::channel('mobile_courier')->info($user->courier->id . ' |  Помилка створення поїздки:  ' . $e->getMessage());
            $success = false;
        }

        if ($success) {
            DB::commit();
        } else {
            DB::rollBack();
        }

        return [
            'success' => $success
        ];
    }

    /**
     * @param Delivery $delivery
     * @param array $validated
     * @return bool
     */
    private function createRelatedDeliveryOrders(Delivery $delivery, array $validated): bool
    {
        return (bool)$delivery->orders()
            ->createMany(array_map(function ($orderId) {
                $order = Order::where('id', $orderId)->with(['address', 'address.city'])->first();
                return [
                    'restaurant' => $order->restaurant,
                    'order_id' => $order->id,
                    'address' => $this->formatAddress($order->address->toArray()),
                    'range_type' => $this->getRangeType($order->address->toArray()),
                ];
            }, $validated['orders']));
    }

    /**
     * @param array $address
     * @return string
     */
    private function formatAddress(array $address): string
    {
        $formattedAddressString = "{$address['street']} ";
        $formattedAddressString .= "{$address['house_number']}, ";
        $formattedAddressString .= "{$address['city']['name']}";
        return $formattedAddressString;
    }

    /**
     * @param array $address
     * @return string
     */
    private function getRangeType(array $address): string
    {
        $cityType = $address['city']['type'] ?? 1;
        return ($cityType == CityEnum::TYPE_CITY)
            ? Delivery::RANGE_TYPE_WITHIN_CITY
            : Delivery::RANGE_TYPE_OUTSIDE_CITY;
    }

    /**
     * @param int $userId
     * @return mixed
     */
    private function setCourierStatus(CourierIiko $courier, int $deliveryId): bool
    {
        $courier->status = User::COURIER_STATUS_ON_DELIVERY;
        $courier->current_delivery_id = $deliveryId;
        return (bool)$courier->save();
    }

    private function getCourierStatus(int $userId)
    {
        $courierRecord = CourierIiko::where('user_id', '=', $userId)->first();
        return $courierRecord->status;
    }

    /**
     * @return Collection|null
     */
    public function existingDeliveryForCourier(): ?Collection
    {
        return Delivery::where('id', Auth::user()->courierCurrentDeliveryId)->first()
            ?->orders;
    }

    public function checkDeliveryOrders($deliveryOrders)
    {
        $orderIds = \App\Services\Courier\collect($deliveryOrders)->pluck('order_uuid');
        return DeliveryOrder::whereIn('iiko_order_id', $orderIds)->exists();
    }

    public function deleteExistsDeliveryOrders($deliveryOrders)
    {
        $orderIds = \App\Services\Courier\collect($deliveryOrders)->pluck('id');
        DeliveryOrder::whereIn('order_id', $orderIds)->delete();
    }
}
