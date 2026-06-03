<div class="col-xl-3 col-lg-4 col-md-6 mb-4">
  <div class="card h-100 border-{{ $device['online'] ? 'success' : 'secondary' }} shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <h5 class="card-title">
          <span class="badge bg-{{ $device['online'] ? 'success' : 'danger' }} me-2">
            {{ $device['online'] ? 'ON' : 'OFF' }}
          </span>
          {{ $device['name'] }}
        </h5>
        <small class="text-muted">{{ $device['ip'] }}</small>
      </div>
      <hr>
      <p class="card-text small">
        <strong>MAC:</strong> {{ $device['mac'] ?: 'Tidak diketahui' }}<br>
        <strong>Produsen:</strong> {{ $device['manufacturer'] ?: '-' }}<br>
        <strong>Model:</strong> {{ $device['model'] ?: '-' }}<br>
        <strong>Tipe:</strong> {{ strtoupper($device['type']) }}<br>
        <strong>Terakhir terlihat:</strong> <span class="text-muted">{{ \Carbon\Carbon::parse($device['last_seen'])->diffForHumans() }}</span>
      </p>
    </div>
    <div class="card-footer bg-transparent">
      <div class="btn-group w-100" role="group">
        @if($device['mac'])
        <button class="btn btn-outline-secondary btn-sm" onclick="wakeDevice('{{ $device['mac'] }}')">
          ⚡ WOL
        </button>
        @endif
        <button class="btn btn-outline-primary btn-sm" onclick="renameDevice('{{ $device['ip'] }}', '{{ $device['name'] }}')">
          ✏️ Nama
        </button>
        <a href="http://{{ $device['ip'] }}" target="_blank" class="btn btn-outline-info btn-sm">
          🔗 Buka
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  async function wakeDevice(mac) {
    if (confirm(`Kirim Wake-on-LAN ke ${mac}?`)) {
      let res = await fetch('{{ route('network.wake') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ mac: mac })
      });
      alert('Perintah terkirim!');
    }
  }

  async function renameDevice(ip, currentName) {
    let newName = prompt('Nama baru untuk perangkat:', currentName);
    if (newName) {
      let res = await fetch('{{ route('network.devices.name', '') }}/' + ip, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ name: newName })
      });
      if (res.ok) location.reload();
      else alert('Gagal mengganti nama.');
    }
  }
</script>