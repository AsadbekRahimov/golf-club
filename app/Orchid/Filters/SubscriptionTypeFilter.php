<?php

namespace App\Orchid\Filters;

use App\Enums\SubscriptionType;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class SubscriptionTypeFilter extends Filter
{
    public function name(): string
    {
        return 'Тип подписки';
    }

    public function parameters(): array
    {
        return ['subscription_type'];
    }

    public function run(Builder $builder): Builder
    {
        return $builder->where('subscription_type', $this->request->get('subscription_type'));
    }

    public function display(): array
    {
        return [
            Select::make('subscription_type')
                ->options(collect(SubscriptionType::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->toArray())
                ->empty('Все типы')
                ->value($this->request->get('subscription_type'))
                ->title('Тип подписки'),
        ];
    }
}
