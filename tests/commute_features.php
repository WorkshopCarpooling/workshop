<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Ride;
use App\Models\RideBooking;
use App\Services\Journey;
use App\Services\RideMatcher;
use App\Services\RoutePlanner;
use App\Services\TravelTimePredictor;
use Carbon\Carbon;

$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $checks++; echo "PASS: $label\n";
}
Carbon::setTestNow(Carbon::parse('2030-01-07 07:00:00', 'Asia/Kuala_Lumpur'));
$ride = new Ride(['pickup_point'=>'Melaka Sentral', 'destination'=>'UTeM Main Campus', 'pickup_latitude'=>2.2214, 'pickup_longitude'=>102.2504, 'dropoff_latitude'=>2.3139, 'dropoff_longitude'=>102.3183, 'departure_at'=>'2030-01-07 08:00:00', 'available_seats'=>2]);
$ride->setRelation('bookings', collect());
$journey = ['pickup_label'=>'Mydin MITC', 'pickup_latitude'=>2.2707, 'pickup_longitude'=>102.2856, 'dropoff_label'=>'UTeM Main Campus', 'dropoff_latitude'=>2.3139, 'dropoff_longitude'=>102.3183, 'seats_requested'=>1, 'departure_at'=>'2030-01-07 07:45:00', 'arrive_by'=>'2030-01-07 09:00:00'];
$match = (new RideMatcher)->match($ride, $journey);
check($match !== null && $match['score'] >= 0 && $match['score'] <= 100, 'feasible journey receives a bounded compatibility score');
$points = $match['plan']['points'];
check($points[0]['label']==='Melaka Sentral' && end($points)['label']==='UTeM Main Campus', 'driver endpoints stay fixed');
$labels = array_column($points, 'label');
check(array_search('Pickup: Mydin MITC',$labels) < array_search('Drop-off: UTeM Main Campus',$labels), 'passenger pickup precedes drop-off');
check((new RideMatcher)->match($ride, array_replace($journey,['seats_requested'=>3]))===null, 'insufficient seats rejected');
check((new RideMatcher)->match($ride, array_replace($journey,['departure_at'=>'2030-01-07 08:01:00']))===null, 'ride before earliest departure rejected');
check((new RideMatcher)->match($ride, array_replace($journey,['departure_at'=>'2030-01-07 05:00:00']))===null, 'departure beyond two-hour window rejected');
check((new RideMatcher)->match($ride, array_replace($journey,['arrive_by'=>'2030-01-07 08:01:00']))===null, 'infeasible arrival deadline rejected');
check((new RideMatcher)->match($ride, array_replace($journey,['pickup_latitude'=>3.139,'pickup_longitude'=>101.6869]))===null, 'excessive route detour rejected');
$booking = new RideBooking(['pickup_label'=>'Zoo Melaka','pickup_latitude'=>2.2768,'pickup_longitude'=>102.2996,'dropoff_label'=>'Campus','dropoff_latitude'=>2.3139,'dropoff_longitude'=>102.3183,'arrive_by'=>'2030-01-07 08:45:00','booking_status'=>'accepted']);
$ride->setRelation('bookings',collect([$booking]));
$plan = (new RoutePlanner)->plan($ride,$journey);
check($plan !== null && count($plan['points'])===6, 'accepted passengers retained while inserting a new pair');
$booking->arrive_by='2030-01-07 08:00:01';
check((new RoutePlanner)->plan($ride,$journey)===null, 'accepted passenger deadline cannot be broken');
check(abs(Journey::km(2.2,102.2,2.2,102.2)) < 0.00001, 'distance is zero for identical coordinates');
check(abs(Journey::km(2.2,102.2,2.3,102.3)-Journey::km(2.3,102.3,2.2,102.2)) < 1e-8, 'distance is symmetric');
check(validator(array_replace($journey,['pickup_latitude'=>91]),Journey::rules())->fails(), 'invalid latitude fails server validation');
check(validator(array_replace($journey,['dropoff_longitude'=>'']),Journey::rules())->fails(), 'unconfirmed location fails validation');
check(TravelTimePredictor::routeKey($points) !== TravelTimePredictor::routeKey(array_reverse($points)), 'opposite route directions have different sample keys');
check(count(TravelTimePredictor::features(12,'2030-01-07 08:00:00',20))===6, 'training feature contract has six values');
// Test inference math with an analytic zero-recurrence cell, entirely in memory.
$model=['kernel'=>array_fill(0,6,[0,0,0,0]),'recurrent_kernel'=>[[0,0,0,0]],'bias'=>[0,0,0,0],'dense_kernel'=>[1],'dense_bias'=>2,'feature_mean'=>array_fill(0,6,0),'feature_std'=>array_fill(0,6,1),'target_mean'=>10,'target_std'=>3];
check(abs(TravelTimePredictor::infer($model,[array_fill(0,6,0)])-16)<1e-8, 'LSTM inference applies dense output and target scaling');
$model['bias']=[0,0,1,0];
$expected=0.5*tanh(0.5*tanh(1));
$model['target_mean']=0;$model['target_std']=1;$model['dense_bias']=0;
check(abs(TravelTimePredictor::infer($model,[array_fill(0,6,0)])-$expected)<1e-8, 'LSTM input, candidate and output gates match analytic result');
Carbon::setTestNow();
echo "$checks checks passed; no database writes.\n";
