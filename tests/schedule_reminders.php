<?php

// This existing harness creates schema and fixtures in SQLite memory only.
require __DIR__.'/carbon_and_pages.php';

use App\Services\PassengerReminders;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

Carbon::setTestNow(Carbon::parse('2030-01-07 08:40:00', 'Asia/Kuala_Lumpur'));
$sessionId=DB::table('commute_timetable')->insertGetId(['user_id'=>$p1->user_id,'title'=>'Software engineering lecture','weekday'=>1,'starts_at'=>'09:00:00','ends_at'=>'10:00:00','location'=>'FTMK Lecture Hall','created_at'=>now(),'updated_at'=>now()]);
DB::table('commute_timetable')->insert(['user_id'=>$p2->user_id,'title'=>'Another passenger private class','weekday'=>1,'starts_at'=>'08:50:00','ends_at'=>'09:50:00','location'=>'Private location','created_at'=>now(),'updated_at'=>now()]);
$tomorrowId=DB::table('commute_timetable')->insertGetId(['user_id'=>$p1->user_id,'title'=>'Tomorrow early class','weekday'=>2,'starts_at'=>'08:30:00','ends_at'=>'09:30:00','location'=>'Campus','created_at'=>now(),'updated_at'=>now()]);
$rideReminder=$scheduled->replicate();$rideReminder->departure_at=now()->addMinutes(15);$rideReminder->save();
$rideBooking=\App\Models\RideBooking::create(['ride_id'=>$rideReminder->ride_id,'passenger_id'=>$p1->user_id,'pickup_label'=>'Main gate','seats_requested'=>1,'booking_status'=>'accepted']);
\App\Models\RideBooking::create(['ride_id'=>$rideReminder->ride_id,'passenger_id'=>$p2->user_id,'pickup_label'=>'Another passenger pickup','seats_requested'=>1,'booking_status'=>'pending']);
$events=PassengerReminders::upcoming($p1->user_id);
cpCheck(count($events)===3 && $events[0]['id']==='booking-'.$rideBooking->booking_id,'reminders merge confirmed departures and weekly classes in upcoming order');
cpCheck($events[1]['startsAt']==='2030-01-07T09:00:00+08:00' && $events[2]['id']==='timetable-'.$tomorrowId,'weekly timetable dates use application timezone and include tomorrow within 24 hours');
cpCheck(!str_contains(json_encode($events),'Another passenger'),'reminders never contain another passenger timetable or pickup');
cpCheck(count(PassengerReminders::upcoming($p2->user_id))===1,'pending bookings do not produce confirmed-ride reminders');
$response=cpPage($kernel,$p1,'api/passenger/reminders');
cpCheck($response->getStatusCode()===200 && count($response->getData(true)['events'])===3 && str_contains($response->headers->get('Cache-Control'),'no-store'),'passenger reminder endpoint serves private, uncached, current-user events');
cpCheck(cpPage($kernel,$driver,'api/passenger/reminders')->getStatusCode()!==200 && cpPage($kernel,$admin,'api/passenger/reminders')->getStatusCode()!==200,'drivers and administrators cannot use passenger reminder endpoint');
cpCheck(cpPage($kernel,null,'api/passenger/reminders')->getStatusCode()!==200,'signed-out visitors cannot read reminders');
$rideReminder->update(['ride_status'=>'cancelled']);
cpCheck(count(PassengerReminders::upcoming($p1->user_id))===2,'cancelled rides disappear on schedule refresh');
$rideReminder->update(['ride_status'=>'scheduled']);
$at=Carbon::parse('2030-01-07 09:01:00','Asia/Kuala_Lumpur')->toImmutable();
cpCheck(!in_array('timetable-'.$sessionId,array_column(PassengerReminders::upcoming($p1->user_id,$at),'id'),true),'past sessions do not cause late reminders or next-week events within today’s horizon');
$sundayId=DB::table('commute_timetable')->insertGetId(['user_id'=>$p1->user_id,'title'=>'Sunday boundary','weekday'=>0,'starts_at'=>'00:05:00','ends_at'=>'01:00:00','location'=>'Campus','created_at'=>now(),'updated_at'=>now()]);
$midnight=PassengerReminders::upcoming($p1->user_id,Carbon::parse('2030-01-05 23:50:00','Asia/Kuala_Lumpur')->toImmutable());
cpCheck(count($midnight)===1 && $midnight[0]['id']==='timetable-'.$sundayId && $midnight[0]['startsAt']==='2030-01-06T00:05:00+08:00','weekly reminders handle midnight and Sunday correctly');
$passengerPage=cpPage($kernel,$p1,'dashboard.php')->getContent();
cpCheck(str_contains($passengerPage,'data-schedule-robot') && !str_contains(cpPage($kernel,$driver,'driver.php')->getContent(),'data-schedule-robot'),'robot mounts for active passengers only');
cpCheck(str_contains(cpPage($kernel,$p1,'timetable')->getContent(),'snooze for five minutes'),'timetable explains when the robot reminders appear');
DB::table('commute_timetable')->where('id',$sessionId)->delete();
cpCheck(!in_array('timetable-'.$sessionId,array_column(PassengerReminders::upcoming($p1->user_id),'id'),true),'deleted timetable sessions disappear from the next reminder refresh');

if(in_array('--render',$argv,true)) {
    $payload=['serverTime'=>now()->toIso8601String(),'timezone'=>'Asia/Kuala_Lumpur','events'=>[['id'=>'preview-class','kind'=>'class','title'=>'Software engineering lecture','location'=>'FTMK Lecture Hall','startsAt'=>now()->addMinutes(20)->toIso8601String(),'url'=>'http://workshop.test/timetable','action'=>'View timetable']]];
    $html=str_replace('http://localhost/','http://workshop.test/',$passengerPage);
    $html=str_replace('data-schedule-robot data-endpoint=', 'data-schedule-robot data-preview-events="'.htmlspecialchars(json_encode($payload),ENT_QUOTES,'UTF-8').'" data-endpoint=', $html);
    $html=preg_replace('/<form\b[^>]*>.*?<\/form>/s','',$html);
    $html=str_replace('<body class=', '<body data-test-preview class=', $html);
    $html=str_replace('<div class="app-shell">','<div style="padding:12px;background:#fff2d8;color:#755024;text-align:center;font:13px system-ui">TEST PREVIEW · sample timetable event · your real schedule is unchanged</div><div class="app-shell">',$html);
    $directory=$root.'/public/device-review';if(!is_dir($directory))mkdir($directory,0777,true);
    file_put_contents($directory.'/schedule-robot-preview.html',$html);
}
Carbon::setTestNow();
echo "Schedule reminder checks passed; SQLite memory only.\n";
