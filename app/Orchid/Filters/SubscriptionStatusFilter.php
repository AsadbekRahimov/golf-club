<?php

namespace App\Orchid\Filters;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class SubscriptionStatusFilter extends Filter
{
    public function name(): string
    {
        return 'Статус';
    }

    public function parameters(): array
    {
        return ['status'];
    }

    public function run(Builder $builder): Builder
    {
        return $builder->where('status', $this->request->get('status'));
    }

    public function display(): array
    {
        return [
            Select::make('status')
                ->options(collect(SubscriptionStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->toArray())
                ->empty('Все статусы')
                ->value($this->request->get('status'))
                ->title('Статус'),
        ];
    }
}
