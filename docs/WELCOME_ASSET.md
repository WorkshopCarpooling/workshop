# Welcome page car asset

Saved project asset: `public/images/welcome-classic-car.png`.
Served asset: `C:/laragon/www/workshop/public/images/welcome-classic-car.png`.
Generation mode: built-in `image_gen` tool, with `transparent_background: true`. No CLI fallback was used.
Original generated file was preserved. The PNG is 1536 × 1024 with a transparent alpha channel.

## Final generation prompt

Use case: stylized-concept.
Asset type: transparent hero automobile asset for a polished university carpool welcome page.
Primary request: One beautifully proportioned burnt-orange vintage compact two-door car, a subtle cream roof, original generic European classic design, seen from a low front three-quarter angle with the front toward the left and the rear toward the right. The entire car must be visible including all tires, mirrors and bumpers. Correct coherent automotive geometry: normal wheelbase and circular wheels, matching axles, clean smooth bodywork, realistic slightly curved windshield and side windows, small round warm headlamps, chrome bumpers and refined silver wheels. No passengers visible; dark olive interior behind realistic translucent glass.
Style/medium: High quality physically based 3D product rendering, like a premium automotive advertising render but with gently softened edges and a touch of warmth. Mostly realistic proportions and material detail, never flat vector art, never a toy with oversized wheels. Satin orange metallic paint, subtle clear-coat reflections, natural glass reflections, polished but understated chrome, textured black rubber. Precise believable perspective, beautiful clean silhouette. Subject is elegant, friendly and grounded, suitable for a soft sage-green and warm ivory website matching an orange classic-car login illustration.
Composition/framing: landscape canvas around 3:2; car fills about 85 percent of image width, centered with clear breathing room on every side, no cropping. Camera roughly at headlight height, 55 mm product lens, no exaggerated wide-angle distortion. No close-up. No platform, no room, no road, no trees, no environment. Truly transparent background with alpha channel. A delicate natural contact shadow under the tires is okay if transparent around it.
Lighting/mood: Large softbox lighting from upper left, warm gentle rim highlights, careful soft ambient occlusion, subtle olive reflections along lower body, readable rich material shading, studio beauty render.
Constraints: Only one car. No people, no text, no logos, no letters, no watermark, no visible license-plate writing. No props or luggage. No surreal geometry, no bent body, no warped wheels, no flat illustration, no outlines.

## Cinematic rear view asset

Saved project asset: `public/images/welcome-classic-rear.png`.
Served asset: `C:/laragon/www/workshop/public/images/welcome-classic-rear.png`.
Generation mode: built-in `image_gen`, using the saved front view as the identity reference and `transparent_background: true`. The original generated file was preserved.

Final prompt:

Use case: identity-preserve. Asset type: alternate camera angle of the exact same car for a cinematic website transition. Reference Image 1 is the exact car identity to preserve: burnt-orange compact two-door vintage car, cream roof, curved glass, chrome trim and bumpers, round headlamps, plain chrome steel wheels, black rubber tires. Create a matching high-quality physically based 3D studio render of THIS SAME CAR, now viewed from the REAR THREE-QUARTER angle, rear at left of image and nose pointing toward right. Rear body panel and one side visible, authentic believable compact classic rear geometry, small vintage tail lamps, blank rear license plate. Preserve the model, colours, proportions, wheelbase, wheels, cream roof, chrome and materials exactly. Whole car fully visible with generous space around every edge, landscape 3:2 canvas and low natural 55mm camera. Transparent background with real alpha, with delicate natural contact shadow only. Lighting: rich warm highlights on orange paint and chrome, soft studio reflections, same detailed materials as reference, subtly more dramatic rim lighting for a dark green showroom backdrop. No environment, no platform, no text, no logos, no humans, no luggage. Do not mirror the existing front image; this must be a coherent new camera view of the rear of that same car.

## Current welcome experience

The initial viewport combines the RideUTeM wordmark, visible orange-car render, and one link, “Let’s ride together”. No header, footer, extra copy, or motion controls are displayed.

A normal click plays a 2.6-second image-based cinematic sequence with a wide front view and rear view, letterbox bars, lighting sweep and fade into login. The animation-end event navigates to login; a timeout is the navigation fallback. Escape skips the sequence. Reduced-motion preferences and modified/new-tab clicks retain ordinary link navigation. Browser Back resets the welcome state.

Verified the initial view has one link, no extra buttons and no scrolling at desktop and phone sizes; all car images load; the cinematic click reaches login; and Back restores the enabled button.
