@php
  $showLoginArrival = Auth::check() && (bool) session()->pull('workspaceEntrance', false);
@endphp
@if($showLoginArrival)
<meta name="rideutem-login-arrival" content="1">
<link rel="stylesheet" href="{{ asset('css/login-arrival.css') }}?v={{ filemtime(public_path('css/login-arrival.css')) }}">
<template id="rideLoginArrival">
  <section class="login-arrival" aria-label="Opening your workspace">
    <div class="arrival-heading"><span class="arrival-wordmark">ride<span>UTeM</span><i>.</i></span><p role="status">You’re on your way.</p><span class="arrival-caption">YOUR CAMPUS. BETTER CONNECTED.</span></div>
    <div class="arrival-landscape" aria-hidden="true">
      <svg class="arrival-map" viewBox="0 0 1100 800" preserveAspectRatio="xMidYMid slice" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="arrivalLand" x1="150" y1="100" x2="850" y2="780" gradientUnits="userSpaceOnUse"><stop stop-color="#E8EFDF"/><stop offset="1" stop-color="#F4F5EC"/></linearGradient>
          <linearGradient id="arrivalRoad" x1="400" y1="50" x2="900" y2="800" gradientUnits="userSpaceOnUse"><stop stop-color="#4B6552"/><stop offset="1" stop-color="#254B39"/></linearGradient>
          <path id="arrivalRoute" d="M790 880C870 674 270 710 410 475C500 323 884 383 767 213C690 100 470 179 550 28"/>
          <g id="arrivalPin"><ellipse cx="0" cy="5" rx="15" ry="5" fill="#254B3917"/><path d="M0 0C-7-9-18-20-18-32a18 18 0 0 1 36 0C18-20 7-9 0 0Z" fill="#C98148"/><circle cy="-32" r="6" fill="#FFF8EA"/></g>
        </defs>
        <rect x="-1100" y="-800" width="3300" height="2400" fill="url(#arrivalLand)"/>
        <g stroke="#FAFCF5" stroke-width="12"><path d="M100 190 1000 430M100 400 1000 640M230 0 60 790M510 0 270 850M940 0 720 850M120 650 1010 150"/></g>
        <g fill="#D6E2C9"><rect x="150" y="225" width="104" height="72" rx="13" transform="rotate(15 150 225)"/><rect x="250" y="390" width="85" height="112" rx="13" transform="rotate(15 250 390)"/><rect x="590" y="370" width="140" height="70" rx="14" transform="rotate(15 590 370)"/><rect x="825" y="475" width="110" height="135" rx="18" transform="rotate(15 825 475)"/><rect x="435" y="80" width="85" height="55" rx="10"/><rect x="780" y="95" width="115" height="70" rx="14" transform="rotate(15 780 95)"/></g>
        <path d="M90 575C300 625 505 528 655 585S925 760 1060 660" stroke="#D5DFCA" stroke-width="45" stroke-linecap="round"/>
        <g fill="#B8CDA8"><circle cx="240" cy="140" r="19"/><circle cx="281" cy="162" r="13"/><circle cx="905" cy="310" r="23"/><circle cx="945" cy="328" r="15"/><circle cx="145" cy="465" r="22"/><circle cx="180" cy="490" r="13"/><circle cx="675" cy="100" r="17"/></g>
        <use href="#arrivalRoute" stroke="#254B3912" stroke-width="115" transform="translate(0 10)"/>
        <use href="#arrivalRoute" stroke="#FFFDF4" stroke-width="97"/>
        <use href="#arrivalRoute" stroke="url(#arrivalRoad)" stroke-width="86"/>
        <use class="arrival-road-dashes" href="#arrivalRoute" stroke="#EFF3E5" stroke-width="3" stroke-dasharray="18 18"/>
        <g class="arrival-pin pin-one" style="--pin-delay:0ms"><use href="#arrivalPin" x="444" y="627"/></g>
        <g class="arrival-pin pin-two" style="--pin-delay:220ms"><use href="#arrivalPin" x="399" y="534"/></g>
        <g class="arrival-pin pin-three" style="--pin-delay:500ms"><use href="#arrivalPin" x="756" y="351"/></g>
        <g class="arrival-pin pin-four" style="--pin-delay:820ms"><use href="#arrivalPin" x="599" y="139"/></g>
        <g class="arrival-car">
          <animateMotion dur="1.9s" begin="0s" fill="freeze" rotate="auto" keyPoints="0.32;0.96" keyTimes="0;1" calcMode="linear"><mpath href="#arrivalRoute"/></animateMotion>
          <rect x="-24" y="-15" width="46" height="30" rx="11" fill="#17372824" transform="translate(3 4)"/>
          <rect x="-19" y="-15" width="11" height="4" rx="2" fill="#20382A"/><rect x="-19" y="11" width="11" height="4" rx="2" fill="#20382A"/><rect x="9" y="-15" width="11" height="4" rx="2" fill="#20382A"/><rect x="9" y="11" width="11" height="4" rx="2" fill="#20382A"/>
          <rect x="-24" y="-12" width="48" height="24" rx="9" fill="#E3AD73"/><rect x="-13" y="-10" width="26" height="20" rx="6" fill="#F9E4BA"/><path d="m10-8 5 2v12l-5 2V-8Zm-21 1-4 2v10l4 2V-7Z" fill="#486C5A"/><rect x="-8" y="-8" width="15" height="16" rx="4" fill="#E5B579"/><path d="M20-8v4m0 8v4" stroke="#FFF6DB" stroke-width="3" stroke-linecap="round"/>
        </g>
      </svg>
    </div>
    <div class="arrival-whitewash" aria-hidden="true"></div>
    <button type="button" class="arrival-skip">Skip animation <span aria-hidden="true">↗</span></button>
  </section>
</template>
<script src="{{ asset('js/login-arrival.js') }}?v={{ filemtime(public_path('js/login-arrival.js')) }}" defer></script>
@endif
