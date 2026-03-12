<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Input;

class ClientSearchFilter extends Filter
{
    public function name(): string
    {
        return 'Поиск';
    }

    public function parameters(): array
    {
        return ['search'];
    }

    public function run(Builder $builder): Builder
    {
        $search = $this->request->get('search');

        return $builder->where(function (Builder $q) use ($search) {
            $q->where('phone_number', 'ilike', "%{$search}%")
              ->orWhere('full_name', 'ilike', "%{$search}%")
              ->orWhere('first_name', 'ilike', "%{$search}%")
              ->orWhere('last_name', 'ilike', "%{$search}%")
              ->orWhere('username', 'ilike', "%{$search}%");
        });
    }

    public function display(): array
    {
        return [
            Input::make('search')
                ->type('text')
                ->placeholder('Телефон, имя, username...')
                ->value($this->request->get('search'))
                ->title('Поиск'),
        ];
    }
}
