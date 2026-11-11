<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\MilkFeedingProgram;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $type = $request->query('type', 'addresses');

        if (in_array($type, ['cities', 'provinces', 'districts'], true)) {
            return $this->getAddressFieldValues(
                $type,
                $request->query('key'),
                $request->query('addressable_type')
            );
        }

        if ($type === 'mfp-divisions') {
            return $this->getMilkFeedingProgramDivisions($request->query('key'));
        }

        $addressableType = $request->query('addressable_type', 'Center');

        return Address::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('addressable_type', $addressableType)
            ->when($request->get('addressable_id'), function ($query) use ($request) {
                $query->where('addressable_id', $request->input('addressable_id'));
            })
            ->get();
    }

    private function getAddressFieldValues(string $type, ?string $key, ?string $addressableType = null): array
    {
        $field = match ($type) {
            'cities' => 'city',
            'provinces' => 'province',
            'districts' => 'district',
            default => throw new \InvalidArgumentException("Invalid type: {$type}"),
        };

        $query = Address::query()
            ->distinct()
            ->select($field)
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->when($addressableType, function ($query) use ($addressableType) {
                $query->where('addressable_type', $addressableType);
            })
            ->when($key, function ($query) use ($field, $key) {
                $query->where($field, 'like', "%{$key}%");
            })
            ->orderBy($field)
            ->limit(50);

        return $query->get()
            ->map(function ($address) use ($field) {
                return [
                    'value' => $address->$field,
                    'label' => $address->$field,
                ];
            })->toArray();
    }

    private function getMilkFeedingProgramDivisions(?string $key): array
    {
        return MilkFeedingProgram::query()
            ->distinct()
            ->select('division')
            ->whereNotNull('division')
            ->where('division', '!=', '')
            ->when($key, function ($query) use ($key) {
                $query->where('division', 'like', "%{$key}%");
            })
            ->orderBy('division')
            ->limit(50)
            ->get()
            ->map(fn ($row) => ['value' => $row->division, 'label' => $row->division])
            ->toArray();
    }
}
