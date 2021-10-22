<?php
require_once '/var/www/html/roundcubemail/vendor/autoload.php';
require_once '/var/www/html/roundcubemail/program/lib/Roundcube/rcube_plugin_api.php';
require_once '/var/www/html/roundcubemail/program/lib/Roundcube/rcube_plugin.php';
require_once '/var/www/html/roundcubemail/plugins/roundcube_caldav/lib/php/ics_file_modification.php';
require_once '/var/www/html/roundcubemail/program/lib/Roundcube/bootstrap.php';

use ICal\Event;
use PHPUnit\Framework\TestCase;

final class event_comparison_test extends TestCase
{
    protected $all_event;

    protected function setUp()
    {


        $this->all_event['test'][] = file_get_contents(__DIR__ . "/data/sample1_after.ics");
        $this->all_event['test'][] = file_get_contents(__DIR__ . "/data/sample1_afsample2_afterter.ics");
        $this->all_event['test'][] = file_get_contents(__DIR__ . "/data/sample3.ics");
        $this->all_event['test'][] = file_get_contents(__DIR__ . "/data/sample4.ics");

        fclose($handler);
        fclose($handler2);
        fclose($handler3);
        fclose($handler4);

        var_dump($this->all_event);
    }

    protected function tearDown()
    {
        $this->roundcube_caldav = null;
    }

}