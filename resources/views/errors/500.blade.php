@extends('errors::layout')

@section('title', __('Server Error'))
@section('code', '500')
@section('heading', __('Something broke'))
@section('message', __('The error is logged. Nothing you did caused it, and trying again may well work.'))
