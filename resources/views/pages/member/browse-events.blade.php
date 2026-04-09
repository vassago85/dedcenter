<?php

use Livewire\Attributes\{Layout, Title};
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')]
    #[Title('Find a Match — DeadCenter')]
    class extends Component {
}; ?>

<div>
    <livewire:events-listing />
</div>
