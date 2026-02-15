<?php

namespace App\Orchid\Filters;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class PaymentStatusFilter extends Filter
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
                ->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->toArray())
                ->empty('Все статусы')
                ->value($this->request->get('status'))
                ->title('Статус'),
        ];
    }
}
