<div class="vintage-scene" role="img" aria-label="Animated orange vintage car on a green campus road">
  <span class="vintage-caption"><i></i> A little classic. A better commute.</span>
  <svg viewBox="0 0 720 390" preserveAspectRatio="xMidYMax meet" aria-hidden="true">
    <defs>
      <linearGradient id="vintagePaint" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#ffb667"/><stop offset=".5" stop-color="#f28b39"/><stop offset="1" stop-color="#cf5e24"/></linearGradient>
      <linearGradient id="vintageGlass" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#b6d8cc"/><stop offset="1" stop-color="#507b70"/></linearGradient>
    </defs>
    <circle cx="565" cy="105" r="62" fill="#f2d69c"/>
    {{-- Extra seamless tiles fill wide boxes while the car keeps its natural proportions. --}}
    <g class="vintage-landscape vintage-hills">
      @for ($tile = -2; $tile < 4; $tile++)
      <g transform="translate({{ $tile * 720 }} 0)">
        <path d="M0 215C120 145 240 145 360 215S600 285 720 215V390H0Z" fill="#7e9e80"/>
      </g>
      @endfor
    </g>
    <g class="vintage-landscape vintage-scenery">
      @for ($tile = -2; $tile < 4; $tile++)
      <g transform="translate({{ $tile * 720 }} 0)">
        <path d="M0 263C120 220 240 177 360 220S600 306 720 263V390H0Z" fill="#a5b89a"/>
        <g fill="#315e4c"><path d="M61 235v-79h5v79Z"/><path d="M40 193q-16-38 24-62 41 26 22 62Z"/><path d="M651 270v-88h5v88Z"/><path d="M630 228q-18-41 24-71 43 30 25 71Z"/></g>
        <path d="M500 245v-54h81v54" fill="#d7dfc7"/><path d="m491 192 50-27 50 27Z" fill="#496b54"/>
        <g fill="#78947d"><path d="M513 204h12v18h-12ZM536 204h12v18h-12ZM559 204h12v18h-12ZM536 230h12v15h-12Z"/></g>
      </g>
      @endfor
    </g>
    <path d="M-1440 295Q-1080 275-720 288T0 288T720 288T1440 288T2160 288T2880 288V390H-1440Z" fill="#39584b"/>
    <path d="M-1440 305Q-1080 285-720 298T0 298T720 298T1440 298T2160 298T2880 298" fill="none" stroke="#d5d9b7" stroke-width="4"/>
    <g class="vintage-road-dashes" stroke="#e8dfbb" stroke-width="4" stroke-dasharray="45 35"><path d="M-1600 355H3040"/></g>
    <g class="vintage-road-texture" stroke="#adc1a1" stroke-width="2" opacity=".28">
      @for ($tile = -2; $tile < 4; $tile++)
      <path transform="translate({{ $tile * 720 }} 0)" d="M20 329h18M170 375h27M320 338h15M490 373h24M640 324h20"/>
      @endfor
    </g>
    <ellipse class="vintage-shadow" cx="355" cy="320" rx="234" ry="18" fill="#203f33" opacity=".25"/>
    <g class="vintage-dust" fill="#e8edce"><circle cx="136" cy="280" r="4"/><circle cx="136" cy="280" r="3"/><circle cx="136" cy="280" r="3"/></g>
    <g class="vintage-car">
      <path d="M139 283v-47q1-20 25-23l53-7 55-67q9-12 31-12h113q18 0 32 13l57 67 56 8q23 4 28 29l3 42Z" fill="url(#vintagePaint)" stroke="#ad5429" stroke-width="3"/>
      <path d="m238 205 48-60h51v60Zm111 0v-60h65q11 0 21 11l42 49Z" fill="url(#vintageGlass)" stroke="#e7d9b8" stroke-width="5" stroke-linejoin="round"/>
      <path d="m258 190 22-28M363 156l38 39M375 153l31 31" stroke="#d1e7d8" stroke-width="4" opacity=".5"/>
      <path d="M345 214v81M224 220v51M470 218v60" stroke="#ac562e" stroke-width="2" fill="none"/>
      <rect x="360" y="224" width="23" height="6" rx="3" fill="#eee5cc" stroke="#966a43"/>
      <path d="M147 248h431M151 284h423" stroke="#f8ca88" stroke-width="3"/>
      <rect x="131" y="273" width="43" height="14" rx="4" fill="#e8dfc5" stroke="#7f8e7a" stroke-width="2"/>
      <rect x="553" y="273" width="45" height="14" rx="4" fill="#e8dfc5" stroke="#7f8e7a" stroke-width="2"/>
      <rect x="568" y="237" width="18" height="24" rx="4" fill="#fff0bb"/>
      <rect x="141" y="235" width="12" height="23" rx="2" fill="#a63f28"/>
      <path d="M198 299a44 44 0 0 1 88 0M453 299a44 44 0 0 1 88 0" fill="#29463c" stroke="#b35e31" stroke-width="4"/>
      <g transform="translate(242 297)"><circle r="32" fill="#253c35"/><circle r="22" fill="#d9ddce"/><g class="vintage-wheel"><path d="M0-18V18M-18 0h36M-13-13l26 26M13-13l-26 26" stroke="#7e9288" stroke-width="4"/><circle r="8" fill="#f5efdb" stroke="#9ba99b" stroke-width="2"/></g></g>
      <g transform="translate(497 297)"><circle r="32" fill="#253c35"/><circle r="22" fill="#d9ddce"/><g class="vintage-wheel"><path d="M0-18V18M-18 0h36M-13-13l26 26M13-13l-26 26" stroke="#7e9288" stroke-width="4"/><circle r="8" fill="#f5efdb" stroke="#9ba99b" stroke-width="2"/></g></g>
      <path d="m466 201 17-5 8 7v10h-20Z" fill="#e49b56" stroke="#aa5b32" stroke-width="2"/>
      <path d="M180 220h27M185 226h20" stroke="#bb642f" stroke-width="3"/>
      <rect x="534" y="257" width="30" height="10" rx="2" fill="#f9f1dc"/>
    </g>
    <g class="vintage-speed" stroke="#e8edce" stroke-width="3" stroke-linecap="round"><path d="M87 249h30M67 264h42M89 280h23"/></g>
  </svg>
</div>
