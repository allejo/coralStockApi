<?php

class Seed
{
    private static $seed;

    public static function getSeed()
    {
        if (!self::$seed)
        {
            $Locations = array("london,uk", "cairo,eg", "boston,usa", "tokyo,jp", "northridge,usa", "auburn,usa");

            $location = $Locations[array_rand($Locations)];
            $url = "http://api.openweathermap.org/data/2.5/weather?q=" . $location . "&units=imperial&appid=c0895deab7cee66c04b35675b3c5be57";

            $curl_handle = curl_init();
            curl_setopt($curl_handle, CURLOPT_URL, $url);
            curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Your application name');
            $query = curl_exec($curl_handle);
            curl_close($curl_handle);

            $json = json_decode($query);

            self::$seed = (is_object($json)) ? $json->main->temp : mt_rand();
        }

        return self::$seed;
    }
}