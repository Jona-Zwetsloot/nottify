<?php
// Use the radio-browser API to display a list of radio stations.

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) || empty($config)) {
    http_response_code(403);
    header('Content-type: application/json');
    exit(json_encode(['error' => 'This is a backend PHP file. It\'s not accessible from the client-side.']));
}

if (array_key_exists('radio_browser', $config) && !$config['radio_browser']) {
    exit('The radio-browser feature is disabled.');
}

function outputRadioStations($local = false)
{
    global $userLanguage, $countries, $baseURL;
    $country = ((empty($userLanguage) || strlen($userLanguage) > 2) ? 'en' : $userLanguage);
    if ($local) {
        include_once 'countries.php';
    }
    $url = $baseURL . ($local ? '/json/stations/bycountry/' . getCountryName($country) . '?limit=250&hidebroken=true&order=clickcount' : '/json/stations/topclick?limit=250&hidebroken=true');
    $radioJson = requestURL($url, 'radio-browser', true);
    if (empty($radioJson)) {
        return;
    }
    echo '<h2>' . text(($local ? 'local' : 'discover') . '_radio_stations') . '</h2><div>';
    $i = 0;
    foreach ($radioJson as $station) {
        if (!empty($station['stationuuid']) && !empty($station['url_resolved'])) {
            $i++;
            $track = [
                'uuid' => $station['stationuuid'],
                'name' => 'api/audio-proxy?url=' . rawurlencode($station['url_resolved']),
                'meta' => [],
            ];
            $html = '';
            if (!empty($station['favicon']) && $station['favicon'] != 'null') {
                $track['pictures'] = [[
                    'url' => 'api/image-proxy?url=' . rawurlencode($station['favicon']),
                    'mime' => 'image/' . pathinfo(strtok($station['favicon'], '?'), PATHINFO_EXTENSION),
                ]];
                $html .= '<img loading="lazy" src="api/image-proxy?url=' . filter_var(rawurlencode($station['favicon']), FILTER_SANITIZE_SPECIAL_CHARS) . '">';
            } else {
                $html .= '<img src="svg/placeholder.svg">';
            }
            $html .= '<div>';
            if (!empty($station['name'])) {
                $track['meta']['title'] = $station['name'];
                $html .= '<h3>' . filter_var($station['name'], FILTER_SANITIZE_SPECIAL_CHARS) . '</h3>';
            }
            $items = [];
            if (!empty($station['countrycode'])) {
                array_push($items, filter_var($station['countrycode'], FILTER_SANITIZE_SPECIAL_CHARS));
            }
            if (!empty($station['tags'])) {
                $track['meta']['genre'] = explode(',', $station['tags']);
                array_push($items, str_replace(',', ', ', filter_var($station['tags'], FILTER_SANITIZE_SPECIAL_CHARS)));
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
        $dnsRecords = @dns_get_record('_api._tcp.radio-browser.info', DNS_SRV);

        if ($dnsRecords === false || count($dnsRecords) === 0) {
            exitMessage('DNS error', 'Could not retrieve DNS records.');
            return;
        }

        shuffle($dnsRecords);

        foreach ($dnsRecords as $dnsRecord) {
            if (!empty($dnsRecord['target'])) {
                $baseURL = 'https://' . $dnsRecord['target'];

                if (!file_exists('cache')) {
                    mkdir('cache', 0777, true);
                }

                write_file('cache/radio-browser-baseurl.txt', $baseURL);
                break;
            }
        }
    }
}
