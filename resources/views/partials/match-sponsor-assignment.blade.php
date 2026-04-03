@isset($match)
    @livewire('match-advertising-options', ['match' => $match], key('match-advertising-' . $match->id))
@endisset
