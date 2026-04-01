@extends('matchbook.layout')

@push('styles')
<style>
    body { background: #e2e8f0; }
    .preview-container { max-width: 210mm; margin: 20px auto; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,0.12); }
</style>
@endpush

@section('content')
<div class="preview-container">
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
</div>
@endsection
