<?php

use Sdw\LivewireMaps\Livewire\LivewireMap;

it('casts various useClusters values to boolean', function () {
    // No Laravel app in this test context; avoid config() and rely on explicit props

    $cmpFalse = new LivewireMap();
    $cmpFalse->mount(useClusters: 'false');
    expect($cmpFalse->useClusters)->toBeFalse();

    $cmpTrue = new LivewireMap();
    $cmpTrue->mount(useClusters: 'true');
    expect($cmpTrue->useClusters)->toBeTrue();

    $cmpNumeric = new LivewireMap();
    $cmpNumeric->mount(useClusters: 0);
    expect($cmpNumeric->useClusters)->toBeFalse();
});
