<?php

use it\thecsea\simple_caldav_client\CalDAVClient;
use it\thecsea\simple_caldav_client\CalDAVException;
use it\thecsea\simple_caldav_client\SimpleCalDAVClient;

class SecureSimpleCalDAVClient extends SimpleCalDAVClient
{
    public function connect($url, $user, $pass, $options = [])
    {
        $client = new CalDAVClient($url, $user, $pass, $options);

        // shortcut: relies on simple-caldav-client 0.1.x private properties; use its TLS option when upstream adds one.
        $handle_property = new ReflectionProperty(CalDAVClient::class, 'ch');
        $handle_property->setAccessible(true);
        $handle = $handle_property->getValue($client);
        if (!curl_setopt_array($handle, [
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
        ])) {
            throw new RuntimeException('Unable to enable TLS certificate verification.');
        }

        if (!$client->isValidCalDAVServer()) {
            if ($client->GetHttpResultCode() == '401') {
                throw new CalDAVException('Login failed', $client);
            } elseif ($client->GetHttpResultCode() == '') {
                throw new CalDAVException('Can\'t reach server', $client);
            }

            throw new CalDAVException('Could\'n find a CalDAV-collection under the url', $client);
        }

        if ($client->GetHttpResultCode() != '200') {
            if ($client->GetHttpResultCode() == '401') {
                throw new CalDAVException('Login failed', $client);
            } elseif ($client->GetHttpResultCode() == '') {
                throw new CalDAVException('Can\'t reach server', $client);
            }

            throw new CalDAVException('Recieved unknown HTTP status while checking the connection after establishing it', $client);
        }

        $client_property = new ReflectionProperty(SimpleCalDAVClient::class, 'client');
        $client_property->setAccessible(true);
        $client_property->setValue($this, $client);
    }
}
