@extends('layouts.plain')

@section('title', $title.' — '.config('app.name'))

@section('content')
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    @isset($returnUrl)
        <p><a href="{{ $returnUrl }}">Return to {{ $returnHost }}</a></p>
    @endisset
@endsection
