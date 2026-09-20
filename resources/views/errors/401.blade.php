@extends('errors::layout')

@section('title', __('Unauthorized'))
@section('code', '401')
@section('heading', __('You are not signed in'))
@section('message', __('This page needs an account. Sign in and try again.'))
