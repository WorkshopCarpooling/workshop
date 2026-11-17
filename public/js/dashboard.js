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
  document.getElementById('searchRides')?.addEventListener('click', (event) => {
    event.preventDefault();
    window.location.assign('findride.php');
  });
})();
