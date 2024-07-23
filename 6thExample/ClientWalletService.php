<?php


use App\Console\Commands\ClientWalletBonuses;
use App\DTO\ApiResponse;
use App\Enums\ClientWalletEnum;
use App\Models\Client\BalanceWallet;
use App\Models\Client\Client;
use App\Models\Client\ClientWallet;
use App\Repositories\ClientWalletRepository;
use Illuminate\Support\Facades\Auth;

class ClientWalletService extends \App\Services\AbstractService
{

    public function __construct(private ClientWalletRepository $clientWalletRepository)
    {

    }


    public function addBonuses(Client $client, array $validated): ApiResponse
    {
        $wallet = $this->getWallet($client);
        $balanceWallet = $this->getBalanceWallet($wallet, $validated['restaurant_id']);
        $balanceWallet->histories()->create([
            'user_id' => Auth::user()->id ?? null,
            'value' => $validated['value'],
            'comment' => $validated['comment'] ?? null,
        ]);

        $balanceWallet->value += $validated['value'];
        if ($balanceWallet->value < 0) {
            $balanceWallet->value = 0;
        }

        $balanceWallet->save();

        return $this->setApiResponse()
            ->setMessage(\App\Services\__('Історію бонусів успішно оновлено.'))
            ->setOkStatus();
    }

    /**
     * @param Client $client
     * @return ClientWallet
     */
    private function getWallet(Client $client): ClientWallet
    {
        $wallet = $client->wallet()->first();
        if ($wallet) {
            return $wallet;
        }

        $wallet = new ClientWallet();
        $wallet->client_id = $client->id;
        $wallet->save();

        return $wallet;
    }

    /**
     * @param ClientWallet $wallet
     * @param int $restaurantID
     * @param string $type
     * @return BalanceWallet
     */
    private function getBalanceWallet(ClientWallet $wallet, int $restaurantID, string $type = ClientWalletEnum::BALANCE_TYPE_BONUS): BalanceWallet
    {
        $balanceWallet = $wallet->balances()
            ->where(['restaurant_id' => $restaurantID])
            ->where(['type' => $type])
            ->first();

        if ($balanceWallet) {
            return $balanceWallet;
        }

        $balanceWallet = new BalanceWallet();
        $balanceWallet->wallet_id = $wallet->id;
        $balanceWallet->restaurant_id = $restaurantID;
        $balanceWallet->type = $type;
        $balanceWallet->save();

        return $balanceWallet;
    }


    public function writeOffBonuses($clientID, $restaurantId, $value)
    {
        $clientWallet = $this->clientWalletRepository->one(['client_id' => $clientID]);
        BalanceWallet::query()->where([
            ['type', '=', ClientWalletEnum::BALANCE_TYPE_BONUS],
            ['wallet_id', '=', $clientWallet->id],
            ['restaurant_id', '=', $restaurantId],
        ])->decrement('value', $value);

    }
}
