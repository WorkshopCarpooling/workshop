(() => {
  const toast = document.getElementById('toast');
  let timer;
  const notify = (text) => {
    toast.textContent = text;
    toast.classList.add('visible');
    clearTimeout(timer);
    timer = setTimeout(() => toast.classList.remove('visible'), 3000);
  };

  document.getElementById('swapLocations')?.addEventListener('click', () => notify('Choose a pickup point when the campus locations are set up.'));
  document.getElementById('searchRides')?.addEventListener('click', () => notify('No ride records are loaded yet. Ride search will work after the interface is connected to a data service.'));
  document.getElementById('offerRide')?.addEventListener('click', () => notify('Ride publishing is an interface placeholder. Nothing is being saved.'));
})();
