<?php


use App\Jobs\ParseSourceChankJob;
use App\Models\CategoryAlias;
use App\Models\Location;
use App\Models\Source;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Storage;
use Vedmant\FeedReader\Facades\FeedReader;

class ParsePostsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:parse_posts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('memory_limit', -1);
        $counter = 0;

        $start = microtime(true);

        $locations = Location::where('active', 1)->with('active_feeds')->orderBy('id', 'asc')->get();
        $location = $locations->where('next_in_queue', TRUE)->first();
        $location->next_in_queue = NULL;
        $location->save();
        /* Set Next Location to Parse */
        $next = $locations->where('id', '>', $location->id)->first();
        if (is_null($next)) {
            $next = $locations->first();
        }

        $next->next_in_queue = 1;
        $next->save();

        $categoryAliases = CategoryAlias::all();
        $feedsAr = $location->feeds->toArray();

        $feedUrls = array_column($feedsAr, 'URL');

        $f = FeedReader::read($feedUrls);

        $f->set_item_limit(\App\Console\Commands\setting('rss.posts_per_rss'));
        foreach ($f->get_items(0, $f->get_item_quantity()) as $item) {
            if (is_null($item)) {
                continue;
            }

            foreach ($feedsAr as $feedItem) {
                if ($feedItem['URL'] === $item->get_feed()->feed_url) {
                    $feed = $feedItem;
                    break;
                }
            }

            $category_id = $feed['category_id'];

            if (is_null($category_id)) {
                $category = $item->get_category();
                if (is_null($category)) {
                    continue;
                }

                $alias = $categoryAliases->where('feed_id', $feed['id'])->where('name', $category->term)->first();
                if (is_null($alias)) {
                    continue;
                }
                $category_id = $alias->category_id;
            }

            if (is_null($item->get_permalink())) {
                continue;
            }

            $permalink = parse_url($item->get_permalink());


            $dumpPermalink = $permalink['scheme'] . '://' . $permalink['host'];
            $dumpPermalinkHost = $permalink['host'];
            $permalink = $permalink['scheme'] . '://' . $permalink['host'] . $permalink['path'];

            $title = htmlspecialchars_decode(mb_substr(strip_tags($item->get_title()), 0, 255));
            if (empty($title)) {
                continue;
            }
            $content = htmlspecialchars_decode(mb_substr(strip_tags($item->get_description()), 0, 300));
            if (empty($content)) {
                continue;
            }
            $img_link = $item->get_enclosure()->get_link();
            if (is_null($img_link)) {
                continue;
            }

            ParseSourceChankJob::dispatch([
                'source' => [
                    'url' => $dumpPermalink,
                    'name' => $dumpPermalinkHost,
                ],
                'post' => [
                    'title' => htmlspecialchars_decode($title),
                    'permalink' => $permalink,
                    'category_id' => $category_id,
                    'feed_id' => $feed['id'],
                    'content' => htmlspecialchars_decode($content),
                    'img_link' => $img_link,
                    'location_id' => $location->id
                ]
            ]);
        }

        return Command::SUCCESS;
    }
}
