@extends('errors::layout')

@section('title', __('Service Unavailable'))
@section('code', '503')
@section('heading', __('Down for maintenance'))
@section('message', __('The panel is being updated. It will be back shortly.'))
