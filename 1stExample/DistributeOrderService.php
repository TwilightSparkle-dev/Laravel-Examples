<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Category\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class DistributeOrderService
{
    public function create($request): int
    {
        $order = Order::create(
            [
                'traderId'                  => $request['traderId'],
                'sourceId'                  => $request['sourceId'],
                'cashdeskId'                => $request['cashdeskId'],
                'cardId'                    => $request['cardId'],

                'orderNumber'               => $request['order']['orderNumber'],
                'fiscalNumber'              => $request['order']['fiscalNumber'],
                'orderTime'                 => $request['order']['orderTime'],
                'totalPrice'                => $request['order']['totalPrice'],
                'totalPriceWithDiscount'    => $request['order']['totalPriceWithDiscount'],
                'percentTax'                => $request['order']['percentTax'],
                'totalTax'                  => $request['order']['totalTax'],
                'paymentForm'               => $request['order']['paymentForm'],
            ]
        );
        foreach ($request['order']['items'] as $product) {

            $searchCategory = $this->searchCategorySynonym($product['category']);
            $searchProduct = $this->searchProductSynonym($product['name']);

            if ($searchCategory === null && $searchProduct === null) {
                $defaultCategory = Category::where('name', 'Не визначені')->first();
                $newCategory = Category::create(
                    [
                        'name'          => $product['category'],
                        'parent_id'     => $defaultCategory->id,
                        'original_id'   => null,
                        'type'          => 1,
                    ]
                );
                $newProduct = Product::create(
                    [
                        'name'          => $product['name'],
                        'category_id'   => $newCategory->id,
                        'original_id'   => null,
                        'type'          => 1,
                    ]
                );
                $productId = $newProduct->id;

            } else if ($searchCategory === null && $searchProduct !== null) {
                $originalProductForNewCategory = $this->getOriginalProduct($searchProduct);

                $newCategorySynonym = Category::create(
                    [
                        'name'          => $product['category'],
                        'parent_id'     => null,
                        'original_id'   => $originalProductForNewCategory->category_id,
                        'type'          => 2,
                    ]
                );
                $productId = $originalProductForNewCategory->id;
            } else if ($searchCategory !== null && $searchProduct === null) {
                $originalCategoryForNewProduct = $this->getOriginalCategory($searchCategory);

                // check - is bottom category
                if ($originalCategoryForNewProduct->subCategories()->count() == 0) {
                    $productCategory = $originalCategoryForNewProduct->id;
                }else {
                    $defaultCategory = Category::where('name', 'Не визначені')->first();
                    $productCategory = $defaultCategory->id;
                }

                $newProduct = Product::create(
                    [
                        'name'          => $product['name'],
                        'category_id'   => $productCategory,
                        'original_id'   => null,
                        'type'          => 1,
                    ]
                );
                $productId = $newProduct->id;
            } else {
                $originalProduct = $this->getOriginalProduct($searchProduct);
                $productId = $originalProduct->id;
            }

            $pivot = [];
            array_push($pivot, [
                'order_id'          => $order->id,
                'product_id'        => $productId,
                'name'              => $product['name'],
                'price'             => $product['price'],
                'priceWithDiscount' => $product['priceWithDiscount'],
                'qty'               => $product['qty'],
                'discountSrc'       => $product['discounts'][0]['src'] ?? null,
                'discountType'      => $product['discounts'][0]['type'] ?? null,
                'discountValue'     => $product['discounts'][0]['value'] ?? null,
                'productCode'       => $product['code']
            ]);


            OrderProduct::insert($pivot);
        }

        return $order->id;
    }

    public function createList(array $data): bool
    {
        foreach ($data['orders'] as $order) {
            $this->create($order);
        }
        return true;
    }

    public function cancelOrder(array $data): int
    {
        $order = Order::where(
            [
                ['traderId',    '=', $data['traderId']],
                ['sourceId',    '=', $data['sourceId']],
                ['orderNumber', '=', $data['orderNumber']]
            ]
        )
            ->delete();

        return $order;
    }

    public function searchCategorySynonym(string $categorySynonym)
    {
        $synonym = Category::where('name', 'ilike', $categorySynonym)->first();
        return $synonym;
    }

    public function searchProductSynonym(string $productSynonym)
    {
        $synonym = Product::where('name', 'ilike', $productSynonym)->first();
        return $synonym;
    }

    public function saveCategoryList(array $data)
    {
        $defaultCategory = Category::where('name', 'Не визначені')->first();

        foreach ($data[0] as $category) {

            if ($category['parent'] !== null && $category['parent'] !== 'null' && $category['parent'] !== '') {
                $parent = Category::where('name', 'ilike', $category['parent'])->first();

                if ($parent !== null) {
                    $parent = $this->getOriginalCategory($parent);

                    if ($parent->synonyms()->count() == 0 && $parent->products()->count() == 0) {
                        $parentId = $parent->id;
                    } else {
                        $parentId = $defaultCategory->id;
                    }
                } else {

                    $newCategoryParent = Category::create(
                        [
                            'name'          => $category['parent'],
                            'parent_id'     => $defaultCategory->id,
                            'original_id'   => null,
                            'type'          => 1,
                        ]
                    );
                    $parentId = $newCategoryParent;
                }
            }

            $newCategory = Category::where('name', 'ilike', $category['name'])->first();

            if ($newCategory == null) {

                Category::create(
                    [
                        'name'          => $category['name'],
                        'parent_id'     => $parentId ?? null,
                        'original_id'   => null,
                        'type'          => 1,
                    ]
                );
            }
        }
        return true;
    }

    public function getOriginalProduct($product)
    {
        if ($product->original_id === null) {
            return $product;
        } else {
            return $product->product;
        }
    }

    public function getOriginalCategory($category)
    {
        if ($category->original_id === null) {
            return $category;
        } else {
            return $category->original;
        }
    }
}
