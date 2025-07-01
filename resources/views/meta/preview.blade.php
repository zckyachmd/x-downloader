@extends('layouts.app')

@php
$title = "Download X Video from @$username";
$description = "Watch and download X video (ID: {$tweetId})";
@endphp

@section('content')
<div class="container py-5 text-center">
    <h1 class="h4">Preparing X Video...</h1>
</div>
@endsection
