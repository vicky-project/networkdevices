@extends('coreui::layouts.admin')

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h1 class="mb-4">🌐 Manajemen Perangkat Jaringan</h1>
      @yield('network-content')
    </div>
  </div>
</div>
@endsection

{{-- Script akan ditambahkan oleh child view melalui @push --}}