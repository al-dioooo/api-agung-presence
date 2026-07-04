<?php

namespace App\Support\Pagination;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

final readonly class PaginationOptions
{
    public const DEFAULT_PAGE = 1;

    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public function __construct(
        public int $page = self::DEFAULT_PAGE,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public static function rules(bool $allowLimitAlias = false): array
    {
        $rules = [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];

        if ($allowLimitAlias) {
            $rules['limit'] = ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE];
        }

        return $rules;
    }

    public static function fromRequest(Request $request, bool $allowLimitAlias = false): self
    {
        $page = $request->integer('page', self::DEFAULT_PAGE);
        $perPage = $request->integer(
            'per_page',
            $allowLimitAlias ? $request->integer('limit', self::DEFAULT_PER_PAGE) : self::DEFAULT_PER_PAGE,
        );

        return new self(
            page: max(self::DEFAULT_PAGE, $page),
            perPage: min(max(1, $perPage), self::MAX_PER_PAGE),
        );
    }
}
