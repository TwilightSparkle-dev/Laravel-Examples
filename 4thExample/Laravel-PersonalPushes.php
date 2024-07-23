<?php
//call in scheduler

namespace App\Console\Commands;

use App\Jobs\v3\PersonalIntentPushJob;
use App\Models\v3\DeviceSetting;
use App\Models\v3\IntentPersonalPushCompany;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Mockery\Exception;

class personalIntentChecker extends Command
{
    protected $signature = 'custom:personal_intent_checker';
    protected $description = 'Sending pesonal notifications to mobile devices with firebase';

    public function handle(): bool
    {
        try {
            if (IntentPersonalPushCompany::where('active', 1)->exists()) {
                $iPPCompanies = IntentPersonalPushCompany::where('active', 1)->with('location', 'application')->get();

                foreach ($iPPCompanies as $company) {
                    $location = $company->location;
                    $application = $company->application;
                    $push_days = json_decode($application->push_days);

                    if (!is_null($push_days)
                        && in_array(Carbon::now($location->timezone)->format('N'), $push_days)
                        && Carbon::now($location->timezone)->between($location->start, $location->end)) {

                        $posts = $company->intentPersonalPushCompanyPosts;
                        if ($location && $application && $posts
                            && DeviceSetting::where('location_id', $location->id)
                                ->where('application_id', $application->id)
                                ->where('fb_token', '!=', null)
                                ->where('intent_push_status', 1)
                                ->exists()) {

                            DeviceSetting::where('location_id', $location->id)
                                ->where('fb_token', '!=', null)
                                ->where('application_id', $application->id)
                                ->where('intent_push_status', 1)
                                ->chunk(500, function ($devices) use ($application) {
                                    $job = new PersonalIntentPushJob($application, $devices->pluck('fb_token')->toArray());
                                    $job->handle();
                                });
                        }
                    }
                }
            }
            return true;
        } catch (Exception $exception) {
            echo $exception->getMessage();
            return false;
        }
    }

}

<?php

namespace App\Jobs\v3;

use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use sngrl\PhpFirebaseCloudMessaging\Client;
use sngrl\PhpFirebaseCloudMessaging\Message;
use sngrl\PhpFirebaseCloudMessaging\Recipient\Device;

class PersonalIntentPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Application $app;
    protected array $tokens;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Application $application, array $tokens)
    {
        $this->app = $application;
        $this->tokens = $tokens;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->pushFirebase();
    }

    private function pushFirebase()
    {
        if (!$this->isValidForPush($this->app)) { exit(); }

            $data['post_id'] = substr(Carbon::now()->timestamp,-6);
            $data['type_notification'] = 'call';
            $data['push_type'] = 'personal_intent_push';

            $server_key = $this->app->firebase_server_key;
            $client = new Client();
            $client->setApiKey($server_key);
            $client->injectGuzzleHttpClient(new \GuzzleHttp\Client());

            $message = new Message();
            $message->setPriority('high');

            foreach ($this->tokens as $token) {
                $message->addRecipient(new Device($token));
            }

            $message->setData($data);
            $response = $client->send($message);
            $message = $response->getBody()->getContents();
//            Log::channel('request_log')->info('response: ' . $message);
            return $message;
    }

    private function isValidForPush($app) : bool
    {
        if (is_null($app->firebase_server_key)) {
            return false;
        }
        return true;
    }

   

}




