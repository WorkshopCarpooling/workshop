@php
  $isDriverHero = $journeyMode ?? false;
@endphp
<section class="journey-hero" aria-labelledby="journey-heading">
  <div class="journey-copy">
    <span class="journey-eyebrow"><i></i> TOGETHER, TO CAMPUS</span>
    <h2 id="journey-heading">{{ $isDriverHero ? 'Your journey.' : 'Same campus.' }}<br>{{ $isDriverHero ? 'Their way home.' : 'Better together.' }}</h2>
    <p>{{ $isDriverHero ? 'A spare seat can make someone’s day. Share your next campus journey with the UTeM community.' : 'Less driving solo. More going together. Find your campus carpool and make the everyday journey a little better.' }}</p>
    <a class="journey-cta" href="{{ $isDriverHero ? url('driver/offer') : url('findride.php') }}">{{ $isDriverHero ? 'Offer a ride' : 'Find my ride' }} <span>↗</span></a>
    <div class="journey-tags"><span>✓ Campus community</span><span>✓ Scheduled journeys</span></div>
  </div>
  @include('partials.traffic-scene')
</section>
