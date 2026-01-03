<?php

namespace App\Orchid\Layouts\Charts;

use Orchid\Screen\Layouts\Chart;

class RegistrationsChart extends Chart
{
    protected $title = '📈 Регистрации клиентов';
    
    protected $target = 'registrations';
    
    protected $type = self::TYPE_LINE;
    
    protected $height = 250;
    
    protected $colors = ['#4BC0C0'];
    
    protected $export = false;
}
