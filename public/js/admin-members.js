(() => {
  'use strict';
  document.querySelectorAll('form[data-member-action]').forEach(form => {
    form.addEventListener('submit', event => {
      if (form.dataset.submitting === '1') { event.preventDefault(); return; }
      if (form.dataset.memberAction === 'remove' && !window.confirm(`Remove access for ${form.dataset.memberName}? Unstarted bookings and requests will be cancelled. Past trips are kept, and access can be restored from Removed members.`)) {
        event.preventDefault(); return;
      }
      form.dataset.submitting = '1';
      const button=form.querySelector('button[type="submit"]');
      button.disabled=true;button.textContent=form.dataset.memberAction==='remove'?'Removing…':'Restoring…';
    });
  });
})();
