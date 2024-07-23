<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelOrderRetailRequest;
use App\Http\Requests\StoreListRetailRequest;
use App\Http\Requests\StoreSingleRetailRequest;
use App\Http\Requests\CategoryImportRetailRequest;
use App\Services\DistributeOrderService;
use Illuminate\Support\Facades\Log;

class RetailController extends Controller
{
    protected $distributeOrderService;

    public function __construct(DistributeOrderService $distributeOrderService)
    {
        $this->distributeOrderService = $distributeOrderService;
    }

    public function storeSingle(StoreSingleRetailRequest $request)
    {


        $orderId = $this->distributeOrderService->create($request);

        return response(
            [
                'status'    => 'OK',
                'requestId' => $orderId
            ],
            200)
            ->header('Content-Type', 'application/json');
    }

    public function storeList(StoreListRetailRequest $request)
    {
        $this->distributeOrderService->createList($request->validated());

        return response(
            [
                'status'    => 'OK',
            ],
            200)
            ->header('Content-Type', 'application/json');
    }

    public function cancelOrder(CancelOrderRetailRequest $request)
    {
        $result = $this->distributeOrderService->cancelOrder($request->validated());

        if ($result === 0) {
            $message = 'Order not found';
        } else {
            $message = 'Order was deleted';
        }

        return response(
            [
                'status'        => 'OK',
                'message'       => $message,
                'orderNumber'   => $request->orderNumber
            ],
            200)
            ->header('Content-Type', 'application/json');
    }

    public function categoryImport(CategoryImportRetailRequest $request)
    {

        $result = $this->distributeOrderService->saveCategoryList($request->validated());

        $message = 'Категорії успішно импортовано!';

        return response(
            [
                'status'        => 'OK',
                'success'       => $message,
            ],
            200)
            ->header('Content-Type', 'application/json');
    }
}
