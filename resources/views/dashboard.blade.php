@extends('networkdevices::layouts.app')

@section('network-content')
<div id="app">
  {{-- Toolbar --}}
  <div class="row mb-3">
    <div class="col-md-4 mb-2">
      <input type="text" id="searchInput" class="form-control" placeholder="Cari nama, IP, atau MAC...">
    </div>
    <div class="col-md-3 mb-2">
      <select id="typeFilter" class="form-select">
        <option value="">Semua Tipe</option>
        <option value="upnp">UPnP</option>
        <option value="arp">ARP</option>
      </select>
    </div>
    <div class="col-auto mb-2">
      <button class="btn btn-outline-secondary" onclick="refreshDevices()">
        🔄 Refresh
      </button>
    </div>
    <div class="col-auto ms-auto mb-2">
      <span id="connectionStatus" class="badge bg-warning">Menghubungkan...</span>
    </div>
  </div>

  {{-- Loading Spinner --}}
  <div id="loadingSpinner" class="text-center my-5">
    <div class="spinner-border" role="status">
      <span class="visually-hidden">Memuat data...</span>
    </div>
    <p class="mt-2">
      Mengambil data perangkat...
    </p>
  </div>

  {{-- Devices Container --}}
  <div class="row" id="devicesContainer"></div>

  {{-- Empty State --}}
  <div id="emptyState" class="alert alert-info d-none">
    Tidak ada perangkat ditemukan di jaringan.
  </div>
</div>

{{-- ========== MODAL RENAME ========== --}}
<div class="modal fade" id="renameModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="renameForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">✏️ Ganti Nama Perangkat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="renameIp">
        <label class="form-label">Nama Baru</label>
        <input type="text" id="renameName" class="form-control" required maxlength="100">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- ========== MODAL KONTROL ========== --}}
<div class="modal fade" id="controlModal" tabindex="-1">
  <div class="modal-dialog">
    <form id="controlForm" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">🎛️ Kontrol Perangkat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="controlIp">
        <div class="mb-3">
          <label class="form-label">Aksi</label>
          <select id="controlAction" class="form-select" required>
            <option value="http_get">HTTP GET</option>
            <option value="http_post">HTTP POST</option>
            <option value="wol">Wake‑on‑LAN (Magic Packet)</option>
          </select>
        </div>
        <div id="controlParams">
          <div class="mb-3" id="urlField">
            <label class="form-label">URL</label>
            <input type="url" id="controlUrl" class="form-control" placeholder="http://192.168.1.x/api">
          </div>
          <div class="mb-3 d-none" id="postDataField">
            <label class="form-label">Data (JSON)</label>
            <textarea id="controlData" class="form-control" rows="3" placeholder='{"key":"value"}'></textarea>
          </div>
          <div class="mb-3 d-none" id="macField">
            <label class="form-label">MAC Address</label>
            <input type="text" id="controlMac" class="form-control" placeholder="AA:BB:CC:DD:EE:FF">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Kirim Perintah</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
{{-- Socket.IO Client CDN --}}
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
{{-- Moment.js untuk format waktu relatif --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script>
  // ===================== KONFIGURASI =====================
  const WS_URL = "{{ config('networkdevices.api.base_url') }}";
  const CSRF_TOKEN = '{{ csrf_token() }}';
  const ROUTES = {
    devicesAjax: '{{ route('admin.network.devices.ajax') }}',
    deviceName: '{{ route('admin.network.devices.name', ['ip' => '__IP__']) }}',
    wake: '{{ route('admin.network.wake') }}',
    control: '{{ route('admin.network.control') }}'
  };

  // ===================== STATE =====================
  let devices = [];
  let socket = null;
  let refreshTimer = null;
  let isSocketConnected = false;

  // ===================== DOM =====================
  const devicesContainer = document.getElementById('devicesContainer');
  const loadingSpinner = document.getElementById('loadingSpinner');
  const emptyState = document.getElementById('emptyState');
  const connectionStatus = document.getElementById('connectionStatus');
  const searchInput = document.getElementById('searchInput');
  const typeFilter = document.getElementById('typeFilter');
  const renameModal = new bootstrap.Modal(document.getElementById('renameModal'));
  const controlModal = new bootstrap.Modal(document.getElementById('controlModal'));

  // ===================== FUNGSI RENDER =====================
  function renderDevices() {
    const filtered = filterDevices();
    if (filtered.length === 0) {
      devicesContainer.innerHTML = '';
      emptyState.classList.remove('d-none');
      loadingSpinner.classList.add('d-none');
      return;
    }
    emptyState.classList.add('d-none');
    let html = '';
    filtered.forEach(device => {
    html += createDeviceCard(device);
    });
    devicesContainer.innerHTML = html;
    loadingSpinner.classList.add('d-none');
  }

  function createDeviceCard(device) {
    const onlineClass = device.online ? 'success': 'secondary';
    const onlineBadge = device.online ? 'bg-success': 'bg-danger';
    const onlineText = device.online ? 'ON': 'OFF';
    const lastSeen = device.last_seen ? moment(device.last_seen).fromNow(): '-';
    const mac = device.mac || 'Tidak diketahui';
    const manufacturer = device.manufacturer || '-';
    const model = device.model || '-';
    const type = device.type ? device.type.toUpperCase(): '-';
    const services = device.services && device.services.length
    ? device.services.join(', '): '';

    return `
    <div class="col-xl-3 col-lg-4 col-md-6 mb-4 device-card" data-ip="${device.ip}">
    <div class="card h-100 border-${onlineClass} shadow-sm">
    <div class="card-body">
    <div class="d-flex justify-content-between align-items-start">
    <h5 class="card-title">
    <span class="badge ${onlineBadge} me-2">${onlineText}</span>
    ${escapeHtml(device.name)}
    </h5>
    <small class="text-muted">${device.ip}</small>
    </div>
    <hr>
    <p class="card-text small">
    <strong>MAC:</strong> ${mac}<br>
    <strong>Produsen:</strong> ${manufacturer}<br>
    <strong>Model:</strong> ${model}<br>
    <strong>Tipe:</strong> ${type}<br>
    <strong>Terakhir:</strong> <span class="text-muted">${lastSeen}</span>
    </p>
    ${services ? `<p class="card-text small text-muted">Layanan: ${services}</p>`: ''}
    </div>
    <div class="card-footer bg-transparent">
    <div class="btn-group w-100" role="group">
    ${device.mac ? `<button class="btn btn-outline-secondary btn-sm" onclick="wakeDevice('${device.mac}')">⚡ WOL</button>`: ''}
    <button class="btn btn-outline-primary btn-sm" onclick="openRenameModal('${device.ip}', '${escapeHtml(device.name)}')">✏️ Nama</button>
    <button class="btn btn-outline-info btn-sm" onclick="openControlModal('${device.ip}')">🎛️ Kontrol</button>
    <a href="http://${device.ip}" target="_blank" class="btn btn-outline-dark btn-sm">🔗</a>
    </div>
    </div>
    </div>
    </div>`;
  }

  function filterDevices() {
    const term = searchInput.value.trim().toLowerCase();
    const type = typeFilter.value;
    return devices.filter(d => {
    const matchType = !type || d.type === type;
    const matchSearch = !term ||
    d.name.toLowerCase().includes(term) ||
    d.ip.includes(term) ||
    (d.mac && d.mac.toLowerCase().includes(term));
    return matchType && matchSearch;
    });
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  // ===================== WEBSOCKET =====================
  function connectWebSocket() {
    socket = io(WS_URL, {
      transports: ['websocket', 'polling'] // fallback ke polling jika websocket diblokir
    });

    socket.on('connect', () => {
    console.log('WebSocket connected');
    isSocketConnected = true;
    connectionStatus.textContent = 'Live';
    connectionStatus.className = 'badge bg-success';
    fetchInitialDevices();
    if (refreshTimer) {
    clearInterval(refreshTimer);
    refreshTimer = null;
    }
    });

    socket.on('disconnect', () => {
    console.log('WebSocket disconnected');
    isSocketConnected = false;
    connectionStatus.textContent = 'Offline';
    connectionStatus.className = 'badge bg-danger';
    startPolling();
    });

    socket.on('device_new', (device) => {
    console.log('device_new', device);
    const exists = devices.find(d => d.ip === device.ip);
    if (!exists) {
    devices.push(device);
    renderDevices();
    showToast(`Perangkat baru: ${device.name} (${device.ip})`, 'success');
    }
    });

    socket.on('device_update', (device) => {
    console.log('device_update', device);
    const idx = devices.findIndex(d => d.ip === device.ip);
    if (idx > -1) {
    devices[idx] = { ...devices[idx], ...device };
    } else {
    devices.push(device);
    }
    renderDevices();
    });

    socket.on('device_offline', (data) => {
    console.log('device_offline', data);
    const idx = devices.findIndex(d => d.ip === data.ip);
    if (idx > -1) {
    devices.splice(idx, 1);
    renderDevices();
    showToast(`Perangkat offline: ${data.ip}`, 'warning');
    }
    });
  }

  function startPolling() {
    if (refreshTimer) return;
    refreshTimer = setInterval(() => {
    fetchDevicesAjax().then(data => {
    if (data && !isSocketConnected) {
    devices = data;
    renderDevices();
    }
    });
    }, 30000);
  }

  // ===================== DATA FETCH =====================
  async function fetchInitialDevices() {
    try {
      const data = await fetchDevicesAjax();
      if (data) {
        devices = data;
        renderDevices();
      }
    } catch (e) {
      console.error(e);
      loadingSpinner.classList.add('d-none');
      emptyState.classList.remove('d-none');
      emptyState.textContent = 'Gagal memuat data.';
    }
  }

  async function fetchDevicesAjax() {
    try {
      const res = await fetch(ROUTES.devicesAjax);
      if (res.ok) return await res.json();
    } catch (e) {
      /* fall through */
    }
    return null;
  }

  window.refreshDevices = async function() {
    const data = await fetchDevicesAjax();
    if (data) {
      devices = data;
      renderDevices();
    }
  };

  // ===================== AKSI =====================
  window.wakeDevice = async function(mac) {
    if (!confirm(`Kirim magic packet ke ${mac}?`)) return;
    try {
      const res = await fetch(ROUTES.wake, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF_TOKEN
        },
        body: JSON.stringify({ mac })
      });
      const result = await res.json();
      if (result.status === 'sent') {
        showToast('Magic packet dikirim!', 'success');
      } else {
        showToast('Gagal: ' + (result.message || 'unknown'), 'danger');
      }
    } catch (e) {
      showToast('Gagal mengirim WOL.', 'danger');
    }
  };

  window.openRenameModal = function(ip, currentName) {
    document.getElementById('renameIp').value = ip;
    document.getElementById('renameName').value = currentName;
    renameModal.show();
  };

  document.getElementById('renameForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const ip = document.getElementById('renameIp').value;
  const name = document.getElementById('renameName').value.trim();
  if (!name) return;
  try {
  const url = ROUTES.deviceName.replace('__IP__', ip);
  const res = await fetch(url, {
  method: 'POST',
  headers: {
  'Content-Type': 'application/json',
  'X-CSRF-TOKEN': CSRF_TOKEN
  },
  body: JSON.stringify({ name })
  });
  if (res.ok) {
  renameModal.hide();
  showToast('Nama diperbarui!', 'success');
  const idx = devices.findIndex(d => d.ip === ip);
  if (idx > -1) {
  devices[idx].name = name;
  renderDevices();
  }
  } else {
  showToast('Gagal menyimpan nama.', 'danger');
  }
  } catch (e) {
  showToast('Error jaringan.', 'danger');
  }
  });

  window.openControlModal = function(ip) {
    document.getElementById('controlIp').value = ip;
    document.getElementById('controlAction').value = 'http_get';
    updateControlFields();
    controlModal.show();
  };

  document.getElementById('controlAction').addEventListener('change', updateControlFields);
  function updateControlFields() {
    const action = document.getElementById('controlAction').value;
    document.getElementById('urlField').classList.toggle('d-none', action === 'wol');
    document.getElementById('postDataField').classList.toggle('d-none', action !== 'http_post');
    document.getElementById('macField').classList.toggle('d-none', action !== 'wol');
  }

  document.getElementById('controlForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const ip = document.getElementById('controlIp').value;
  const action = document.getElementById('controlAction').value;
  const params = {};

  if (action === 'http_get' || action === 'http_post') {
  params.url = document.getElementById('controlUrl').value;
  if (action === 'http_post') {
  try {
  params.data = JSON.parse(document.getElementById('controlData').value);
  } catch {
  showToast('Data JSON tidak valid.', 'danger');
  return;
  }
  }
  } else if (action === 'wol') {
  params.mac = document.getElementById('controlMac').value;
  if (!params.mac) {
  showToast('Masukkan MAC address.', 'danger');
  return;
  }
  }

  try {
  const res = await fetch(ROUTES.control, {
  method: 'POST',
  headers: {
  'Content-Type': 'application/json',
  'X-CSRF-TOKEN': CSRF_TOKEN
  },
  body: JSON.stringify({ ip, action, params })
  });
  const result = await res.json();
  if (res.ok) {
  controlModal.hide();
  showToast('Perintah terkirim.', 'success');
  } else {
  showToast('Gagal: ' + (result.message || 'error'), 'danger');
  }
  } catch (e) {
  showToast('Error jaringan.', 'danger');
  }
  });

  // ===================== UTILITAS =====================
  searchInput.addEventListener('input', renderDevices);
  typeFilter.addEventListener('change', renderDevices);

  function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    toast.role = 'alert';
    toast.style.zIndex = 9999;
    toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
  }

  // ===================== INISIALISASI =====================
  document.addEventListener('DOMContentLoaded', () => {
  connectWebSocket();
  });
</script>
@endpush