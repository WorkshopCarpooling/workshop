<div class="traffic-scene {{ ($trafficCompact ?? false) ? 'traffic-scene-compact' : '' }}" aria-hidden="true">
  <span class="traffic-tree traffic-tree-one"></span><span class="traffic-tree traffic-tree-two"></span><span class="traffic-tree traffic-tree-three"></span>
  <div class="traffic-road traffic-road-car">
    <div class="traffic-road-stripes"></div>
    <div class="traffic-road-wind"><i></i><i></i><i></i></div>
    <div class="traffic-vehicle traffic-car">
      <div class="traffic-body traffic-car-body">
        <span class="traffic-headlight"></span>
        <svg viewBox="0 0 100 210" fill="none"><defs><linearGradient id="trafficCarPaint" x2="1" y2="1"><stop stop-color="#fff"/><stop offset=".5" stop-color="#dfe6df"/><stop offset="1" stop-color="#a9b8ad"/></linearGradient></defs><rect x="11" y="24" width="78" height="166" rx="31" fill="url(#trafficCarPaint)" stroke="#a9b6ad"/><path d="M22 72Q50 55 78 72L73 113Q50 103 27 113Z" fill="#203c35"/><path d="M27 145Q50 155 73 145L76 165Q50 177 24 165Z" fill="#29463c"/><rect x="29" y="111" width="42" height="35" rx="7" fill="#edf2eb"/><path d="M20 78L20 144M80 78L80 144" stroke="#6f8275" stroke-width="3"/><path d="M20 42L33 37M67 37L80 42" stroke="#fff" stroke-width="7" stroke-linecap="round"/><path d="M22 178L33 181M67 181L78 178" stroke="#cb6c59" stroke-width="4"/><path d="M14 85H5M86 85H95" stroke="#8da294" stroke-width="6" stroke-linecap="round"/></svg>
        <span class="traffic-slipstream traffic-slipstream-left"></span><span class="traffic-slipstream traffic-slipstream-right"></span>
      </div>
    </div>
  </div>
  <div class="traffic-road traffic-road-motorcycle">
    <div class="traffic-road-stripes"></div>
    <div class="traffic-road-wind"><i></i><i></i><i></i></div>
    <div class="traffic-vehicle traffic-motorcycle">
      <div class="traffic-body traffic-motorcycle-body">
        <span class="traffic-headlight"></span>
        <img src="{{ asset('images/motorcycle-top.svg') }}" alt="" width="160" height="330" decoding="async">
        <span class="traffic-slipstream traffic-slipstream-left"></span><span class="traffic-slipstream traffic-slipstream-right"></span>
        <span class="traffic-exhaust"><i></i><i></i><i></i></span>
      </div>
    </div>
  </div>
  @unless ($trafficCompact ?? false)
    <div class="traffic-journey-note"><span class="traffic-note-icon">⌖</span><span><b>Next stop. Campus.</b><small>Your pickup point <span>→</span> UTeM campus</small></span><span class="traffic-note-spark">✦</span></div>
  @endunless
</div>
