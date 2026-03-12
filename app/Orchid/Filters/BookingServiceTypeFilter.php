<?php

namespace App\Orchid\Filters;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class BookingServiceTypeFilter extends Filter
{
    public function name(): string
    {
        return 'Тип услуги';
    }

    public function parameters(): array
    {
        return ['service_type'];
    }

    public function run(Builder $builder): Builder
    {
        return $builder->where('service_type', $this->request->get('service_type'));
    }

    public function display(): array
    {
        return [
            Select::make('service_type')
                ->options(collect(ServiceType::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->toArray())
                ->empty('Все типы')
                ->value($this->request->get('service_type'))
                ->title('Тип услуги'),
        ];
    }
}
