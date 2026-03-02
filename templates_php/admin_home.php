<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Admin</h2>
  <div class="mini-share">
    <span class="pill subtle">Maintenance: <?= !empty($maintenance) ? "ON" : "OFF" ?></span>
    <span class="pill subtle">Suspended: <?= !empty($suspended) ? "ON" : "OFF" ?></span>
    <span class="pill subtle">Destroyed: <?= !empty($destroyed) ? "ON" : "OFF" ?></span>
  </div>
  <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:12px;">
    <a class="btn btn-primary" href="/admin/users">Users</a>
    <a class="btn btn-secondary" href="/admin/students">Students</a>
    <a class="btn btn-secondary" href="/classrooms">Classrooms</a>
    <a class="btn btn-secondary" href="/admin/vocab">Vocab</a>
    <a class="btn btn-secondary" href="/admin/keywords">Keywords</a>
    <a class="btn btn-secondary" href="/admin/packs">Packs</a>
    <a class="btn btn-secondary" href="/admin/broadcast">Broadcast</a>
    <a class="btn btn-secondary" href="/admin/audit">Audit</a>
    <a class="btn btn-secondary" href="/admin/logs">Logs</a>
    <a class="btn btn-secondary" href="/admin/maintenance">Maintenance</a>
    <a class="btn btn-secondary" href="/admin/manage-server">Manage Server</a>
    <a class="btn btn-secondary" href="/admin/export-all">Export All</a>
  </div>
</section>
