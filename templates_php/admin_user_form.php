<?php declare(strict_types=1); ?>
<?php $item = $item ?? []; ?>
<section class="card" style="padding:24px;">
  <h2 class="card-title"><?= ($mode ?? "new") === "edit" ? "Edit User" : "New User" ?></h2>
  <?php if (!empty($error)): ?><p class="result-text" style="color:#fca5a5;"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES) ?>">
    <label>Username</label>
    <input class="input" name="username" value="<?= htmlspecialchars((string) ($item["username"] ?? ""), ENT_QUOTES) ?>" <?= ($mode ?? "new") === "edit" ? "disabled" : "" ?>>
    <?php if (($mode ?? "new") === "edit"): ?>
      <input type="hidden" name="username_locked" value="<?= htmlspecialchars((string) ($item["username"] ?? ""), ENT_QUOTES) ?>">
    <?php endif; ?>
    <label>Full name</label>
    <input class="input" name="full_name" value="<?= htmlspecialchars((string) ($item["full_name"] ?? ""), ENT_QUOTES) ?>">
    <label>Email</label>
    <input class="input" type="email" name="email" value="<?= htmlspecialchars((string) ($item["email"] ?? ""), ENT_QUOTES) ?>">
    <label>Role</label>
    <select class="input" name="role">
      <option value="teacher" <?= (($item["role"] ?? "") === "teacher") ? "selected" : "" ?>>Teacher</option>
      <option value="admin" <?= (($item["role"] ?? "") === "admin") ? "selected" : "" ?>>Admin</option>
    </select>
    <label>Password <?= ($mode ?? "new") === "edit" ? "(leave blank to keep current)" : "" ?></label>
    <input class="input" type="text" name="password" value="">
    <div style="display:flex; gap:8px;">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="/admin/users">Back</a>
    </div>
  </form>
</section>
