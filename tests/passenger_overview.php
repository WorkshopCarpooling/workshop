<?php
require __DIR__.'/../vendor/autoload.php';
use App\Services\PassengerOverview;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
$at=CarbonImmutable::parse('2026-10-28 08:00:00','Asia/Kuala_Lumpur');
$make=function($id,$status,$rideStatus,$departure,$created)use($at){
 return (object)['booking_id'=>$id,'booking_status'=>$status,'created_at'=>$at->addMinutes($created),
  'ride'=>$rideStatus===null?null:(object)['ride_status'=>$rideStatus,'departure_at'=>$at->addMinutes($departure)]];
};
$bookings=new Collection([
 $make(1,'accepted','scheduled',120,1),$make(2,'pending','scheduled',60,2),
 $make(3,'accepted','scheduled',-10,3),$make(4,'accepted','cancelled',30,4),
 $make(5,'accepted','in_progress',-30,5),$make(6,'declined','scheduled',20,6),
 $make(7,'accepted','completed',-60,7),$make(8,'pending',null,10,8),
 $make(9,'cancelled','scheduled',90,10),$make(10,'accepted','cancelled',90,11),
]);
$checks=0;
$check=function($condition,$label)use(&$checks){if(!$condition)throw new RuntimeException($label);$checks++;echo 'PASS: '.$label.PHP_EOL;};
$result=PassengerOverview::fromBookings($bookings,$at);
$check($result['upcomingCount']===3,'Only active and upcoming pending/accepted journeys are counted');
$check($result['confirmedCount']===2,'Confirmed count excludes past, cancelled and completed rides');
$check($result['pendingCount']===1,'Pending requests remain separate from confirmed journeys');
$check($result['nextBooking']->booking_id===5,'An in-progress ride is the next journey even after its departure time');
$check($result['recentBookings']->pluck('booking_id')->all()===[8,7,6],'Recent bookings exclude cancelled bookings and trips, then use creation order');
$empty=PassengerOverview::fromBookings(new Collection(),$at);
$check($empty['upcomingCount']===0&&$empty['nextBooking']===null&&$empty['recentBookings']->isEmpty(),'Empty data produces a truthful empty overview');
$pending=PassengerOverview::fromBookings(new Collection([$make(9,'pending','scheduled',90,10),$make(10,'pending','scheduled',30,9)]),$at);
$check($pending['confirmedCount']===0&&$pending['pendingCount']===2,'Pending-only journeys do not imply a confirmed seat');
$check($pending['nextBooking']->booking_id===10,'The nearest future departure is selected');
echo 'Passenger overview: '.$checks.' checks passed.'.PHP_EOL;
