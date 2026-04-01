@isset($match)
    @livewire('match-sponsor-assignment', ['match' => $match], key('match-sponsor-assignment-' . $match->id))
@endisset
