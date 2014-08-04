<?php

/**
 *  A 'weekly' provider, or 'hints' is designed to prompt the
 *  user to remember what they did in the last week, so they can
 *  fill out their weekly report more accurately.
 *
 *  The class name doesn't matter.. It's picked in the config.
 *
 *  Your constructor should accept the following variables:
 *  - $username: The username of the person the hints are for
 *  - $config: An array of the config options that came from config.php
 *  - $events_from: The beginning of the period to show hints for
 *  - $events_to: The end of the period to show hints for
 *
 *  Then, just create a public function 'printHints' that returns HTML to be
 *  inserted into the sidebar of the "add report" page.
 *
 **/

class RTHints {
    private $rt_api_url, $rt_client_url;
    private $rt_api_user, $rt_api_pass;
    private $events_from, $events_to;
    private $username;

    public function __construct($username, $config, $events_from, $events_to) {
        $this->events_from = $events_from;
        $this->events_to = $events_to;
        $this->rt_api_url = $config['rt_api_url'];
        $this->rt_client_url = $config['rt_client_url'];
        $this->rt_api_user = $config['rt_api_user'];
        $this->rt_api_pass = $config['rt_api_pass'];
        if (isset($config['rt-user-map'][$username])) {
            $this->username = $config['rt-user-map'][$username];
        } else {
            $this->username = $username;
        }
    }

    public function printHints() {
        return $this->printRTForPeriod();
    }

    public function getRTLastPeriod($days) {
        $user = strtolower($this->username);
        $url = $this->rt_api_url;
        $rt_api_user = $this->rt_api_user;
        $rt_api_pass = $this->rt_api_pass;
        $query = urlencode("Owner='{$user}' AND LastUpdated > '-{$days} days'");
        $url = "$url/search/ticket?query=$query&user=$rt_api_user&pass=$rt_api_pass";
        $request = new HttpRequest($url, HTTP_METH_GET);
        $response = $request->send();
        $parsed = http_parse_message($response);
        return $parsed;
    }

    public function getRTForPeriod($start, $end) {
        $user = strtolower($this->username);
        $url = $this->rt_api_url;
        $rt_api_user = $this->rt_api_user;
        $rt_api_pass = $this->rt_api_pass;
        $query = urlencode("Owner='{$user}' AND (LastUpdated >= '{$start}' AND LastUpdated <= '{$end}' AND Status != 'new')");
        $url = "$url/search/ticket?query=$query&user=$rt_api_user&pass=$rt_api_pass";
        $request = new HttpRequest($url, HTTP_METH_GET);
        $response = $request->send();
        $parsed = http_parse_message($response);
        return $parsed;
    }

    public function printRTLast7Days() {
        $tickets = $this->getRTLastPeriod(7);
        $separator = "\r\n";
        $line = strtok($tickets->body, $separator);
        $count = 0;
        $arr = array();
        while ($line !== false) {
            if (preg_match('/^\d+:/', $line)) {
                $arr[$count] = explode(': ', $line, 2);
                $count++;
            }
            $line = strtok( $separator );
        }
        if (count($arr) > 0) {
            $html = "<ul>";
            foreach ($arr as $issue) {
                $html .= '<li><a href="' . $this->rt_client_url . '/Ticket/Display.html?id=' . $issue[0]. '" target="_blank">';
                $html .= "{$issue[0]}</a> - {$issue[1]}</li>";
            }
            $html .= "</ul>";
            return $html;
        } else {
            # No tickets found
            return insertNotify("error", "No RT activity in the last 7 days found");
        }
    }

    public function printRTForPeriod() {
        $range_start = date('Y-m-d', $this->events_from);
        $range_end = date('Y-m-d', $this->events_to);

        $tickets = $this->getRTForPeriod($range_start, $range_end);
        $separator = "\r\n";
        $line = strtok($tickets->body, $separator);
        $count = 0;
        $arr = array();
        while ($line !== false) {
            if (preg_match('/^\d+:/', $line)) {
                $arr[$count] = explode(': ', $line, 2);
                $count++;
            }
            $line = strtok( $separator );
        }
        if (count($arr) > 0) {
            $html = "<ul>";
            foreach ($arr as $issue) {
                $html .= '<li><a href="' . $this->rt_client_url . '/Ticket/Display.html?id=' . $issue[0]. '" target="_blank">';
                $html .= "{$issue[0]}</a> - {$issue[1]}</li>";
            }
            $html .= "</ul>";
            return $html;
        } else {
            # No tickets found
            return insertNotify("error", "No RT activity for $range_start - $range_end was found.");
        }

    }

}

?>
