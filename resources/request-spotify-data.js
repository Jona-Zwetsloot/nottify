const progress = document.getElementById('import-progress').getElementsByTagName('div')[0];
const div = document.getElementById('import-text');
const task = div.getElementsByTagName('p')[0];
const percentage = div.getElementsByTagName('p')[1];
const image = div.getElementsByTagName('img')[0];
const message = document.getElementById('center-message');
const title = message.getElementsByTagName('h3')[0];
const description = message.getElementsByTagName('p')[0];
let tracks = 0, playlists = 0, albums = 0;

// Simple count up animation
function countanimation(element, begin, target, time, update, exact) {
    if (isNaN(begin) || isNaN(target) || isNaN(time) || isNaN(update) || element == null) {
        return;
    }
    var value;
    if (exact == undefined) {
        value = begin;
    }
    else {
        value = exact;
    }
    value = value + (target - begin) / (time / update);
    if (value >= target && begin <= target || value <= target && begin >= target) {
        element.innerHTML = target;
    }
    else {
        exact = value;
        element.innerHTML = Math.round(value);
        setTimeout(countanimation, update, element, begin, target, time, update, exact);
    }
}

function setCount(element, count) {
    setTimeout(function () {
        document.getElementById(element).style.minWidth = (11 * count.toString().length) + 'px';
        countanimation(document.getElementById(element), 0, count, 1000 + Math.random() * 2000, 20);
    }, 500);
}

async function requestData() {
    while (true) {
        let response;
        try {
            response = await fetch('api/spotify.php');
        }
        catch (e) {
            if (response.status == 504) {
                // request_timed_out
            }
            else {
                // error_unknown
            }
            break;
        }
        json = await response.json();
        if (json == null || json.error) {
            // error
            break;
        }
        console.log(json);
        progress.style.width = json.progress + '%';
        div.classList.remove('slide-up');
        void div.offsetHeight;
        div.classList.add('slide-up');
        task.innerText = json.next;
        percentage.innerText = json.progress + '%';
        tracks += json.tracks;
        playlists += json.playlists;
        albums += json.albums;
        if (json.image) {
            image.style.display = 'block';
            image.src = json.image;
        }
        if (!json.continue) {
            progress.classList.add('finished');
            document.getElementsByTagName('title')[0].innerText = 'Done.';
            title.innerText = 'Done.';
            description.innerHTML = 'We\'ve added <span id="track-count">0</span> tracks, <span id="playlist-count">20</span> playlists and <span id="album-count">20</span> albums from Spotify to your library.';
            setCount('track-count', tracks);
            setCount('playlist-count', playlists);
            setCount('album-count', albums);
            break;
        }
    }
}
requestData();