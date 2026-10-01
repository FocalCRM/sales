<?php

declare(strict_types=1);

arch('sales domain remains strictly headless')
    ->expect('Focal\Sales')
    ->not->toUse([
        'Filament',
        'Livewire',
    ]);

arch('no debug functions left in sales code')
    ->expect('Focal\Sales')
    ->not->toUse([
        'dd',
        'dump',
        'ray',
        'var_dump',
    ]);

arch('all sales domain actions have an execute method')
    ->expect('Focal\Sales\Actions')
    ->toHaveMethod('execute');

arch('all sales enums are string backed for database agnosticism')
    ->expect('Focal\Sales\Enums')
    ->toBeStringBackedEnums();
