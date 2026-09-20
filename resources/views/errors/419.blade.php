@extends('errors::layout')

@section('title', __('Page Expired'))
@section('code', '419')
@section('heading', __('The page expired'))
@section('message', __('You sat on this form long enough for its token to lapse. Go back and submit it again.'))
