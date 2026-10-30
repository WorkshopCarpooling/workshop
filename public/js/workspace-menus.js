(() => {
  const menus = [...document.querySelectorAll('[data-profile-menu]')];
  document.addEventListener('click', event => {
    for (const menu of menus) if (menu.open && !menu.contains(event.target)) menu.open = false;
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    for (const menu of menus) if (menu.open) {
      menu.open = false;
      menu.querySelector('summary')?.focus();
      event.preventDefault();
    }
  });
  for (const picker of document.querySelectorAll('[data-vehicle-picker]')) {
    const select = picker.querySelector('select');
    const panels = [...picker.querySelectorAll('[data-vehicle-panel]')];
    const update = () => {
      for (const panel of panels) panel.hidden = panel.dataset.vehiclePanel !== select.value;
    };
    select.addEventListener('change', update);
    update();
  }
})();
