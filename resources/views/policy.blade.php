@extends('layouts.legal', [
    'title'    => 'Privacy Policy',
    'heading'  => 'Privacy Policy',
    'subtitle' => 'Our data collection, tenant isolation, and Kenya Data Protection Act compliance standards.'
])

@section('content')
    {!! $policy !!}
@endsection
