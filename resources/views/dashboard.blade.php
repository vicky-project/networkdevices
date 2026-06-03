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

{{-- ========== MODAL KONTROL DINAMIS ========== --}}
<div class="modal fade" id="controlModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">🎛️ Kontrol Perangkat</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        {{-- Info perangkat --}}
        <div class="mb-3 p-2 bg-light rounded">
          <strong id="modalDeviceName"></strong><br>
          <small class="text-muted">IP: <span id="modalDeviceIp"></span></small>
          <small class="text-muted d-block" id="modalDeviceMac"></small>
        </div>

        {{-- Pilihan aksi --}}
        <div class="mb-3">
          <label class="form-label">Aksi</label>
          <select id="controlAction" class="form-select" required>
            <option value="">-- Pilih Aksi --</option>
            <option value="wol" id="wolOption">⚡ Wake‑on‑LAN</option>
            <option value="http_get">🌐 HTTP GET (Buka URL)</option>
            <option value="http_post">📤 HTTP POST (Kirim Data)</option>
          </select>
        </div>

        {{-- Field dinamis --}}
        <div id="dynamicControlFields">
          <div class="mb-3 d-none" id="urlGroup">
            <label class="form-label">URL</label>
            <input type="url" id="controlUrl" class="form-control" placeholder="http://192.168.1.x/api">
            <small class="text-muted">Default: http://<em>(ip perangkat)</em></small>
          </div>
          <div class="mb-3 d-none" id="postDataGroup">
            <label class="form-label">Data JSON (opsional)</label>
            <textarea id="controlData" class="form-control" rows="2" placeholder='{"key":"value"}'></textarea>
          </div>
          <div class="mb-3 d-none" id="macGroup">
            <label class="form-label">MAC Address</label>
            <input type="text" id="controlMac" class="form-control" placeholder="AA:BB:CC:DD:EE:FF">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="sendControlBtn">Kirim Perintah</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="//cdn.jsdelivr.net/npm/eruda"></script>
<script>
  eruda.init();
</script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script>
  // ===================== KONFIGURASI =====================
  const API_BASE = "{{ config('networkdevices.api.base_url') }}";
  const USE_SECURE = {{ config('networkdevices.api.use_secure', false) ? 'true' : 'false' }};
  const WS_URL = API_BASE.replace(/^http/, 'ws');
  const CSRF_TOKEN = '{{ csrf_token() }}';

  function adjustUrl(url) {
    if (USE_SECURE) {
      return url.replace(/^http:/, 'https:');
    }
    return url;
  }

  const ROUTES = {
    devicesAjax: adjustUrl('{{ route('admin.network.devices.ajax') }}'),
    deviceName: adjustUrl('{{ route('admin.network.devices.name', ['ip' => '__IP__']) }}'),
    wake: adjustUrl('{{ route('admin.network.wake') }}'),
    control: adjustUrl('{{ route('admin.network.control') }}')
  };

  // ===================== STATE =====================
  let devices = [];
  let socket = null;
  let refreshTimer = null;
  let isSocketConnected = false;
  let currentDevice = null;

  // ===================== DOM =====================
  const devicesContainer = document.getElementById('devicesContainer');
  const loadingSpinner = document.getElementById('loadingSpinner');
  const emptyState = document.getElementById('emptyState');
  const connectionStatus = document.getElementById('connectionStatus');
  const searchInput = document.getElementById('searchInput');
  const typeFilter = document.getElementById('typeFilter');
  const renameModal = new bootstrap.Modal(document.getElementById('renameModal'));
  const controlModal = new bootstrap.Modal(document.getElementById('controlModal'));

  // ===================== UTILITAS =====================
  function debounce(func, delay) {
    let timer;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => func.apply(this, args), delay);
    };
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  // ===================== RENDER =====================
  const renderDevices = debounce(function() {
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
  }, 100);

  function createDeviceCard(device) {
    const onlineClass = device.online ? 'success': 'secondary';
    const onlineBadge = device.online ? 'bg-success': 'bg-danger';
    const onlineText = device.online ? 'ON': 'OFF';
    const lastSeen = device.last_seen && typeof moment !== 'undefined'
    ? moment(device.last_seen).fromNow(): (device.last_seen || '-');
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

  // ===================== API FETCH =====================
  async function apiFetch(url, options = {}) {
    const defaultOptions = {
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': CSRF_TOKEN,
      },
      credentials: 'same-origin',
    };

    const finalOptions = {
      ...defaultOptions,
      ...options,
      headers: {
        ...defaultOptions.headers,
        ...(options.headers || {}),
      },
    };

    if (options.credentials) finalOptions.credentials = options.credentials;

    const response = await fetch(url, finalOptions);

    if (response.status === 401 || response.status === 403) {
      showToast('Sesi Anda mungkin telah habis atau Anda tidak memiliki izin.', 'danger');
      throw new Error('Unauthorized');
    }

    if (!response.ok) {
      const body = await response.json().catch(() => ({}));
      throw new Error(body.message || `HTTP ${response.status}`);
    }

    return response;
  }

  async function fetchDevicesAjax() {
    try {
      const res = await apiFetch(ROUTES.devicesAjax);
      return await res.json();
    } catch (e) {
      console.error(e);
      return null;
    }
  }

  window.refreshDevices = async function() {
    const data = await fetchDevicesAjax();
    if (data) {
      devices = data;
      renderDevices();
    }
  };

  // ===================== WEBSOCKET =====================
  function connectWebSocket() {
    socket = io(WS_URL, {
      transports: ['websocket', 'polling']
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
    const exists = devices.find(d => d.ip === device.ip);
    if (!exists) {
    devices.push(device);
    renderDevices();
    showToast(`Perangkat baru: ${device.name} (${device.ip})`, 'success');
    }
    });

    socket.on('device_update', (device) => {
    const idx = devices.findIndex(d => d.ip === device.ip);
    if (idx > -1) {
    devices[idx] = { ...devices[idx], ...device };
    } else {
    devices.push(device);
    }
    renderDevices();
    });

    socket.on('device_offline', (data) => {
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

  async function fetchInitialDevices() {
    try {
      const data = await fetchDevicesAjax();
      if (data && Array.isArray(data)) {
        devices = data;
        renderDevices();
      } else {
        emptyState.textContent = 'Gagal memuat data perangkat. Mungkin Anda belum memiliki izin.';
        emptyState.classList.remove('d-none');
      }
    } catch (e) {
      emptyState.textContent = 'Terjadi kesalahan jaringan.';
      emptyState.classList.remove('d-none');
    } finally {
      loadingSpinner.classList.add('d-none');
    }
  }

  // ===================== AKSI PERANGKAT =====================
  window.wakeDevice = async function(mac) {
    if (!confirm(`Kirim magic packet ke ${mac}?`)) return;
    try {
      const res = await apiFetch(ROUTES.wake, {
        method: 'POST',
        body: JSON.stringify({ mac }),
      });
      const result = await res.json();
      if (result.status === 'sent') {
        showToast('Magic packet dikirim!', 'success');
      } else {
        showToast('Gagal: ' + (result.message || 'unknown'), 'danger');
      }
    } catch (e) {}
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
  const url = ROUTES.deviceName.replace('__IP__', ip);
  try {
  const res = await apiFetch(url, {
  method: 'POST',
  body: JSON.stringify({ name }),
  });
  if (res.ok) {
  renameModal.hide();
  showToast('Nama diperbarui!', 'success');
  const idx = devices.findIndex(d => d.ip === ip);
  if (idx > -1) {
  devices[idx].name = name;
  renderDevices();
  }
  }
  } catch (e) { }
  });

  // ===================== MODAL KONTROL DINAMIS =====================
  window.openControlModal = function(ip) {
    currentDevice = devices.find(d => d.ip === ip);
    if (!currentDevice) {
      showToast('Perangkat tidak ditemukan.', 'danger');
      return;
    }

    document.getElementById('modalDeviceName').textContent = currentDevice.name;
    document.getElementById('modalDeviceIp').textContent = currentDevice.ip;
    const macEl = document.getElementById('modalDeviceMac');
    if (currentDevice.mac) {
      macEl.textContent = 'MAC: ' + currentDevice.mac;
      macEl.style.display = 'block';
    } else {
      macEl.style.display = 'none';
    }

    document.getElementById('controlAction').value = '';
    document.getElementById('controlUrl').value = `http://${currentDevice.ip}/`;
    document.getElementById('controlData').value = '';
    document.getElementById('controlMac').value = currentDevice.mac || '';
    hideAllControlFields();

    const wolOption = document.getElementById('wolOption');
    if (!currentDevice.mac) {
      wolOption.disabled = true;
      wolOption.textContent = '⚡ Wake‑on‑LAN (MAC tidak tersedia)';
    } else {
      wolOption.disabled = false;
      wolOption.textContent = '⚡ Wake‑on‑LAN';
    }

    controlModal.show();
  };

  function hideAllControlFields() {
    document.getElementById('urlGroup').classList.add('d-none');
    document.getElementById('postDataGroup').classList.add('d-none');
    document.getElementById('macGroup').classList.add('d-none');
  }

  document.getElementById('controlAction').addEventListener('change', function() {
  const action = this.value;
  hideAllControlFields();
  switch (action) {
  case 'http_get':
  document.getElementById('urlGroup').classList.remove('d-none');
  break;
  case 'http_post':
  document.getElementById('urlGroup').classList.remove('d-none');
  document.getElementById('postDataGroup').classList.remove('d-none');
  break;
  case 'wol':
  document.getElementById('macGroup').classList.remove('d-none');
  break;
  }
  });

  document.getElementById('sendControlBtn').addEventListener('click', async function() {
  const action = document.getElementById('controlAction').value;
  if (!action) {
  showToast('Pilih aksi terlebih dahulu.', 'warning');
  return;
  }
  if (!currentDevice) return;

  const params = {};
  if (action === 'http_get' || action === 'http_post') {
  params.url = document.getElementById('controlUrl').value.trim() || `http://${currentDevice.ip}/`;
  if (action === 'http_post') {
  const dataStr = document.getElementById('controlData').value.trim();
  if (dataStr) {
  try {
  params.data = JSON.parse(dataStr);
  } catch {
  showToast('Data JSON tidak valid.', 'danger');
  return;
  }
  }
  }
  } else if (action === 'wol') {
  params.mac = document.getElementById('controlMac').value.trim();
  if (!params.mac) {
  showToast('MAC address diperlukan.', 'danger');
  return;
  }
  }

  try {
  const res = await apiFetch(ROUTES.control, {
  method: 'POST',
  body: JSON.stringify({ ip: currentDevice.ip, action, params }),
  });
  const result = await res.json();
  if (res.ok) {
  controlModal.hide();
  showToast('Perintah terkirim!', 'success');
  } else {
  showToast('Gagal: ' + (result.message || 'error'), 'danger');
  }
  } catch (e) { }
  });

  // ===================== EVENT LISTENER =====================
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