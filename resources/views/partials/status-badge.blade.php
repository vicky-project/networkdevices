@if(isset($serviceOnline))
<div class="alert alert-{{ $serviceOnline ? 'success' : 'danger' }} alert-dismissible fade show">
  Status Layanan Penemuan:
  <strong>{{ $serviceOnline ? 'ONLINE' : 'OFFLINE' }}</strong>
</div>
@endif