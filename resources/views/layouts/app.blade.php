@extends('coreui::layouts.admin')

@section('content')
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h1 class="mb-4">🌐 Manajemen Perangkat Jaringan</h1>
      @include('networkdevices::partials.status-badge')
      @yield('network-content')
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  const REFRESH_INTERVAL = {{ $refreshInterval ?? 30 }} * 1000;
</script>
<script src="{{ module_asset('networkdevices', 'js/dashboard.js') }}"></script>
@endpush