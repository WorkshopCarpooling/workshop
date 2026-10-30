(() => {
  const toast = document.getElementById('toast');
  let timer;
  const notify = (text) => {
    toast.textContent = text;
    toast.classList.add('visible');
    clearTimeout(timer);
    timer = setTimeout(() => toast.classList.remove('visible'), 3000);
  };

  document.getElementById('exportReport')?.addEventListener('click', () => notify('There are no report records to export yet.'));
  document.getElementById('printReport')?.addEventListener('click', () => window.print());
})();
