<?php


use App\Http\Requests\Auth\LoginAppWithGoogleRequest;
use App\Http\Requests\DeviceRequest;
use App\Http\Resources\v2\UserResource;
use App\Http\Resources\v2\UserSettingsResource;
use App\Models\Device;
use App\Models\InstallReferrer;
use App\Services\ApplicationService;
use App\Services\DeviceService;
use App\Services\UserSettingService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    protected ApplicationService $applicationService;
    protected DeviceService $deviceService;
    protected UserSettingService $userSettingService;

    public function __construct(ApplicationService $applicationService,
                                DeviceService      $deviceService,
                                UserSettingService $userSettingService)
    {
        $this->applicationService = $applicationService;
        $this->deviceService = $deviceService;
        $this->userSettingService = $userSettingService;
    }

    public function login(LoginAppWithGoogleRequest $request)
    {
        $appSecrets = $this->applicationService->getSettingsByHost($request->getSchemeAndHttpHost());

        if ($appSecrets
            && $appSecrets['google']['client_id'] !== null
            && $appSecrets['google']['client_id'] !== '') {

            $requestData = $request->validated();
            $client = new \Google_Client(['client_id' => $appSecrets['google']['client_id']]);
            $payload = $client->verifyIdToken($requestData['id_token']);

            if ($payload) {
                $user = $this->applicationService->getOrCreateGoogleUser($payload);

                $device = $this->deviceService->getDevice(
                    $this->deviceService->getRequestDevicePropertiesOrNull($requestData)
                );
                //ref - move user in serv
                $userSettings = $this->userSettingService->getSettings($device);
                //REF move user in serv
                if (is_null($userSettings->user_id)) {
                    $userSettings->user_id = $user->id;
                    $userSettings->save();
                }
                //REF move user in serv
                if (Cache::has('settings_device_' . $requestData['device_id'])) {
                    Cache::forget('settings_device_' . $requestData['device_id']);
                }
                Auth::login($user);

                $json['user'] = new UserResource($user);
                $json['settings'] = new UserSettingsResource($userSettings);
                $json['token'] = $user->createToken("API TOKEN")->plainTextToken;
                $status = 200;

                return response()->json($json, $status);
            }
        }

        $json['message'] = 'The given data was invalid.';
        $json['errors']['id_token'] = 'Invalid ID token.';
        $status = 422;

        return response()->json($json, $status);
    }

    public function loginOld(Request $request)
    {
        $request->validate(['id_token' => 'string|required', 'device_id' => 'string|required']);
        $id_token = request()->get('id_token');

        $client = new \Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
        $payload = $client->verifyIdToken($id_token);

        if ($payload)
        {
            $user = User::firstOrCreate(
                [
                    'email' => $payload['email'],
                ],
                [
                    'name' => $payload['name'],
                    'login' => explode('@', $payload['email'])[0],
                    'avatar' => $payload['picture'],
                ]
            );

            $settings = UserSetting::getSettings();
            if (is_null($settings->user_id))
            {
                $settings->user_id = $user->id;
                $settings->save();
            }

            if (cache()->has('settings_device_' . $request->device_id))
            {
                cache()->forget('settings_device_' . $request->device_id);
            }

            Auth::login($user);

            $json['user'] = new UserResource($user);
            $json['settings'] = new UserSettingsResource($settings);
            $json['token'] = $user->createToken("API TOKEN")->plainTextToken;
            $status = 200;
        } else {
            $json['message'] = 'The given data was invalid.';
            $json['errors']['id_token'] = 'Invalid ID token.';
            $status = 422;
        }

        return response()->json($json, $status);
    }


}
