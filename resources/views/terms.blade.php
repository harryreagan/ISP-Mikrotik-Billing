@extends('layouts.legal', [
    'title'    => 'Terms of Service',
    'heading'  => 'Terms of Service',
    'subtitle' => 'Operating terms, router orchestration policies, and service level agreements on HSmart ISP.'
])

@section('content')
    {!! $terms !!}
@endsection
