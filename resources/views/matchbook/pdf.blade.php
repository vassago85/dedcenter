@extends('matchbook.layout')

@section('content')
    @include('matchbook.cover')
    @include('matchbook.overview')
    @include('matchbook.safety')
    @include('matchbook.stages')
    @include('matchbook.sponsors')
    @if($matchBook->include_summary_cards)
        @include('matchbook.summary')
    @endif
    @if($matchBook->include_dope_card)
        @include('matchbook.dope-card')
    @endif
@endsection
