(() => {
  document.querySelectorAll('[data-carbon-baseline]').forEach(root => {
    const mode=root.querySelector('[data-baseline-mode]'), fields=root.querySelector('[data-baseline-car]');
    const update=()=>{const active=mode.value==='drive_alone';fields.hidden=!active;fields.querySelectorAll('input,select').forEach(input=>{input.disabled=!active;input.required=active;});};
    mode.addEventListener('change',update); update();
  });
  document.querySelectorAll('[data-driver-baseline]').forEach(root => {
    const mode=root.querySelector('[name="driver_would_travel"]'), input=root.querySelector('[name="driver_baseline_km"]');
    const update=()=>{input.disabled=mode.value!=='yes';input.required=mode.value==='yes';};mode.addEventListener('change',update);update();
  });
})();
