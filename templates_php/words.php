<?php declare(strict_types=1); ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title">Keywords</h2>
  <form method="get" class="form" style="margin-bottom:10px;">
    <input class="input" name="q" value="<?= htmlspecialchars((string) ($q ?? ""), ENT_QUOTES) ?>" placeholder="Search keywords...">
  </form>
  <div class="table-wrapper">
    <table class="table">
      <thead><tr><th>Keyword</th><th>Slug</th><th>Description</th></tr></thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr>
          <td><a href="/word/<?= rawurlencode((string) ($r["slug"] ?? "")) ?>"><?= htmlspecialchars((string) ($r["keyword"] ?? ""), ENT_QUOTES) ?></a></td>
          <td><?= htmlspecialchars((string) ($r["slug"] ?? ""), ENT_QUOTES) ?></td>
          <td style="max-width:420px; word-break:break-word;"><?= htmlspecialchars((string) ($r["description"] ?? ""), ENT_QUOTES) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
