<?php
namespace App\Services;
use App\Http\Controllers\RoutesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,Http};

final class NavigationRoad {
    public static function route(array $stops): array {
        $points=[];foreach($stops as $stop){$last=end($points);if(!$last||Journey::km($last['lat'],$last['lng'],$stop['lat'],$stop['lng'])>.001)$points[]=['lat'=>(float)$stop['lat'],'lng'=>(float)$stop['lng']];}
        if(count($points)<2)return ['path'=>$points,'source'=>'approximate','distanceMeters'=>0,'durationSeconds'=>0,'road'=>false];
        $key='navigation-road:'.sha1(json_encode($points).config('services.google_maps.api_key').config('navigation.osrm_url').json_encode(config('navigation.osrm_enabled',true)));
        return Cache::remember($key,1800,function()use($points){
            $first=$points[0];$last=end($points);
            if(config('services.google_maps.api_key')){
                $route=app(RoutesController::class)->computeRoute(new Request(['origin_lat'=>$first['lat'],'origin_lng'=>$first['lng'],'dest_lat'=>$last['lat'],'dest_lng'=>$last['lng'],'intermediates'=>array_slice($points,1,-1)]))->getData(true);
                if(($route['source']??'')==='google_routes_api'&&count($route['path']??[])>1)return $route+['road'=>true];
            }
            if(config('navigation.osrm_enabled',true))try{
                $coordinates=implode(';',array_map(fn($p)=>$p['lng'].','.$p['lat'],$points));
                $data=Http::timeout(5)->acceptJson()->get(rtrim(config('navigation.osrm_url','https://router.project-osrm.org'),'/').'/route/v1/driving/'.$coordinates,
                    ['overview'=>'full','geometries'=>'geojson','steps'=>'false'])->throw()->json();
                $route=$data['routes'][0]??[];$coords=$route['geometry']['coordinates']??[];
                if(($data['code']??'')==='Ok'&&count($coords)>1&&count($coords)<50000){
                    $path=[];foreach($coords as $p){if(count($p)<2||!is_numeric($p[0])||!is_numeric($p[1])||abs($p[0])>180||abs($p[1])>90)throw new \RuntimeException('Invalid road coordinates.');$path[]=['lat'=>(float)$p[1],'lng'=>(float)$p[0]];}
                    return ['path'=>$path,'source'=>'osrm','road'=>true,'distanceMeters'=>(float)$route['distance'],'durationSeconds'=>(float)$route['duration']];
                }
            }catch(\Throwable $error){ /* Render the labelled estimate while keeping the map usable. */ }
            $km=(new RoutePlanner)->distance($points);
            return ['path'=>$points,'source'=>'approximate','road'=>false,'distanceMeters'=>$km*1000,'durationSeconds'=>$km/42*3600];
        });
    }
}
