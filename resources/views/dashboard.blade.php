@extends('networkdevices::layouts.app')

@section('network-content')
<div class="row mb-3">
  <div class="col">
    <button class="btn btn-primary" onclick="refreshDevices()">
      🔄 Refresh
    </button>
  </div>
</div>

<div class="row" id="devices-container">
  @forelse($devices as $device)
  @include('networkdevices::partials.device-card', ['device' => $device])
  @empty
  <div class="col-12">
    <div class="alert alert-warning">
      Tidak ada perangkat ditemukan.
    </div>
  </div>
  @endforelse
</div>
@endsection

@push('scripts')
<script>
  function refreshDevices() {
    fetch('{{ route('network.devices.ajax') }}')
    .then(res => res.json())
    .then(devices => {
    const container = document.getElementById('devices-container');
    container.innerHTML = '';
    if (devices.length === 0) {
    container.innerHTML = '<div class="col-12"><div class="alert alert-warning">Tidak ada perangkat ditemukan.</div></div>';
    return;
    }
    devices.forEach(device => {
    // Di sini kita bisa memanggil fungsi renderDeviceCard atau langsung mengganti HTML
    // Untuk sederhana, kita reload halaman (bisa ditingkatkan dengan JS templating)
    });
    location.reload(); // sementara, nanti bisa diganti dengan render dinamis
    });
  }

  // Auto refresh
  setInterval(refreshDevices, REFRESH_INTERVAL);
</script>
@endpush