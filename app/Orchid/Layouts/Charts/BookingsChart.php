<?php

namespace App\Orchid\Layouts\Charts;

use Orchid\Screen\Layouts\Chart;

class BookingsChart extends Chart
{
    protected $title = '📊 Бронирования';
    
    protected $target = 'bookings';
    
    protected $type = self::TYPE_LINE;
    
    protected $height = 250;
    
    protected $colors = ['#36A2EB'];
    
    protected $export = false;
}
