<?php


use App\Models\CategoryAlias;
use App\Models\Location;
use App\Models\Post;
use App\Models\Source;
use App\Models\Test;
use App\Services\RegionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Vedmant\FeedReader\Facades\FeedReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ParseSourceChankJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
//    public $timeout = 60;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
//    public $failOnTimeout = true;


    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        $source = Source::where('url', $this->data['source']['url'])->first();
        if (!$source) {
            $source = Source::create([
                'url' => $this->data['source']['url'],
                'feed_id' => $this->data['post']['feed_id'],
                'name' => $this->data['source']['name'],
                'location_id' => $this->data['post']['location_id'],
                'is_visible' => 0,
            ]);
        }

        $locId = $this->data['post']['location_id'];
        $location = Cache::remember('location_' . $locId, 3600, function () use ($locId) {
            return Location::find($locId);
        });

        if ($location && $source) {
            $post = Post::where('title', $this->data['post']['title'])->first();
            if ($post) {
                $post->update([
                    'feed_id' => $this->data['post']['feed_id'],
                    'content' => $this->data['post']['content'],
                    'img_link' => $this->data['post']['img_link'],
                    'source_id' => $source->id,
                    'location_id' => $this->data['post']['location_id'],
                    'category_id' => $this->data['post']['category_id'],
                    'permalink' => $this->data['post']['permalink'],
                    'region_id' => $post->region_id ?? null
                ]);
            } else {
                $searchRegionService = resolve(RegionService::class);
                try {
                    $regionId = $searchRegionService->regionForNewPost($this->data['post']['title'], $this->data['post']['content']);
                } catch (\Exception $exception) {
                    Log::channel('custom_channel')->info($exception->getMessage() .' !!!!!!!!!!! reg: post ' . $this->data['post']['title']);
                }

                Post::create([
                    'title' => $this->data['post']['title'],
                    'feed_id' => $this->data['post']['feed_id'],
                    'content' => $this->data['post']['content'],
                    'img_link' => $this->data['post']['img_link'],
                    'source_id' => $source->id,
                    'location_id' => $this->data['post']['location_id'],
                    'category_id' => $this->data['post']['category_id'],
                    'permalink' => $this->data['post']['permalink'],
                    'region_id' => null
                        //$regionId ?? null
                ]);
            }
        }

    }
}
