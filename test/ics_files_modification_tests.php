<?php
require_once __DIR__ . "/../../../vendor/autoload.php";
require_once __DIR__ . "/../../../program/lib/Roundcube/bootstrap.php";
require_once __DIR__ . "/../../../program/include/iniset.php";
require_once __DIR__ . "/../../../program/lib/Roundcube/rcube_plugin_api.php";
require_once __DIR__ . "/../../../program/lib/Roundcube/rcube_plugin.php";
require_once __DIR__ . '/../lib/php/ics_file_modification.php';


use PHPUnit\Framework\TestCase;

final class ics_files_modification_tests extends TestCase
{

    /**
     * commande PHPUnit:   ../../vendor/phpunit/phpunit/phpunit test/ics_files_modification_tests.php
     */


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

        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", extract_event_ics($ics_to_test, '622ff8fd-094f-4edb-ac3e-5c02caeab'));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }

    }


    public function test_cancel_one_instance()
    {
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample2_reccurent.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample2_after.ics");


        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", cancel_one_instance($ics_to_test, '20210824T090000Z'));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }

    }


    public function test_change_date_ics()
    {
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample4_changed_date.ics");


        // Test standard use
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample4_standard.ics");
        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_date_ics('20210720T150000Z', '20210720T160000Z', $ics_to_test));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }


        // Test with duration field
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample4_duration.ics");

        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_date_ics('20210720T150000Z', '20210720T160000Z', $ics_to_test));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }


        // Test with no date end field
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample4_no_dt_end.ics");

        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_date_ics('20210720T150000Z', '20210720T160000Z', $ics_to_test));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }

        // Test recurrent event
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample5_recurrent_changed_date.ics");
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample5_recurrent.ics");

        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_date_ics('20210824T100000Z', '20210824T103000Z', $ics_to_test, 7200, 7200));


        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }

    }

    public function test_change_location_ics()
    {
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample3_location.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample3_changed.ics");


        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_location_ics('AlbertVille 16 rue du cuachemar', $ics_to_test));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }

        $ics_to_test = file_get_contents(__DIR__ . "/data/sample3_no_location.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample3_no_location_changed.ics");

        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_location_ics('AlbertVille 16 rue du cuachemar', $ics_to_test));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }
    }

    public function test_change_status_ics()
    {
        // If Status field is present
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample6_status.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample6_changed.ics");


        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_status_ics('CONFIRMED', $ics_to_test));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }

        //Else
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample6_no_status.ics");

        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_status_ics('CONFIRMED', $ics_to_test));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }
    }


    public function test_change_partstat_ics()
    {
        // Changement status
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample7_partstat.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample7_changed.ics");

        $split1 = preg_split("/([\n\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n\r]+(?! ))/", change_partstat_ics($ics_to_test, 'DECLINED', 'test.test@test.fr'));


        $split1 = preg_replace("/[\r\n ]/", '', $split1);
        $split2 = preg_replace("/[\r\n ]/", '', $split2);

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }

        // Transformation de RSVP=FALSE a RSVP=TRUE
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample7_changed_RSVP_TRUE.ics");

        $split1 = preg_split("/([\n\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n\r]+(?! ))/", change_partstat_ics($ics_to_test, 'TENTATIVE', 'test.test@test.fr'));


        $split1 = preg_replace("/[\r\n ]/", '', $split1);
        $split2 = preg_replace("/[\r\n ]/", '', $split2);

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }

        // Ajout du champs RSVP si il n'existe pas
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample7_partstat_no_RSVP.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample7_changed.ics");

        $split1 = preg_split("/([\n\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n\r]+(?! ))/", change_partstat_ics($ics_to_test, 'CANCELLED', 'test.test@test.fr'));


        $split1 = preg_replace("/[\r\n ]/", '', $split1);
        $split2 = preg_replace("/[\r\n ]/", '', $split2);

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }
    }

    public function test_change_method_ics()
    {
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample8_method.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample8_changed.ics");


        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_method_ics($ics_to_test, 'DECLINECOUNTER'));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }
    }

    public function test_change_sequence_ics()
    {
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample1_after.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample9_sequence.ics");


        $split1 = preg_split("/([\n|\r]+(?! ))/", $ics_to_compare);
        $split2 = preg_split("/([\n|\r]+(?! ))/", change_sequence_ics($ics_to_test));

        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }
    }

    public function test_change_partstat_of_all_attendees_to_need_action()
    {
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample10_Need_action.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample10_changed.ics");


        $split1 = preg_split("/(\n(?! ))/", $ics_to_compare);
        $split2 = preg_split("/(\n(?! ))/", change_partstat_of_all_attendees_to_need_action($ics_to_test));

        $split1 = preg_replace("/[\r\n ]/", '', $split1);
        $split2 = preg_replace("/[\r\n ]/", '', $split2);
        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }
    }

    public function test_find_time_zone()
    {
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample11_time_zone.ics");

        self::assertEquals(7200,find_time_zone($ics_to_test));
    }


    public function test_delete_comment_section_ics()
    {
        $ics_to_test = file_get_contents(__DIR__ . "/data/sample12_comment.ics");
        $ics_to_compare = file_get_contents(__DIR__ . "/data/sample12_no_comment.ics");


        $split1 = preg_split("/(\n(?! ))/", $ics_to_compare);
        $split2 = preg_split("/(\n(?! ))/", delete_comment_section_ics($ics_to_test));

        $split1 = preg_replace("/[\r\n ]/", '', $split1);
        $split2 = preg_replace("/[\r\n ]/", '', $split2);
        for ($i = 0; $i <= count($split1); $i++) {
            self::assertEquals($split1[$i], $split2[$i]);
        }
    }



}


?>


