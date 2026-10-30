(() => {
  const routeSelect = document.getElementById('driverRouteSelect');
  const groups = [...document.querySelectorAll('.route-stop-options')];
  if (!routeSelect || !groups.length) return;

  function showStopsForRoute() {
    groups.forEach((group) => {
      const visible = group.dataset.routeId === routeSelect.value;
      group.hidden = !visible;
      if (!visible) group.querySelectorAll('input[type="checkbox"]').forEach((input) => { input.checked = false; });
    });
  }

  routeSelect.addEventListener('change', showStopsForRoute);
  showStopsForRoute();

  const rideForm = routeSelect.closest('form');
  rideForm.addEventListener('submit', (event) => {
    const selectedCount = rideForm.querySelectorAll('.route-stop-options:not([hidden]) input[type="checkbox"]:checked').length;
    if (selectedCount < 1 || selectedCount > 3) {
      event.preventDefault();
      window.alert('Select at least one fixed pickup stop and no more than three.');
    }
  });
})();
