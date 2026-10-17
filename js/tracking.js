(() => {
  const path = document.getElementById('routeProgress');
  const marker = document.getElementById('carMarker');
  const startButton = document.getElementById('startPreview');
  const endButton = document.getElementById('endPreview');
  const state = document.getElementById('tripState');
  const title = document.getElementById('statusTitle');
  const copy = document.getElementById('statusCopy');
  const progress = document.getElementById('progressValue');
  const update = document.getElementById('updateValue');
  const eta = document.getElementById('etaValue');
  const total = path.getTotalLength();
  let running = false;
  let startTime = 0;
  let progressValue = 0;
  let frame;

  const paint = (amount) => {
    progressValue = Math.max(0, Math.min(1, amount));
    const point = path.getPointAtLength(total * progressValue);
    marker.setAttribute('transform', `translate(${point.x} ${point.y})`);
    marker.setAttribute('opacity', progressValue > 0 ? '1' : '0');
    path.style.strokeDasharray = `${total * progressValue} ${total}`;
    progress.textContent = `${Math.round(progressValue * 100)}%`;
    update.textContent = 'Preview only';
  };

  const animate = (time) => {
    if (!running) return;
    if (!startTime) startTime = time - progressValue * 22000;
    paint((time - startTime) / 22000);
    if (progressValue >= 1) {
      running = false;
      state.className = 'trip-state is-ended';
      state.innerHTML = '<i></i> ARRIVED · PREVIEW';
      title.textContent = 'Destination reached';
      copy.textContent = 'The simulated route has ended. No device location was used or shared.';
      startButton.textContent = 'Restart movement preview →';
      startButton.disabled = false;
      endButton.disabled = true;
      return;
    }
    frame = requestAnimationFrame(animate);
  };

  startButton.addEventListener('click', () => {
    if (running) {
      running = false;
      cancelAnimationFrame(frame);
      startButton.innerHTML = 'Resume movement preview <span>→</span>';
      title.textContent = 'Preview paused';
      copy.textContent = 'The simulated marker is paused. Resume to continue the route preview.';
      return;
    }
    if (progressValue >= 1) paint(0);
    running = true;
    startTime = 0;
    state.className = 'trip-state is-active';
    state.innerHTML = '<i></i> SIMULATING';
    title.textContent = 'Simulating driver movement';
    copy.textContent = 'The marker moves along this illustrative route. This is not a live driver or GPS position.';
    eta.textContent = 'Not available';
    startButton.innerHTML = 'Pause preview <span>Ⅱ</span>';
    endButton.disabled = false;
    frame = requestAnimationFrame(animate);
  });

  endButton.addEventListener('click', () => {
    running = false;
    cancelAnimationFrame(frame);
    paint(0);
    state.className = 'trip-state is-ended';
    state.innerHTML = '<i></i> PREVIEW ENDED';
    title.textContent = 'Preview ended';
    copy.textContent = 'A real trip would stop sharing the driver’s location at this point.';
    eta.textContent = '—';
    startButton.innerHTML = 'Start movement preview <span>→</span>';
    endButton.disabled = true;
  });
})();
