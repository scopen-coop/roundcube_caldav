<?php
require_once '/var/www/html/roundcubemail/vendor/autoload.php';
require_once '/var/www/html/roundcubemail/program/lib/Roundcube/rcube_plugin_api.php';
require_once '/var/www/html/roundcubemail/program/lib/Roundcube/rcube_plugin.php';
require_once '/var/www/html/roundcubemail/plugins/roundcube_caldav/lib/php/ics_file_modification.php';
require_once '/var/www/html/roundcubemail/program/lib/Roundcube/bootstrap.php';


use PHPUnit\Framework\TestCase;

final class ics_files_modification_tests extends TestCase
{


//    protected function setUp(){
//        $this->roundcube_caldav = new roundcube_caldav(rcube_plugin_api::get_instance());
//    }
//
//    protected  function tearDown(){
//        $this->roundcube_caldav =null;
//    }

    public function test_extract_event_ics()
    {
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample1_toreduce.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample1_after.ics");

        $split1 = preg_split("/([\n|\r]+(?! ))/",$ics_to_compare);
        $split2= preg_split("/([\n|\r]+(?! ))/",extract_event_ics($ics_to_test, '622ff8fd-094f-4edb-ac3e-5c02caeab'));

        for($i=0;$i<=count($split1);$i++){
            self::assertEquals($split1[$i],$split2[$i] );
        }

    }


    public function test_cancel_one_instance()
    {
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample2_reccurent.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample2_after.ics");


        $split1 = preg_split("/([\n|\r]+(?! ))/",$ics_to_compare);
        $split2= preg_split("/([\n|\r]+(?! ))/",cancel_one_instance($ics_to_test, '20210824T090000Z'));

        for($i=0;$i<=count($split1);$i++){
            self::assertEquals($split1[$i],$split2[$i] );
        }

    }
}

?>


