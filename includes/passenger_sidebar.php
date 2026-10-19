<?php
$passengerActivePage = $passengerActivePage ?? '';
$passengerRole = (string) ($currentUser['role'] ?? 'student');
$passengerType = (string) ($currentUser['account_type'] ?? 'passenger');
$passengerAccountLabel = match (true) {
    $passengerRole === 'student' && $passengerType === 'driver' => 'Student · Driver',
    $passengerRole === 'staff' && $passengerType === 'driver' => 'Staff · Driver',
    $passengerRole === 'staff' => 'Staff · Passenger',
    default => 'Student · Passenger',
};
?>
<aside class="sidebar">
  <a class="app-brand" href="<?= $passengerType === 'driver' && in_array($passengerRole, ['student', 'staff'], true) ? 'driver.php' : 'dashboard.php' ?>"><span class="mini-car">↗</span>ride<span>UTeM</span></a>
  <div class="sidebar-label"><?= $passengerType === 'driver' && in_array($passengerRole, ['student', 'staff'], true) ? 'DRIVER MENU' : 'MENU' ?></div>
  <nav class="side-nav" aria-label="Main navigation">
    <?php if (in_array($passengerRole, ['student', 'staff'], true) && $passengerType === 'driver'): ?>
      <a class="side-link <?= $passengerActivePage === 'driver' ? 'active' : '' ?>" href="driver.php" <?= $passengerActivePage === 'driver' ? 'aria-current="page"' : '' ?>><span>↗</span> Driver overview</a>
      <a class="side-link" href="driver.php#seat-requests"><span>▤</span> Seat requests</a>
      <a class="side-link" href="driver.php#offer-ride"><span>＋</span> Offer a ride</a>
      <a class="side-link" href="driver.php#my-rides"><span>⌖</span> My rides</a>
    <?php else: ?>
      <a class="side-link <?= $passengerActivePage === 'dashboard' ? 'active' : '' ?>" href="dashboard.php" <?= $passengerActivePage === 'dashboard' ? 'aria-current="page"' : '' ?>><span>⌂</span> Overview</a>
      <a class="side-link <?= $passengerActivePage === 'findride' ? 'active' : '' ?>" href="findride.php" <?= $passengerActivePage === 'findride' ? 'aria-current="page"' : '' ?>><span>⌕</span> Find a ride</a>
      <a class="side-link <?= $passengerActivePage === 'bookings' ? 'active' : '' ?>" href="dashboard.php#upcoming" <?= $passengerActivePage === 'bookings' ? 'aria-current="page"' : '' ?>><span>▤</span> My bookings</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-bottom">
    <div class="user-mini"><span class="user-avatar">U</span><span><b><?= htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8') ?></b><small><?= htmlspecialchars($passengerAccountLabel, ENT_QUOTES, 'UTF-8') ?></small></span></div>
  </div>
</aside>
