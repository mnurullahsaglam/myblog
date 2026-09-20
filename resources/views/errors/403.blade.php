@extends('errors::layout')

@section('title', __('Forbidden'))
@section('code', '403')
@section('heading', __('Not yours to open'))
@section('message', __('The account you are signed in as cannot reach this page.'))
