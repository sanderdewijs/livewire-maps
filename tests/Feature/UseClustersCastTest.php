<?php

use Sdw\LivewireMaps\Livewire\LivewireMap;

it('casts various useClusters values to boolean', function () {
    config(['livewire-maps.use_clusters' => true]);

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
