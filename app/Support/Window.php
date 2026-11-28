<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

class Window
{
    private const MAX_STEPS = 20;

    public function __construct(public readonly int $size) {}

    public static function fromRequest(Request $request, int $step): self
    {
        $requested = min(max($request->integer('show', $step), $step), $step * self::MAX_STEPS);

        return new self((int) ceil($requested / $step) * $step);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>|Relation<TModel, *, *>  $query
     * @param  Closure(TModel): mixed  $present
     * @return array{data: array<int, mixed>, hasMore: bool}
     */
    public function of(Builder|Relation $query, Closure $present): array
    {
        $items = $query->limit($this->size + 1)->get();

        return [
            'data' => $items->take($this->size)->map($present)->values()->all(),
            'hasMore' => $items->count() > $this->size,
        ];
    }
}
