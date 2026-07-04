<?php

namespace App\Support\Pagination;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class PaginatesCollections
{
    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  Collection<TKey, TValue>  $items
     * @return LengthAwarePaginator<TValue>
     */
    public static function paginate(Collection $items, PaginationOptions $pagination, Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: $items->forPage($pagination->page, $pagination->perPage)->values(),
            total: $items->count(),
            perPage: $pagination->perPage,
            currentPage: $pagination->page,
            options: [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }
}
