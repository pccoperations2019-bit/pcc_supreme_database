<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

class PartnersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $key = $request->query('key');
        $centerIds = $this->normalizeIds($request->query('center_id'));

        $user = $request->user();

        $query = Partner::query()
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->when($centerIds !== [], fn ($query) => $query->whereIn('center_id', $centerIds))
            ->when($key, function ($query) use ($key) {
                // Match each term independently so a partial name
                // ("San Fernando Rice") still finds "San Fernando Rice Farmers Association".
                foreach ($this->searchTerms((string) $key) as $term) {
                    $query->where(function ($search) use ($term) {
                        $search->where('name', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%");
                    });
                }
            })
            ->orderBy('name')
            ->limit(20);

        return $query->get()
            ->map(function ($partner) {
                return [
                    'value' => $partner->id,
                    'label' => $partner->name,
                ];
            });
    }

    /**
     * Split a search key into the individual terms that must all match.
     *
     * @return array<int, string>
     */
    private function searchTerms(string $key): array
    {
        $terms = preg_split('/\s+/', trim($key), -1, PREG_SPLIT_NO_EMPTY);

        return $terms === false ? [] : array_values($terms);
    }

    /**
     * Normalize a center filter into usable ids.
     *
     * Report filters send either a comma-separated string (`center_id=1,2`)
     * or an array (`center_id[]=1`), and use `all` as the "no restriction"
     * sentinel — that must not be treated as an id.
     *
     * @return array<int, string>
     */
    private function normalizeIds(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            $values,
        ), static fn (string $item) => $item !== '' && $item !== 'all' && $item !== '0'));
    }
}
