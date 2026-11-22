@extends('layouts.legal', [
    'title'    => 'Cookie Policy',
    'heading'  => 'Cookie Policy',
    'subtitle' => 'How we use essential session cookies to authenticate administrators and protect subscriber portal transactions.'
])

@section('content')
    {!! $cookies !!}
@endsection

