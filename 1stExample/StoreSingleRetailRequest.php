<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSingleRetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'traderId' => 'required|string',
            'sourceId' => 'required|string',
            'cashdeskId' => 'required|string',
            'cardId' => 'required|string',

            'order.orderNumber' => 'required|string',
            'order.fiscalNumber' => 'required|integer',
            'order.orderTime' => 'required|string',

            'order.items.*' => 'required|array',
            'order.items.*.name' => 'required|string',
            'order.items.*.code' => 'required|integer',
            'order.items.*.category' => 'required|string',
            'order.items.*.qty' => 'required',
            'order.items.*.price' => 'required',
            'order.items.*.priceWithDiscount' => 'required',

            'order.items.*.discounts' => 'array',
            'order.items.*.discounts.src' => 'string',
            'order.items.*.discounts.type' => 'string',
            'order.items.*.discounts.value' => 'string',

            'order.totalPrice' => 'required',
            'order.totalPriceWithDiscount' => '',
            'order.percentTax' => 'required',
            'order.totalTax' => 'required',
            'order.paymentForm' => 'required',
        ];
    }
}
