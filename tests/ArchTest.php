<?php

declare(strict_types=1);

// Dependency rules scan source text; see sourceFilesMatching() in tests/Pest.php.
it('sales domain remains strictly headless (no Filament or Livewire)', function (): void {
    expect(sourceFilesMatching('/(?<![\\\\\w])(Filament|Livewire)\\\\+[A-Z]/'))->toBeEmpty();
});

it('sales does not depend on service, marketing, or the Filament UI', function (): void {
    expect(sourceFilesMatching('/\bFocal\\\\+(Service|Marketing|Filament)\\\\+/'))->toBeEmpty();
});

arch('no debug functions are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('all sales domain actions have an execute method')
    ->expect('Focal\Sales\Actions')
    ->toHaveMethod('execute');

arch('all sales enums are string backed for database agnosticism')
    ->expect('Focal\Sales\Enums')
    ->toBeStringBackedEnums();
