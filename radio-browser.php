<?php
// Use the radio-browser API to display a list of radio stations

require_once 'config.php';
if (array_key_exists('radio_browser', $config) && !$config['radio_browser']) {
    exit('The radio-browser feature is disabled.');
}

function outputRadioStations($local = false)
{
    global $userLanguage, $countries;
    if ($local) {
        $country = ((empty($userLanguage) || strlen($userLanguage) > 2) ? 'en' : $userLanguage);
        $file = 'library/radio-' . $country . '.json';
    } else {
        $file = 'library/radio.json';
    }
    $radioDataExists = file_exists('library') && file_exists($file);
    // If radio data does not exist yet, request it from radio-browser
    if (!$radioDataExists) {
        if ($local) {
            include_once 'countries.php';
        }
        $radioDataExists = requestRadioData(
            url: $local ? '/json/stations/bycountry/' . getCountryName($country) . '?limit=250&hidebroken=true&order=clickcount' : '/json/stations/topclick?limit=250&hidebroken=true',
            file: $file
        );
    }
    if (!$radioDataExists) {
        return;
    }
    $radioData = file_get_contents($file, true);
    if (!json_validate($radioData)) {
        echo '<p>Invalid JSON data encountered in radio.json. Could not display radio stations.</p>';
        return;
    }
    $radioJson = json_decode($radioData);
    echo '<h2>' . text(($local ? 'local' : 'discover') . '_radio_stations') . '</h2><div>';
    $i = 0;
    foreach ($radioJson as $station) {
        if (!empty($station->stationuuid) && !empty($station->url_resolved)) {
            $i++;
            $track = [
                'uuid' => $station->stationuuid,
                'name' =>'audio-proxy?url=' . rawurlencode($station->url_resolved),
                'meta' => [],
            ];
            $html = '';
            if (!empty($station->favicon) && $station->favicon != 'null') {
                $track['pictures'] = [[
                    'url' => 'image-proxy?url=' . rawurlencode($station->favicon),
                    'mime' => 'image/' . pathinfo(strtok($station->favicon, '?'), PATHINFO_EXTENSION),
                ]];
                $html .= '<img loading="lazy" src="image-proxy?url=' . filter_var(rawurlencode($station->favicon), FILTER_SANITIZE_SPECIAL_CHARS) . '">';
            } else {
                $html .= '<img src="svg/placeholder.svg">';
            }
            $html .= '<div>';
            if (!empty($station->name)) {
                $track['meta']['title'] = $station->name;
                $html .= '<h3>' . filter_var($station->name, FILTER_SANITIZE_SPECIAL_CHARS) . '</h3>';
            }
            $items = [];
            if (!empty($station->countrycode)) {
                array_push($items, filter_var($station->countrycode, FILTER_SANITIZE_SPECIAL_CHARS));
            }
            if (!empty($station->tags)) {
                $track['meta']['genre'] = explode(',', $station->tags);
                array_push($items, str_replace(',', ', ', filter_var($station->tags, FILTER_SANITIZE_SPECIAL_CHARS)));
            }
            if (count($items) > 0) {
                $track['meta']['subtitle'] = [implode(' • ', $items)];
                $html .= '<p>' . implode(' • ', $items) . '</p>';
            }
            echo '<div' . ($i > 18 ? ' style="display: none !important;"' : '') . ' class="radio tile" data-json="' . filter_var(json_encode($track), FILTER_SANITIZE_SPECIAL_CHARS) . '">' . $html . '</div></div>';
        }
    }
    echo '</div>';
        echo '<a class="view-more">' . text('view_more') . '</a>';
    echo '<br><br>';
}

function getBaseURL()
{
    global $baseURL;
    if (empty($baseURL)) {
        $dnsRecords = dns_get_record('_api._tcp.radio-browser.info', DNS_SRV);

        if ($dnsRecords === false || count($dnsRecords) === 0) {
            echo 'Could not retrieve DNS records.';
        }

        shuffle($dnsRecords);

        foreach ($dnsRecords as $dnsRecord) {
            if (!empty($dnsRecord['target'])) {
                $baseURL = 'https://' . $dnsRecord['target'];

                if (!file_exists('library')) {
                    mkdir('library', 0777, true);
                }

                write_file('library/radio-browser-baseurl.txt', $baseURL);
                break;
            }
        }
    }
}

function requestRadioData($url, $file)
{
    global $baseURL, $config;

    if (empty($baseURL)) {
        echo 'Could not retrieve base URL from DNS records: ' . var_dump($dnsRecords);
        return false;
    }

    $ch = curl_init($baseURL . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $config['useragent']);
    $output = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($status < 200 || $status >= 300) {
        echo 'Invalid status code (' . $status . ') with output ' . filter_var($output, FILTER_SANITIZE_SPECIAL_CHARS);
        return false;
    }

    if (!json_validate($output)) {
        echo 'Output is not valid JSON: ' . filter_var($output, FILTER_SANITIZE_SPECIAL_CHARS);
        return false;
    }

    if (!file_exists('library')) {
        mkdir('library', 0777, true);
    }

    write_file($file, $output);
    return true;
}
