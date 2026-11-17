<?php
namespace App\Services;

final class CampusDriverArea
{
    public static function settings(): array {
        return config('carpool.driver_area',['label'=>'UTeM Main Campus (Durian Tunggal)','lat'=>2.3139,'lng'=>102.3183,'radius_km'=>5]);
    }
    public static function contains(float $lat,float $lng): bool {
        $area=self::settings();return Journey::km($lat,$lng,(float)$area['lat'],(float)$area['lng'])<=(float)$area['radius_km'];
    }
}
