<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.marketing')]
    #[Title('Events — DeadCenter')]
    class extends Component {
}; ?>

<div class="mx-auto max-w-6xl px-6 py-8">
    <livewire:events-listing />
</div>
