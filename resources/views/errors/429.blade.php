@extends('errors::layout')

@section('title', __('Too Many Requests'))
@section('code', '429')
@section('heading', __('Slow down'))
@section('message', __('Too many requests came from here at once. Wait a minute and try again.'))
