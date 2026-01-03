<?php

namespace App\Orchid\Layouts\Charts;

use Orchid\Screen\Layouts\Chart;

class SubscriptionsChart extends Chart
{
    protected $title = '📋 Подписки по типам';
    
    protected $target = 'subscriptions_by_type';
    
    protected $type = self::TYPE_PIE;
    
    protected $height = 200;
    
    protected $colors = ['#36A2EB', '#4BC0C0', '#FF9F40'];
    
    protected $export = false;
}
