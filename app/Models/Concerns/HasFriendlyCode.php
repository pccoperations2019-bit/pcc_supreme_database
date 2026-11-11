<?php

namespace App\Models\Concerns;

use App\Support\Sequence;
use Illuminate\Support\Str;

trait HasFriendlyCode
{
    private static function buildFriendlyPrefix(string $category, ?string $subCategory = ''): string
    {
        $prefix = '';
        $category = preg_replace('/[^A-Za-z ]/', '', $category);
        $subCategory = preg_replace('/[^A-Za-z ]/', '', $subCategory);

        if (Str::of($category)->explode(' ')->count() >= 3) {
            $words = Str::of($category)->explode(' ');
            foreach ($words as $word) {
                $prefix .= Str::of($word)->upper()->substr(0, 1);
            }
        } else {
            $prefix .= Str::of($category)->upper()->substr(0, 3);
        }

        if (! in_array($subCategory, [null, '', '0'], true)) {
            $prefix .= '-';
        }
        if (Str::of($subCategory)->explode(' ')->count() >= 3) {
            $words = Str::of($subCategory)->explode(' ');
            foreach ($words as $word) {
                $prefix .= Str::of($word)->upper()->substr(0, 1);
            }
        } elseif ($subCategory !== null && $subCategory !== '') {
            $prefix .= Str::of($subCategory)->upper()->substr(0, 3);
        }

        return $prefix;
    }

    public static function generateCode(string $category, ?string $subCategory = ''): string
    {
        $prefix = self::buildFriendlyPrefix($category, $subCategory);

        $latestCode = static::query()
            ->where('code', 'like', "{$prefix}-%")
            ->orderByDesc('code')
            ->value('code');

        if (is_string($latestCode) && $latestCode !== '') {
            $tailValue = (int) substr($latestCode, -5);
            if ($tailValue > 0) {
                Sequence::ensureAtLeast($prefix, $tailValue);
            }
        }

        $next = Sequence::next($prefix);
        $tail = str_pad((string) $next, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$tail}";
    }
}
