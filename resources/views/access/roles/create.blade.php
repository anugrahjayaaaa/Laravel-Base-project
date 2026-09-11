@extends('layouts.app')
@section('content')
<h3>{{ ui('new_role') }}</h3>
@include('access.roles.edit')
@endsection
