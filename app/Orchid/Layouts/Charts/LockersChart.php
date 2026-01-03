<?php

namespace App\Orchid\Layouts\Charts;

use Orchid\Screen\Layouts\Chart;

class LockersChart extends Chart
{
    protected $title = '🗄️ Статус шкафов';
    
    protected $target = 'lockers_status';
    
    protected $type = self::TYPE_PIE;
    
    protected $height = 200;
    
    protected $colors = ['#4BC0C0', '#FF6384'];
    
    protected $export = false;
}
