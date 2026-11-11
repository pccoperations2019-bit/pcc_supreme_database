<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use App\Models\Farmer;
use Illuminate\Http\Request;

class FarmersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $key = $request->query('key');
        $centerIds = $this->normalizeIds($request->query('center_id'));
        $partnerIds = $this->normalizeIds($request->query('partner_id'));

        $user = $request->user();

        return Farmer::query()
            ->when(
                $user && $user->isCenterScoped(),
                fn ($query) => $query->where('center_id', $user->center_id)
            )
            ->when($centerIds !== [], fn ($query) => $query->whereIn('center_id', $centerIds))
            ->when($partnerIds !== [], fn ($query) => $query->whereIn('partner_id', $partnerIds))
            ->when($key, function ($query) use ($key) {
                // Search each whitespace-separated term independently so a full name
                // ("Aaron Ramos") matches even though the parts live in separate columns.
                foreach ($this->searchTerms((string) $key) as $term) {
                    $query->where(function ($search) use ($term) {
                        $search->where('first_name', 'like', "%{$term}%")
                            ->orWhere('middle_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%");
                    });
                }
            })
            ->limit(20)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function ($farmer) {
                return [
                    'value' => $farmer->id,
                    'label' => $this->fullName($farmer),
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

    private function fullName(Farmer $farmer): string
    {
        return trim(implode(' ', array_filter([
            $farmer->first_name,
            $farmer->middle_name,
            $farmer->last_name,
        ])));
    }

    /**
     * Normalize a filter query value into usable ids.
     *
     * Report filters send either a comma-separated string (`center_id=1,2`)
     * or an array (`partner_id[]=1`), and use `all` as the "no restriction"
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
