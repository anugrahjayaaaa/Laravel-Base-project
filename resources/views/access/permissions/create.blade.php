@extends('layouts.app')
@section('content')
<h3>{{ ui('new_permission') }}</h3>
@include('access.permissions.edit')
@endsection
