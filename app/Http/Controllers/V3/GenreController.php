<?php

namespace App\Http\Controllers\V3;

use App\Http\HttpHelper;
use Illuminate\Support\Facades\DB;
use Jikan\Request\Genre\AnimeGenreRequest;
use Jikan\Request\Genre\MangaGenreRequest;
use Illuminate\Http\Request;

class GenreController extends Controller
{

    private $request;
    const MAX_RESULTS_PER_PAGE = 50;

    public function anime(Request $request, int $id, int $page = 1)
    {
        $this->request = $request;

        $results = DB::table('anime')
            ->where('genres.mal_id', $id)
            ->orderBy('title');

        $results = $results
            ->paginate(
                self::MAX_RESULTS_PER_PAGE,
                [
                    'mal_id', 'url', 'title', 'image_url', 'synopsis', 'type', 'airing_start', 'episodes', 'members', 'genres', 'source', 'producers', 'score', 'licensors', 'rating'
                ],
                null,
                $page
            );

        $items = $this->applyBackwardsCompatibility($results);

        return response()->json($items);

//        $person = $this->jikan->getAnimeGenre(new AnimeGenreRequest($id, $page));
//        return response($this->serializer->serialize($person, 'json'));
    }

    public function manga(int $id, int $page = 1)
    {
        $person = $this->jikan->getMangaGenre(new MangaGenreRequest($id, $page));
        return response($this->serializer->serialize($person, 'json'));
    }

    private function applyBackwardsCompatibility($data)
    {
        $fingerprint = HttpHelper::resolveRequestFingerprint($this->request);

        $meta = [
            'request_hash' => $fingerprint,
            'request_cached' => true,
            'request_cache_expiry' => 0,
            'last_page' => $data->lastPage()
        ];

        $items = $data->items() ?? [];
        foreach ($items as &$item) {
            if (isset($item['aired']['from'])) {
                $item['airing_start'] = $item['aired']['from'];
            }

            $item['kids'] = false;
            if (isset($item['rating'])) {
                if ($item['rating'] === 'G - All Ages' || $item['rating'] === 'PG - Children') {
                    $item['kids'] = true;
                }
            }

            $item['r18'] = false;
            if (isset($item['rating'])) {
                if ($item['rating'] === 'R+ - Mild Nudity' || $item['rating'] === 'Rx - Hentai') {
                    $item['r18'] = true;
                }
            }

            unset($item['_id'], $item['oid'], $item['expiresAt'], $item['aired'], $item['published']);
        }

        $items = [
            'anime' => $items
        ];

        return $meta+$items;
    }
}