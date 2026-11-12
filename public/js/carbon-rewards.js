(() => {
  'use strict';
  document.querySelectorAll('.rewards-declaration').forEach(form=>{
    const select=form.querySelector('[data-reward-alternative]'),fields=form.querySelector('[data-reward-car-fields]');
    const update=()=>{const separate=select.value==='separate_car';fields.hidden=!separate;fields.querySelectorAll('input,select').forEach(input=>{input.disabled=!separate;input.required=separate;});};
    select.addEventListener('change',update);update();
  });
  document.querySelectorAll('.carbon-rewards-page form').forEach(form=>form.addEventListener('submit',event=>{
    if(form.dataset.submitting){event.preventDefault();return;}
    form.dataset.submitting='true';const button=form.querySelector('button[type="submit"]');if(button){button.disabled=true;button.textContent='Saving…';}
  }));
})();
