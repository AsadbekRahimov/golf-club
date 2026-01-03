<?php

namespace App\Orchid\Layouts\Charts;

use Orchid\Screen\Layouts\Chart;

class RevenueChart extends Chart
{
    protected $title = '💵 Выручка ($)';
    
    protected $target = 'revenue';
    
    protected $type = self::TYPE_BAR;
    
    protected $height = 250;
    
    protected $colors = ['#FF9F40'];
    
    protected $export = false;
}
