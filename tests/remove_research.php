<?php
// Reuse the in-memory ride/timetable fixtures; never connects to the live database.
require __DIR__.'/timetable_commutes.php';

use App\Http\Controllers\TimetableController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Symfony\Component\HttpKernel\Exception\{HttpExceptionInterface, NotFoundHttpException};

$controller=new TimetableController;
Auth::guard()->setUser($users[0]);
// Timetable must keep working even when the research-only tables are absent.
foreach(['usability_responses','commute_requirement_surveys','travel_time_samples'] as $table) Schema::dropIfExists($table);
$data=['title'=>'Independent timetable','weekday'=>5,'starts_at'=>'11:00','ends_at'=>'12:00','location'=>'Campus library'];
$response=$controller->store(useNowRequest('timetable','POST',$data));
$id=DB::table('commute_timetable')->where('title',$data['title'])->value('id');
checkNow($id && $response->isRedirect(url('timetable')),'adding a session works without research tables');
rejectedNow(fn()=>$controller->store(useNowRequest('timetable','POST',array_replace($data,['starts_at'=>'11:30']))),'overlapping sessions are still rejected');
rejectedNow(fn()=>$controller->store(useNowRequest('timetable','POST',array_replace($data,['ends_at'=>'10:00']))),'sessions still require an end after their start');
$html=$controller->index()->getContent();
checkNow(str_contains($html,'action="'.url('timetable').'"') && str_contains($html,'action="'.url('timetable/'.$id.'/remove').'"'),'session forms use independent timetable routes');
checkNow(!str_contains($html,'/research'),'passenger timetable and navigation have no Research links');
Auth::guard()->setUser($users[1]);
try{$controller->destroy($id);checkNow(false,'private deletion');}catch(HttpExceptionInterface $e){checkNow($e->getStatusCode()===404 && DB::table('commute_timetable')->where('id',$id)->exists(),'another passenger cannot remove a session');}
Auth::guard()->setUser($users[2]);
foreach([fn()=>$controller->store(useNowRequest('timetable','POST',$data)),fn()=>$controller->destroy($id)] as $call) {
    try{$call();checkNow(false,'driver mutation');}catch(HttpExceptionInterface $e){checkNow($e->getStatusCode()===403,'drivers cannot change passenger timetable sessions');}
}
Auth::guard()->setUser($users[0]);
$controller->destroy($id);
checkNow(!DB::table('commute_timetable')->where('id',$id)->exists(),'the new remove action deletes the owned session');
$routes=app('router')->getRoutes();
foreach([['GET','research'],['POST','research'],['GET','research/export/survey'],['GET','research/export/samples'],['GET','research/export/usability']] as [$method,$path]) {
    try{$routes->match(Request::create('/'.$path,$method));checkNow(false,'retired research route '.$path);}catch(NotFoundHttpException $e){checkNow(true,$method.' '.$path.' is no longer registered');}
}
foreach(['timetable'=>'store','timetable/'.$previewId.'/remove'=>'destroy'] as $path=>$action) {
    $route=$routes->match(Request::create('/'.$path,'POST'));
    checkNow(str_ends_with($route->getActionName(),'@'.$action) && in_array('passenger',$route->gatherMiddleware()) && in_array('web',$route->gatherMiddleware()),'timetable mutation retains passenger and CSRF middleware');
}
echo "$checks total ride, timetable and research-removal checks passed; SQLite memory only.\n";
