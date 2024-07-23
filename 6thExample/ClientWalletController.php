<?php


use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\Wallet\AddBonusesRequest;
use App\Models\Client\Client;
use App\Services\ClientWalletService;

class ClientWalletController extends Controller
{
    /**
     * @var ClientWalletService
     */
    protected $service;

    /**
     * @param ClientWalletService $service
     */
    public function __construct(ClientWalletService $service)
    {
        $this->service = $service;
    }

    /**
     * @OA\Post (
     *     path="/clients/{id}/wallet/bonuses/add",
     *     tags={"Clients"},
     *     security={{"Bearer":{}}},
     *     summary="Обновить бонусный счет",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID Клиента",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                     @OA\Property(
     *                         property="restaurant_id",
     *                         type="integer",
     *                         description="ID ресторана"
     *                     ),
     *                     @OA\Property(
     *                         property="value",
     *                         type="integer",
     *                         description="Значение бонуса. может быть отрицательным"
     *                     ),
     *                     @OA\Property(
     *                         property="comment",
     *                         type="string",
     *                         description="Комментарий"
     *                     ),
     *                  required={"restaurant_id","value"},
     *                  example={
     *                      "restaurant_id": 1,
     *                      "value": 20,
     *                      "comment": "Comment"
     *                  }
     *              )
     *          )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="OK",
     *         content={
     *             @OA\MediaType(
     *                 mediaType="application/json",
     *                 @OA\Schema(
     *                     example={
     *                          "status": "Ok",
     *                          "message": "Bonus history update successfully."
     *                      }
     *                 )
     *             )
     *         }
     *     ),
     * )
     *
     * @param Client $client
     * @param AddBonusesRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addBonuses(Client $client, AddBonusesRequest $request)
    {
        $result = $this->service->addBonuses($client, $request->validated());
        return \App\Http\Controllers\Api\Client\response()->json($result->toArray(), $result->getResponseStatus());
    }
}
