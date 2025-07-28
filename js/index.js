const albumImage = document.getElementById('album-image');
const albumList = document.getElementById('album-listview');
const albumTab = document.getElementById('album-tab');
const lyricsTab = document.getElementById('lyrics-tab');
const artistTab = document.getElementById('artist-tab');
const profileTab = document.getElementById('profile-tab');
const homeTab = document.getElementById('home-tab');
const info = document.getElementById('info');
const queueButton = document.getElementById('queue');
const lyricButton = document.getElementById('lyrics');
const friendsButton = document.getElementById('friend-btn');
const lyricContainer = document.getElementById('lyric-container');
const isContentEditable = document.getElementsByTagName('body')[0].dataset.changeMetadata == 'true';
const mobileNavbar = document.getElementById('mobile-navbar');
const navHome = mobileNavbar.getElementsByTagName('button')[0];
const navQueue = mobileNavbar.getElementsByTagName('button')[1];
const navLibrary = mobileNavbar.getElementsByTagName('button')[2];
const albumMaxLoadedTrackCount = 1000;

const leftPanel = document.getElementById('left-panel');
const middlePanel = document.getElementById('middle-panel');
const infoPanel = document.getElementById('track-extra-info');
const queuePanel = document.getElementById('queue-list');
const friendsPanel = document.getElementById('friend-list');
let lyricsInterval;
let openedAlbum;
let openedArtist;
let contextMenuTrack;
let contextMenuType;
let search, addSearch;
let initialPageOpened;

const contextMenu = document.getElementById('music-actions-menu');
leftPanel.addEventListener('scroll', hideContextMenu, { passive: true });
middlePanel.addEventListener('scroll', hideContextMenu, { passive: true });
document.documentElement.addEventListener('scroll', hideContextMenu, { passive: true });
function hideContextMenu() {
    contextMenu.classList.remove('open');
    if (contextMenuTrack) {
        contextMenuTrack.classList.remove('contextmenu');
        contextMenuTrack = null;
    }
}

function searchObject(obj, search) {
    search = search.toLocaleLowerCase();
    for (const key in obj) {
        if (obj.hasOwnProperty(key)) {
            const value = obj[key];
            if (typeof value === 'object' && value !== null) {
                if (searchObject(value, search)) {
                    return true;
                }
            } else if (value && value.toString().toLocaleLowerCase().indexOf(search) != -1) {
                return true;
            }
        }
    }
    return false;
}

function searchLibrary(e) {
    if (e.key != 'Enter') {
        return;
    }
    open('home');
    if (this.value == '') {
        document.getElementById('home-tab').classList.remove('search-results');
        return;
    }
    document.getElementById('home-tab').classList.add('search-results');
    document.getElementById('search-results').innerHTML = '';

    let searchTracks = [];
    let i = 1;
    const searches = this.value.split(' ');
    for (const track in tracks) {
        let found = true;
        for (const search of searches) {
            found = searchObject(tracks[track], search);
            if (!found) {
                break;
            }
        }
        if (found) {
            searchTracks.push({
                id: track,
                number: i,
            });
            i++;
        }
    }

    let searchAlbums = [];
    i = 1;
    for (const album in albums) {
        let found = true;
        for (const search of searches) {
            found = searchObject(albums[album], search);
            if (!found) {
                break;
            }
        }
        if (found) {
            searchAlbums.push(album);
            i++;
        }
    }
    const parent = document.createElement('div');
    for (const album of searchAlbums) {
        const albumElement = document.createElement('div');
        albumElement.classList.add('tile');
        albumElement.classList.add('search-result');
        albumElement.addEventListener('click', function () {
            albumElement.classList.toggle('active');
            if (albumElement.classList.contains('active') || document.documentElement.clientWidth <= 800) {
                search = '';
                openAlbum(album, true);
            }
            else {
                open('home');
            }
        });
        const img = document.createElement('img');
        img.loading = 'lazy';
        img.src = albums[album].picture ? albums[album].picture.url : 'svg/placeholder.svg';
        albumElement.appendChild(img);
        const div = document.createElement('div');
        const h3 = document.createElement('h3');
        h3.innerText = albums[album].name;
        div.appendChild(h3);
        const p = document.createElement('p');
        artistLinks(p, albums[album].artists, true);
        div.appendChild(p);
        albumElement.appendChild(div);
        parent.appendChild(albumElement);
    }
    document.getElementById('search-results').appendChild(parent);

    openedAlbum = null;
    const table = getTrackTable(searchTracks);
    document.getElementById('search-results').appendChild(table);
}

document.getElementById('search').addEventListener('keydown', searchLibrary);

function text(key) {
    if (translations[key]) {
        if (translations[key].constructor === Array) {
            return translations[key][Math.floor(Math.random() * translations[key].length)];
        }
        else {
            return translations[key];
        }
    }
    else {
        return key;
    }
}

navHome.innerText = text('home');
navHome.addEventListener('click', function () {
    document.getElementById('search').value = '';
    document.getElementById('home-tab').classList.remove('search-results');
    open('home');
});
navQueue.innerText = text('queue');
navQueue.addEventListener('click', function () {
    open('queue');
});
navLibrary.innerText = text('library');
navLibrary.addEventListener('click', function () {
    open('library');
});

let layout = localStorage.getItem('layout') ? localStorage.getItem('layout') : 'list';
document.getElementById('toggle-layout').addEventListener('click', function () {
    layout = layout == 'list' ? 'grid' : 'list';
    this.src = 'svg/' + (layout == 'list' ? 'grid' : 'list') + '.svg';
    localStorage.setItem('layout', layout);
    if (layout == 'list') {
        for (const album of albumList.children) {
            album.classList.add('album');
            album.classList.remove('tile');
        }
    }
    else if (layout == 'grid') {
        for (const album of albumList.children) {
            album.classList.add('tile');
            album.classList.remove('album');
        }
    }
});

let timeout;
document.addEventListener('touchstart', (e) => {
    timeout = setTimeout(() => {
        const event = new MouseEvent('contextmenu', {
            bubbles: true,
            cancelable: true,
            view: window,
        });
        e.target.dispatchEvent(event);
    }, 500);
});
document.addEventListener('touchend', () => {
    clearTimeout(timeout);
});

document.addEventListener('contextmenu', (e) => {
    let element = e.target;
    hideContextMenu();
    while (element) {
        if ((element.tagName == 'TR' && element.parentElement.tagName == 'TBODY') || element.classList.contains('album') || (element.classList.contains('tile') && !element.classList.contains('radio') && !element.classList.contains('search-result'))) {
            contextMenuType = (element.tagName == 'TR' || element.classList.contains('queue-item')) ? 'track' : 'album';
            contextMenuTrack = element;
            break;
        }
        element = element.parentElement;
    }
    if (contextMenuTrack == null) {
        return;
    }
    contextMenuTrack.classList.add('contextmenu');
    e.preventDefault(); // Prevent default context menu
    if (element.classList.contains('spotify-content')) {
        document.getElementById('remove-from-album').style.display = 'none';
        document.getElementById('delete').style.display = 'none';
    }
    else {
        document.getElementById('remove-from-album').removeAttribute('style');
        document.getElementById('delete').removeAttribute('style');
    }
    document.getElementById('remove-from-album').getElementsByTagName('p')[0].innerText = text(contextMenuType == 'track' ? 'remove_from_album' : 'remove_album');
    contextMenu.classList.add('open');
    contextMenu.style.left = e.pageX + 'px';
    contextMenu.style.top = e.pageY + 'px';
});

document.addEventListener('click', hideContextMenu);

document.getElementById('delete').getElementsByTagName('p')[0].innerText = text('delete');
document.getElementById('delete').addEventListener('click', async function () {
    if (contextMenuTrack == null) {
        return;
    }
    const title = contextMenuTrack.dataset.id;
    let formData = new FormData();
    if (contextMenuType == 'track') {
        formData.append('track', title);
        delete tracks[title];
        for (const id in albums) {
            for (let i = 0; i < albums[id].tracks.length; i++) {
                if (albums[id].tracks[i] == title) {
                    albums[id].tracks.splice(i, 1);
                    break;
                }
            }
        }
        if (player.track == title) {
            player.track = null;
        }
        const json = await request('delete', {
            method: 'POST',
            body: formData
        }, ['status']);

        if (json) {
            populateFeed();
            player.update();
            if (openedAlbum) {
                openAlbum(openedAlbum);
            }
            else {
                open('home');
            }
            updateExtraInfoPanel();
        }
    }
    else if (contextMenuType == 'album') {
        await deleteAlbum(title, true);
    }
});

async function request(url, options, required = []) {
    let response;
    try {
        response = await fetch(url, options);
    }
    catch (e) {
        if (response.status == 504) {
            sendNotification('error', 'request_timed_out');
        }
        else {
            sendNotification('error', 'error_unknown');
        }
        return;
    }
    json = await response.json();
    if (json.error) {
        sendNotification('error', json.error);
        return;
    }
    for (const key of required) {
        if (json[key] == null) {
            console.log(key);
            sendNotification('error', 'error_unknown');
            return;
        }
    }
    return json;
}

document.getElementById('remove-from-album').addEventListener('click', async function () {
    if (contextMenuTrack == null) {
        return;
    }
    if (contextMenuType == 'track' && openedAlbum != null) {
        albums[openedAlbum].tracks = albums[openedAlbum].tracks.filter(item => item !== contextMenuTrack.dataset.id);

        let formData = new FormData();
        formData.append('album', openedAlbum);
        if (albums[openedAlbum].tracks.length == 0) {
            formData.append('tracks[]', null);
        }
        else {
            for (const albumTrack of albums[openedAlbum].tracks) {
                formData.append('tracks[]', albumTrack);
            }
        }
        const json = await request('edit', {
            method: 'POST',
            body: formData
        }, ['status']);
        if (json) {
            sendNotification('removed_from_playlist', 'removed_from_playlist_description');
            openAlbum(openedAlbum);
        }
    }
    else if (contextMenuType == 'album') {
        deleteAlbum(contextMenuTrack.dataset.id, false);
    }
});

async function deleteAlbum(id, permanent) {
    delete albums[id];
    if (player.album == id) {
        player.album = null;
        if (permanent) {
            player.track = null;
            player.queue = [];
            player.update();
        }
    }
    if (openedAlbum == id) {
        openedAlbum = null;
        open('home');
    }
    else if (permanent && openedAlbum) {
        openAlbum(openedAlbum);
    }
    populateFeed();

    let formData = new FormData();
    formData.append('album', id);
    formData.append('permanent', permanent ? 'true' : 'false');
    const json = await request('delete', {
        method: 'POST',
        body: formData
    }, ['status']);
    if (json) {
        sendNotification('album_removed', 'album_removed_description');
    }
}

document.getElementById('add-to-queue').getElementsByTagName('p')[0].innerText = text('add_to_queue');
document.getElementById('add-to-queue').addEventListener('click', function () {
    if (contextMenuTrack == null) {
        return;
    }
    let first = true;
    const id = contextMenuTrack.dataset.id;
    if (contextMenuType == 'track') {
        if (!player.playing && player.queue.length == 0) {
            player.play(id, openedAlbum);
        }
        else {
            let index = 0;
            for (const item of player.queue) {
                if (item.album) {
                    break;
                }
                index++;
            }
            player.queue.splice(index, 0, {
                track: id,
                album: false,
            });
            sendNotification('added_to_queue', 'added_to_queue_track');
        }
    }
    else if (contextMenuType == 'album') {
        for (const track of albums[id].tracks) {
            if (first && player.track == null && queue.length == 0) {
                first = false;
                player.play(track, id);
            }
            else {
                player.queue.push({
                    track: track,
                    album: id,
                });
            }
        }
        sendNotification('added_to_queue', 'added_to_queue_album');
    }
    if (player.track) {
        updateQueue();
    }
});

document.getElementById('fullscreen').addEventListener('click', function () {
    document.getElementsByTagName('body')[0].classList.toggle('fullscreen');
    if (document.getElementsByTagName('body')[0].classList.contains('fullscreen')) {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen();
        }
    }
    else {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        }
    }
    updateFullscreenContainer();
});

player.player.addEventListener('click', function () {
    if (!player.player.classList.contains('mobile-expanded') && document.documentElement.clientWidth <= 800) {
        open('track');
    }
});

window.addEventListener('popstate', openContentBasedOnQueryString);

function updateFullscreenContainer() {
    document.getElementById('fullscreen-container').innerText = '';
    const image = document.createElement('img');
    image.src = player.track ? (tracks[player.track].pictures.length > 1 ? (tracks[player.track].pictures[1].url + '?v=' + tracks[player.track].pictures[1].version) : (tracks[player.track].pictures[0] ? (tracks[player.track].pictures[0].url + '?v=' + tracks[player.track].pictures[0].version) : 'svg/placeholder.svg')) : 'svg/placeholder.svg';
    if (player.track == null || tracks[player.track].pictures.length <= 1) {
        image.classList.add('blur');
        const album = document.createElement('img');
        album.src = player.track ? (tracks[player.track].pictures[0] ? (tracks[player.track].pictures[0].url + '?v=' + tracks[player.track].pictures[0].version) : 'svg/placeholder.svg') : 'svg/placeholder.svg';
        album.classList.add('album-cover-big');
        document.getElementById('fullscreen-container').appendChild(album);
    }
    document.getElementById('fullscreen-container').appendChild(image);
}

info.addEventListener('click', function () {
    open('track');
});

queueButton.addEventListener('click', function () {
    open('queue');
});

friendsButton.addEventListener('click', openFriends);
async function openFriends() {
    open('friends');
    const response = await fetch('friends');
    document.getElementById('friend-listview').innerHTML = await response.text();
    for (const element of document.getElementById('friend-listview').getElementsByTagName('button')) {
        element.addEventListener('click', async function () {
            let parent = this.getElementsByClassName('lastfm-playing')[0];
            this.classList.toggle('expanded');
            if (!this.classList.contains('expanded')) {
                parent.innerHTML = '';
                return;
            }
            parent.innerHTML = '<span class="loader"></span>';
            const response = await fetch('now-playing?q=' + element.getElementsByTagName('h3')[0].innerText);
            parent.innerHTML = await response.text();
            for (const element of parent.getElementsByClassName('progress')) {
                if (element.dataset.step) {
                    const step = parseFloat(element.dataset.step);
                    let width = parseFloat(element.dataset.width);
                    setInterval(function () {
                        element.style.width = width + '%';
                        width += step;
                    }, 1000);
                }
            }
        })
    }
}

let previousScrollHeight = 0;
lyricButton.addEventListener('click', function (e) {
    e.stopPropagation();
    lyricButton.classList.toggle('active');
    const scrollParent = document.documentElement.clientWidth <= 800 ? document.getElementsByTagName('html')[0] : middlePanel;
    if (lyricButton.classList.contains('active')) {
        previousScrollHeight = scrollParent.scrollTop;
        displayLyrics();
        return;
    }
    else if (openedArtist) {
        open('artist');
        scrollParent.scrollTo({
            top: previousScrollHeight,
            behavior: 'instant',
        });
    }
    else if (openedAlbum) {
        open('album');
        scrollParent.scrollTo({
            top: previousScrollHeight,
            behavior: 'instant',
        });
    }
    else {
        open('home');
    }
});

for (const element of document.getElementsByClassName('radio tile')) {
    let radioTrack = JSON.parse(element.dataset.json);
    radioTrack.radio = true;
    tracks[radioTrack.name] = radioTrack;
    element.addEventListener('click', async function () {
        for (const element of document.getElementsByClassName('radio tile active')) {
            element.classList.remove('active');
            element.classList.remove('now-playing');
        }
        element.classList.add('active');
        element.classList.add('now-playing');
        player.album = null;
        player.play(radioTrack.name);
        if (document.getElementsByTagName('body')[0].dataset.trackRadioClicks == 'true' && document.getElementsByTagName('body')[0].dataset.radioBrowserBaseurl) {
            const json = await request(document.getElementsByTagName('body')[0].dataset.radioBrowserBaseurl + '/json/url/' + radioTrack.uuid);
            console.log(json);
        }
    });
}
for (const element of document.getElementsByClassName('view-more')) {
    element.addEventListener('click', function () {
        for (const child of element.previousElementSibling.children) {
            child.removeAttribute('style');
        }
        this.remove();
    });
}

function addDefaultAlbum(album, name, img) {
    if (albums[album] == null) {
        albums[album] = {
            'name': name,
            'picture': {
                'url': img,
                'mime': 'image\/svg',
                'version': 1,
                'track': false
            },
            'tracks': [],
            'artists': [],
            'added': Math.round(Date.now() / 1000),
            'version': 1
        };
    }
}

function addMissingMetaData() {
    addDefaultAlbum('all_tracks', 'Disconnected', 'svg/disconnected.svg');
    addDefaultAlbum('disconnected', 'Disconnected', 'svg/disconnected.svg');
    albums['disconnected'].tracks = [];
    let disconnectedModified = false;
    for (let id in tracks) {
        if (tracks[id].source == 'spotify' && document.getElementsByTagName('body')[0].dataset.spotifyToken == null) {
            delete tracks[id];
            for (let albumId in albums) {
                albums[albumId].tracks = albums[albumId].tracks.filter(item => item !== id);
            }
            continue;
        }
        if (tracks[id].meta == null) {
            tracks[id].meta = [];
        }
        if (tracks[id].format == null) {
            tracks[id].format = [];
        }
        if (tracks[id].meta.title == '') {
            tracks[id].meta.title = null;
        }
        if (tracks[id].meta.artists) {
            tracks[id].meta.artist = tracks[id].meta.artists.join(', ');
        }
        if (tracks[id].meta.artist == '') {
            tracks[id].meta.artist = null;
        }
        if (tracks[id].meta.album == '') {
            tracks[id].meta.album = null;
        }
        if (tracks[id].pictures == null) {
            tracks[id].pictures = [];
        }
        if (!tracks[id].radio) {
            let connected = false;
            for (const album in albums) {
                if (albums[album].tracks.includes(id)) {
                    connected = true;
                    break;
                }
            }
            if (!connected) {
                disconnectedModified = true;
                albums['disconnected'].tracks.push(id);
            }
        }
        if (albums['all_tracks'] && !tracks[id].radio) {
            albums['all_tracks'].tracks.push(id);
        }
    }
    for (let id in albums) {
        if (albums[id].source == 'spotify' && document.getElementsByTagName('body')[0].dataset.spotifyToken == null) {
            delete albums[id];
            continue;
        }
        if (albums[id].picture == null) {
            albums[id].picture = {
                'url': 'svg/placeholder.svg',
                'mime': 'image/svg+xml',
                'version': 1,
                'track': false,
            };
        }
        if (id == 'all_tracks') {
            albums[id].name = text('all_tracks');
        }
        if (id == 'favorites') {
            albums[id].name = text('favorites');
        }
        if (id == 'disconnected') {
            albums[id].name = text('disconnected');
        }
    }
    console.log(disconnectedModified);
    if (disconnectedModified) {
        let formData = new FormData();
        formData.append('album', 'disconnected');
        for (const track of albums['disconnected'].tracks) {
            formData.append('tracks[]', track);
        }
        request('edit', {
            method: 'POST',
            body: formData
        });
    }
}
addMissingMetaData();

async function populateFeed() {
    document.getElementById('toggle-layout').src = 'svg/' + (layout == 'list' ? 'grid' : 'list') + '.svg';
    albumList.innerText = '';
    for (let id in albums) {
        if (id == 'disconnected' && albums[id].tracks.length == 0 || albums[id].hide) {
            continue;
        }
        const parent = document.createElement('div');
        parent.dataset.id = id;
        if (layout == 'grid') {
            parent.classList.add('tile');
        }
        else {
            parent.classList.add('album');
        }
        if (player.album == id && albums[player.album] && albums[player.album].tracks.includes(player.track)) {
            parent.classList.add('now-playing');
        }

        const img = document.createElement('img');
        img.loading = 'lazy';
        img.src = albums[id].picture.url + '?v=' + albums[id].picture.version;
        parent.appendChild(img);

        const info = document.createElement('div');
        const title = document.createElement('h3');
        title.innerText = albums[id].name ? albums[id].name : text('unknown_album');
        info.appendChild(title);
        const artist = document.createElement('p');
        const artistString = albums[id].artists.join(', ');
        artist.innerText = albums[id].tracks.length + ' ' + text(albums[id].tracks.length == 1 ? 'track' : 'tracks') + (artistString == '' ? '' : (' • ' + artistString));
        if (albums[id].source && albums[id].source == 'spotify') {
            artist.innerHTML = '<img src="svg/spotify.svg">' + artist.innerHTML;
        }
        info.appendChild(artist);
        parent.appendChild(info);

        parent.addEventListener('click', function () {
            parent.classList.add('active');
            search = '';
            openAlbum(id, true);
        });

        img.addEventListener('click', function (e) {
            if (parent.classList.contains('tile')) {
                return;
            }
            e.stopPropagation();
            search = '';
            if (parent.classList.contains('now-playing') && player.playing) {
                player.togglePlay();
            }
            else {
                player.queue = [];
                for (const track of albums[id].tracks) {
                    player.queue.push({
                        track: track,
                        album: id,
                    });
                }
                if (player.random) {
                    shuffleArray(player.queue);
                }
                player.album = id;
                const queueItem = player.queue[0].track;
                player.loopQueue = player.queue.slice();
                player.queue.shift();
                player.play(queueItem);
            }
        });

        albumList.appendChild(parent);
    }
}
function artistLinks(element, track, trackIsArtistArray = false) {
    element.innerText = '';
    let artists = trackIsArtistArray ? track : (tracks[track].meta.artist ? tracks[track].meta.artist.split(', ') : tracks[track].meta.artists);
    if (artists == null) {
        element.appendChild(document.createTextNode(text('unknown_artist')));
        return;
    }
    for (let i = 0; i < artists.length; i++) {
        const span = document.createElement('span')
        span.innerText = artists[i];
        span.addEventListener('click', function (e) {
            e.stopPropagation();
            openArtist(artists[i]);
        });
        element.appendChild(span);
        if (i != artists.length - 1) {
            element.appendChild(document.createTextNode(', '));
        }
    }
}

let sort = 'track';
let order = 'asc';
function setSort(newSort, albumId) {
    order = sort == newSort ? (order == 'asc' ? 'desc' : 'asc') : 'asc';
    sort = newSort;
    openAlbum(albumId);
}

function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]]; // Swap elements
    }
    return array;
}

function isElementInViewport(el) {
    const rect = el.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

function getTrackTable(albumTracks, albumId, showPictures = false, artistPage = false) {
    const table = document.createElement('table');
    table.id = 'album-track-list';

    if (albums[albumId] && (albumId == 'all_tracks' || albumId == 'favorites' || albumId == 'disconnected' || (albums[albumId].source && albums[albumId].source == 'spotify'))) {
        showPictures = true;
    }

    const itemsPerPage = (artistPage ? 10 : albumMaxLoadedTrackCount) + 1;

    const colgroup = document.createElement('colgroup');
    const col1 = document.createElement('col');
    col1.style.width = 'max(50px, 3em)';
    const col2 = document.createElement('col');
    col2.style.width = 'calc(100% - 50px)';
    const col3 = document.createElement('col');
    col3.style.width = '15%';
    const col4 = document.createElement('col');
    col4.style.width = '15%';
    colgroup.appendChild(col1);
    colgroup.appendChild(col2);
    colgroup.appendChild(col3);
    colgroup.appendChild(col4);
    table.appendChild(colgroup);

    if (albumId) {
        const thead = document.createElement('thead');
        const thead_tr = document.createElement('tr');
        const thead_hashtag = document.createElement('th');
        thead_hashtag.innerText = '#';
        if (sort == 'track') {
            thead_hashtag.classList.add('sort-' + order);
            if (order == 'desc') {
                albumTracks.reverse();
            }
        }
        thead_hashtag.addEventListener('click', function () {
            setSort('track', albumId);
        });
        thead_tr.appendChild(thead_hashtag);
        const thead_title = document.createElement('th');
        thead_title.innerText = text('title');
        if (sort == 'title') {
            thead_title.classList.add('sort-' + order);
            albumTracks.sort(function (a, b) {
                a = tracks[a.id];
                b = tracks[b.id];
                if (order == 'desc') {
                    const temp = a;
                    a = b;
                    b = temp;
                }
                return a.meta.title.localeCompare(b.meta.title);
            });
        }
        thead_title.addEventListener('click', function () {
            setSort('title', albumId);
        });
        thead_tr.appendChild(thead_title);
        const thead_year = document.createElement('th');
        thead_year.innerText = text('year');
        if (sort == 'year') {
            thead_year.classList.add('sort-' + order);
            albumTracks.sort(function (a, b) {
                a = tracks[a.id];
                b = tracks[b.id];
                if (order == 'desc') {
                    const temp = a;
                    a = b;
                    b = temp;
                }
                if (a.meta.year == null && b.meta.year == null) {
                    return 0;
                }
                else if (a.meta.year) {
                    return 1;
                } else if (b.meta.year) {
                    return -1;
                }
                return a.meta.year - b.meta.year;
            });
        }
        thead_year.addEventListener('click', function () {
            setSort('year', albumId);
        });
        thead_tr.appendChild(thead_year);
        const thead_duration = document.createElement('th');
        thead_duration.innerText = text('duration');
        if (sort == 'duration') {
            thead_duration.classList.add('sort-' + order);
            albumTracks.sort(function (a, b) {
                a = tracks[a.id];
                b = tracks[b.id];
                if (order == 'desc') {
                    const temp = a;
                    a = b;
                    b = temp;
                }
                return a.format.duration - b.format.duration;
            });
        }
        thead_duration.addEventListener('click', function () {
            setSort('duration', albumId);
        });
        thead_tr.appendChild(thead_duration);
        thead.appendChild(thead_tr);
        table.appendChild(thead);
    }

    const tbody = document.createElement('tbody');
    const tbody_tr = document.createElement('tr');
    const tbody_td = document.createElement('td');
    tbody_tr.appendChild(tbody_td);
    tbody.appendChild(tbody_tr);
    i = 1;
    let loadedPages = 0;
    for (const id of albumTracks) {
        displayRow(id);
        i++;
        if (i % itemsPerPage == 0) {
            loadedPages++;
            break;
        }
    }
    if (loadedPages > 0) {
        let button;
        function addNewItemsOnScroll() {
            if (tbody == null || (loadedPages + 1) * itemsPerPage >= albumTracks.length) {
                middlePanel.removeEventListener('scroll', addNewItemsOnScroll);
                if (button) {
                    button.remove();
                }
            }
            if (isElementInViewport(tbody.children[tbody.childElementCount - 1])) {
                i = 1;
                for (const id of albumTracks) {
                    if (i < loadedPages * itemsPerPage + 1) {
                        i++;
                        continue;
                    }
                    displayRow(id);
                    i++;
                    if (i % itemsPerPage == 0) {
                        break;
                    }
                }
                loadedPages++;
            }
        }
        if (artistPage) {
            setTimeout(function () {
                if (table && table.parentElement) {
                    button = document.createElement('button');
                    button.classList.add('button');
                    button.innerText = 'load_more';
                    button.style.marginTop = '10px';
                    button.addEventListener('click', addNewItemsOnScroll);
                    table.after(button);
                }
            }, 1);
        }
        else {
            middlePanel.addEventListener('scroll', addNewItemsOnScroll, { passive: true })
        }
    }
    function displayRow(id) {
        const j = i;
        const tbody_tr = document.createElement('tr');
        tbody_tr.dataset.id = id.id;
        if (player.track && id.id == player.track && player.album && player.album == albumId) {
            tbody_tr.classList.add('now-playing');
            if (!player.buttons.togglePlay.classList.contains('playing')) {
                tbody_tr.classList.add('paused');
            }
        }
        tbody_tr.addEventListener('click', function () {
            if (this.classList.contains('now-playing')) {
                player.togglePlay();
            }
            else {
                let tracksToAdd = [];
                for (const track of albumTracks) {
                    tracksToAdd.push({
                        track: track.id,
                        album: albumId,
                    });
                }
                player.queue = tracksToAdd;
                if (player.random) {
                    shuffleArray(player.queue);
                }
                player.queue = player.queue.slice(j);
                player.play(id.id, openedAlbum);
            }
        });
        const tbody_hashtag = document.createElement('td');
        if (showPictures) {
            const tbody_hashtag_img = document.createElement('img');
            tbody_hashtag_img.loading = 'lazy';
            tbody_hashtag_img.src = tracks[id.id].pictures[0] ? tracks[id.id].pictures[0].url + '?v=' + tracks[id.id].pictures[0].version : 'svg/placeholder.svg';
            tbody_hashtag.appendChild(tbody_hashtag_img);
        }
        else {
            const tbody_hashtag_span = document.createElement('span');
            tbody_hashtag_span.innerText = id.number;
            tbody_hashtag.appendChild(tbody_hashtag_span);
        }
        tbody_tr.appendChild(tbody_hashtag);
        const tbody_title = document.createElement('td');

        const tbody_title_div = document.createElement('div');
        const tbody_title_h3 = document.createElement('h3');
        tbody_title_h3.innerText = tracks[id.id].meta.title;
        tbody_title_div.appendChild(tbody_title_h3);
        const tbody_title_p = document.createElement('p');
        artistLinks(tbody_title_p, id.id);
        if (tracks[id.id].source && tracks[id.id].source == 'spotify') {
            tbody_title_p.innerHTML = '<img src="svg/spotify.svg">' + tbody_title_p.innerHTML;
        }
        tbody_title_div.appendChild(tbody_title_p);
        tbody_title.appendChild(tbody_title_div);

        tbody_tr.appendChild(tbody_title);
        const tbody_year = document.createElement('td');
        tbody_year.innerText = tracks[id.id].meta.year ? tracks[id.id].meta.year : '-';
        tbody_tr.appendChild(tbody_year);
        const tbody_duration = document.createElement('td');
        if (tracks[id.id].format.duration) {
            let duration = Math.floor(tracks[id.id].format.duration);
            const hours = Math.floor(duration / 60 / 60);
            duration -= hours * 60 * 60;
            const minutes = Math.floor(duration / 60);
            duration -= minutes * 60;
            const seconds = duration;
            tbody_duration.innerText = (hours != 0 ? hours + ':' : '') + ((hours != 0 && minutes < 10) ? '0' + minutes : minutes) + ':' + (seconds < 10 ? '0' + seconds : seconds);
        }
        else {
            tbody_duration.innerText = '-';
        }
        tbody_tr.appendChild(tbody_duration);
        tbody.appendChild(tbody_tr);
    }
    table.appendChild(tbody);
    return table;
}

async function openAlbum(albumId, closeLyrics = false) {
    fetch('data?album=' + albumId);
    if (albums[albumId] == null) {
        populateFeed();
        open('home');
        return;
    }
    document.getElementById('home-tab').classList.remove('search-results');
    homeTab.classList.remove('open');
    openedAlbum = albumId;
    if (closeLyrics || !lyricsTab.classList.contains('open')) {
        open('album');
    }
    albumTab.innerText = '';
    while (albumList.getElementsByClassName('active')[0]) {
        albumList.getElementsByClassName('active')[0].classList.remove('active');
    }
    for (const album of albumList.children) {
        if (album.dataset.id == albumId) {
            album.classList.add('active');
            break;
        }
    }
    let albumTracks = [];
    for (const track of albums[albumId].tracks) {
        if (search == null || search == '' || (tracks[track].meta.title && tracks[track].meta.title.toLocaleLowerCase().indexOf(search.toLocaleLowerCase()) != -1) || (tracks[track].meta.artist && tracks[track].meta.artist.toLocaleLowerCase().indexOf(search.toLocaleLowerCase()) != -1)) {
            albumTracks.push({
                id: track,
            });
        }
    }
    /*albumTracks.sort(function (a, b) {
        a = tracks[a.id];
        b = tracks[b.id];
        if ((a.meta.track == null || a.meta.track['no'] == null) && (b.meta.track == null || b.meta.track['no'] == null)) {
            return 0;
        }
        else if (a.meta.track == null || a.meta.track['no'] == null) {
            return 1;
        } else if (b.meta.track == null || b.meta.track['no'] == null) {
            return -1;
        } else {
            return a.meta.track['no'] - b.meta.track['no'];
        }
    });*/

    for (let i = 0; i < albumTracks.length; i++) {
        albumTracks[i].number = i + 1;
    }

    const albumBackground = document.createElement('div');
    albumBackground.id = 'album-background';
    albumTab.appendChild(albumBackground);

    const parent = document.createElement('div');
    parent.id = 'album-info';

    if (isContentEditable) {
        const input = document.createElement('input');
        input.id = 'album-cover-input';
        input.type = 'file';
        input.accept = 'image/*';
        input.style.display = 'none';
        input.addEventListener('input', async function () {
            if (this.files.length == 0) {
                return;
            }
            let formData = new FormData();
            formData.append('album', albumId);
            formData.append('file', this.files[0]);
            const json = await request('edit', {
                method: 'POST',
                body: formData
            }, ['picture']);
            if (json) {
                albums[albumId].picture = json.picture;
                populateFeed();
                player.update();
                openAlbum(albumId);
                updateExtraInfoPanel();
            }
        });
        parent.appendChild(input);
        const label = document.createElement('label');
        const cover = document.createElement('img');
        cover.src = albums[albumId].picture ? (albums[albumId].picture.url + '?v=' + albums[albumId].picture.version) : 'svg/placeholder.svg';
        label.appendChild(cover);
        parent.appendChild(label);
        label.setAttribute('for', 'album-cover-input');
    }
    else {
        const cover = document.createElement('img');
        cover.src = albums[albumId].picture ? (albums[albumId].picture.url + '?v=' + albums[albumId].picture.version) : 'svg/placeholder.svg';
        parent.appendChild(cover);
    }

    const albumInfo = document.createElement('div');
    const albumTitle = document.createElement('input');
    albumTitle.dataset.vw = albums[albumId].name.length <= 20 ? '4' : (albums[albumId].name.length <= 30 ? '3' : '2');
    albumTitle.value = albums[albumId].name ? albums[albumId].name : '';
    albumTitle.placeholder = text('unknown_album');
    albumInfo.appendChild(albumTitle);

    async function saveAlbumTitle() {
        if (albumTitle.value == albums[albumId].name) {
            return;
        }
        const newAlbumName = albumTitle.value;
        let formData = new FormData();
        formData.append('album', albumId);
        formData.append('name', newAlbumName);
        const json = await request('edit', {
            method: 'POST',
            body: formData
        }, ['status']);
        if (json) {
            albums[albumId].name = newAlbumName;
            populateFeed();
            openAlbum(albumId);
            updateExtraInfoPanel();
        }
    }
    albumTitle.addEventListener('blur', saveAlbumTitle);
    albumTitle.addEventListener('keydown', function (e) {
        if (e.key == 'Enter') {
            saveAlbumTitle();
        }
    });

    const albumArtists = document.createElement('h3');
    if (albumId == 'all_tracks' || albumId == 'favorites' || albumId == 'disconnected') {
        albumArtists.innerText = text(albumId + '_description');
    }
    else {
        artistLinks(albumArtists, albums[albumId].artists, true);
    }
    albumInfo.appendChild(albumArtists);
    parent.appendChild(albumInfo);

    albumTab.appendChild(parent);

    const albumControls = document.createElement('div');
    albumControls.id = 'album-controls';
    const albumPlay = document.createElement('button');
    albumPlay.id = 'album-play';
    if (player.playing && player.album && player.album == albumId) {
        albumPlay.classList.add('playing');
    }
    albumPlay.addEventListener('click', function () {
        if (player.album && player.album == albumId) {
            player.togglePlay();
        }
        else {
            player.queue = [];
            for (const track of albums[albumId].tracks) {
                player.queue.push({
                    track: track,
                    album: albumId,
                });
            }
            if (player.random) {
                shuffleArray(player.queue);
            }
            parent.classList.add('active');
            const itemToPlay = player.queue[0].track;
            player.loopQueue = player.queue.slice();
            player.queue.shift();
            player.play(itemToPlay, albumId);
        }
    });
    albumControls.appendChild(albumPlay);
    const albumRandom = document.createElement('button');
    albumRandom.id = 'album-random';
    if (player.random) {
        albumRandom.classList.add('active');
    }
    albumRandom.addEventListener('click', function () {
        this.classList.toggle('active');
        player.toggleRandom();
    });
    albumControls.appendChild(albumRandom);
    const albumSearch = document.createElement('input');
    albumSearch.type = 'text';
    albumSearch.placeholder = text('search') + '...';
    if (search) {
        albumSearch.value = search;
    }
    albumControls.appendChild(albumSearch);
    albumTab.appendChild(albumControls);
    albumSearch.addEventListener('input', function () {
        search = this.value;
        albumTracks = [];
        for (const track of albums[albumId].tracks) {
            if (search == null || search == '' || (tracks[track].meta.title && tracks[track].meta.title.toLocaleLowerCase().indexOf(search.toLocaleLowerCase()) != -1) || (tracks[track].meta.artist && tracks[track].meta.artist.toLocaleLowerCase().indexOf(search.toLocaleLowerCase()) != -1)) {
                albumTracks.push({
                    id: track,
                });
            }
        }
        for (let i = 0; i < albumTracks.length; i++) {
            albumTracks[i].number = i + 1;
        }
        const table = getTrackTable(albumTracks, albumId);
        document.getElementById('album-track-list').replaceWith(table);
    });

    // Generating the track table might take a bit longer for long playlists.
    // So give the browser time to load the top part instantly (image, title, artist, play buttons, etc), and load the table later
    if (albumTracks.length >= 100) {
        const loading = document.createElement('span');
        loading.classList.add('loader')
        albumTab.appendChild(loading);
        await new Promise(resolve => setTimeout(resolve, 1));
        loading.remove();
    }

    const table = getTrackTable(albumTracks, albumId);
    albumTab.appendChild(table);

    const button = document.createElement('button');
    button.id = 'add-to-album';
    button.innerText = 'Meer toevoegen';
    albumTab.appendChild(button);
    const addToAlbum = document.createElement('div');
    addToAlbum.id = 'add-to-album-form';
    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = text('search');
    input.value = addSearch ? addSearch : '';
    addToAlbum.appendChild(input);
    const trackWrapper = document.createElement('div');
    trackWrapper.id = 'add-to-album-tracks';
    addToAlbum.appendChild(trackWrapper);
    input.addEventListener('input', updateResults);
    button.addEventListener('click', function () {
        addToAlbum.classList.toggle('open');
        if (addToAlbum.classList.contains('open')) {
            button.innerText = 'Sluiten';
            middlePanel.scrollTo({
                top: middlePanel.scrollHeight,
                behavior: 'smooth',
            })
        }
        else {
            button.innerText = 'Meer toevoegen';
        }
    });
    function updateResults() {
        addSearch = input.value;
        trackWrapper.innerHTML = '';
        let searchTracks = [];
        let i = 1;
        const searches = input.value.split(' ');
        for (const track in tracks) {
            let found = true;
            for (const search of searches) {
                found = searchObject(tracks[track], search);
                if (!found) {
                    break;
                }
            }
            if (found) {
                searchTracks.push(track);
                i++;
            }
            if (i > 12) {
                break;
            }
        }
        for (const track of searchTracks) {
            const parent = document.createElement('div');
            parent.classList.add('tile');
            parent.addEventListener('click', async function () {
                const remove = albums[openedAlbum].tracks.includes(track);
                if (remove) {
                    albums[openedAlbum].tracks = albums[openedAlbum].tracks.filter(item => item !== track);
                }
                else {
                    albums[openedAlbum].tracks.push(track);
                }

                let formData = new FormData();
                formData.append('album', openedAlbum);
                for (const albumTrack of albums[openedAlbum].tracks) {
                    formData.append('tracks[]', albumTrack);
                }
                const json = await request('edit', {
                    method: 'POST',
                    body: formData
                }, ['status']);
                if (json) {
                    if (remove) {
                        sendNotification('removed_from_playlist', 'removed_from_playlist_description');
                    }
                    else {
                        if (albums['disconnected']) {
                            albums['disconnected'].tracks = albums['disconnected'].tracks.filter(item => item !== track);
                        }
                        sendNotification('added_to_playlist', 'added_to_playlist_description');
                    }
                    openAlbum(openedAlbum);
                }
            });
            const img = document.createElement('img');
            img.loading = 'lazy';
            img.src = (tracks[track].pictures && tracks[track].pictures.length > 0) ? tracks[track].pictures[0].url + '?v=' + tracks[track].pictures[0].version : 'svg/placeholder.svg';
            parent.appendChild(img);

            if (albums[openedAlbum].tracks.includes(track)) {
                const check = document.createElement('div');
                check.classList.add('check');
                const checkImg = document.createElement('img');
                checkImg.src = 'svg/check.svg';
                check.appendChild(checkImg);
                parent.appendChild(check);
            }

            const info = document.createElement('div');
            const title = document.createElement('h3');
            title.innerText = tracks[track].meta.title ? tracks[track].meta.title : text('unknown_title');
            info.appendChild(title);
            const artist = document.createElement('p');
            const artistString = tracks[track].meta.artist ? tracks[track].meta.artist : text('unknown_artist');
            artist.innerText = artistString;
            info.appendChild(artist);
            parent.appendChild(info);
            trackWrapper.appendChild(parent);
        }
    }
    updateResults();
    albumTab.appendChild(addToAlbum);
}

populateFeed();

async function updateTrackData(track) {
    let formData = new FormData();
    formData.append('track', track);
    formData.append('json', JSON.stringify(tracks[track]));
    const json = await request('edit', {
        method: 'POST',
        body: formData
    }, ['status']);
}

if (document.getElementById('recent-albums')) {
    for (const element of document.getElementById('recent-albums').children) {
        element.addEventListener('click', function () {
            openAlbum(element.dataset.id, true)
        });
    }
}


for (const element of document.getElementsByClassName('play')) {
    element.addEventListener('click', function (e) {
        e.stopPropagation();
        const id = element.dataset.id;
        search = '';
        if (player.album == id) {
            player.togglePlay();
        }
        else {
            player.queue = [];
            for (const track of albums[id].tracks) {
                player.queue.push({
                    track: track,
                    album: id,
                });
            }
            if (player.random) {
                shuffleArray(player.queue);
            }
            player.album = id;
            const queueItem = player.queue[0].track;
            player.loopQueue = player.queue.slice();
            player.queue.shift();
            player.play(queueItem);
        }
    });
}

if (isContentEditable) {
    document.getElementById('new-track-image').addEventListener('input', async function () {
        if (this.files.length == 0 || player.track == null) {
            return;
        }
        let formData = new FormData();
        formData.append('track', player.track);
        formData.append('file', this.files[0]);
        const json = await request('edit', {
            method: 'POST',
            body: formData
        }, ['picture']);
        if (json) {
            tracks[player.track].pictures[0] = json.picture;
            populateFeed();
            player.update();
            if (player.album == openedAlbum) {
                openAlbum(player.album);
            }
            updateExtraInfoPanel();
        }
    });
    const trackTitle = infoPanel.getElementsByClassName('song')[0].getElementsByTagName('input')[0];
    trackTitle.placeholder = text('unknown_title');
    const trackArtist = infoPanel.getElementsByClassName('song')[0].getElementsByTagName('input')[1];
    trackArtist.placeholder = text('unknown_artist');
    function saveTrackTitle() {
        trackTitle.scrollLeft = 0;
        if (player.track == null || tracks[player.track].radio) {
            return;
        }
        if (tracks[player.track].meta.title != trackTitle.value) {
            tracks[player.track].meta.title = trackTitle.value;
            updateTrackData(player.track);
            player.update();
            if (player.album == openedAlbum) {
                openAlbum(player.album);
            }
        }
    }
    trackTitle.addEventListener('blur', saveTrackTitle);
    trackTitle.addEventListener('keydown', function (e) {
        if (e.key == 'Enter') {
            saveTrackTitle();
        }
    });
    function saveTrackDescription() {
        this.scrollLeft = 0;
        if (player.track == null || tracks[player.track].radio) {
            return;
        }
        if (tracks[player.track].meta.artist != this.value) {
            tracks[player.track].meta.artist = this.value;
            updateTrackData(player.track);
            player.update();
            if (player.album == openedAlbum) {
                openAlbum(player.album);
            }
        }
    }
    trackArtist.addEventListener('blur', saveTrackDescription);
    trackArtist.addEventListener('keydown', function (e) {
        if (e.key == 'Enter') {
            saveTrackDescription();
        }
    });
}

function updateQueue() {
    const queueList = document.getElementById('queue-listview');

    queueList.innerText = '';
    let i = 1;
    let queueWithCurrentTrack = [];
    if (player.track) {
        queueWithCurrentTrack.push({ track: player.track, album: false });
    }
    for (const element of player.queue) {
        queueWithCurrentTrack.push(element);
    }
    let albumSection = false;
    for (const queueItem of queueWithCurrentTrack) {
        const j = i;
        const id = queueItem.track;
        if (queueItem.album != albumSection) {
            albumSection = queueItem.album;
            if (queueItem.album) {
                const p = document.createElement('p');
                p.innerText = albums[queueItem.album].name;
                queueList.appendChild(p);
            }
        }
        const parent = document.createElement('div');
        parent.dataset.id = id;
        parent.classList.add('album');
        if (i == 1) {
            parent.classList.add('now-playing');
            if (!player.playing) {
                parent.classList.add('paused');
            }
        }
        else {
            parent.classList.add('queue-item');
        }

        const img = document.createElement('img');
        img.loading = 'lazy';
        img.src = tracks[id].pictures[0] ? (tracks[id].pictures[0].url + '?v=' + tracks[id].pictures[0].version) : 'svg/placeholder.svg';
        img.addEventListener('click', function (e) {
            if (parent.classList.contains('now-playing') && player.playing) {
                player.audio.pause();
                player.update();
            }
            else {
                if (!parent.classList.contains('now-playing')) {
                    player.queue.unshift({
                        track: player.track,
                        album: false,
                    });
                    for (let k = 0; k < j - 1; k++) {
                        player.previousQueue.unshift(player.queue[k].track);
                    }
                    player.queue = player.queue.slice(j);
                }
                player.play(id, openedAlbum);
            }
        });
        parent.appendChild(img);

        const info = document.createElement('div');
        const title = document.createElement('h3');
        if (queueItem.album) {
            title.dataset.id = queueItem.album;
        }
        title.innerText = tracks[id].meta.title ? tracks[id].meta.title : text('unknown_title');
        info.appendChild(title);
        const artist = document.createElement('p');
        artist.innerText = tracks[id].meta.artist ? tracks[id].meta.artist : text('unknown_artist');
        info.appendChild(artist);
        parent.appendChild(info);
        queueList.appendChild(parent);
        i++;
    }
    const list = queueList;
    let draggingEle;
    let placeholder;
    let isDraggingStarted = false;
    let x = 0;
    let y = 0;
    const swap = function (nodeA, nodeB) {
        const parentA = nodeA.parentNode;
        const siblingA = nodeA.nextSibling === nodeB ? nodeA : nodeA.nextSibling;
        nodeB.parentNode.insertBefore(nodeA, nodeB);
        parentA.insertBefore(nodeB, siblingA);
    };
    const isAbove = function (nodeA, nodeB) {
        const rectA = nodeA.getBoundingClientRect();
        const rectB = nodeB.getBoundingClientRect();
        return rectA.top + rectA.height / 2 < rectB.top + rectB.height / 2;
    };
    const mouseDownHandler = function (e) {
        if (e.target.tagName == 'IMG') {
            draggingEle = null;
            return;
        }
        if (e.target.classList.contains('queue-item')) {
            draggingEle = e.target;
        } else if (e.target.parentElement.classList.contains('queue-item')) {
            draggingEle = e.target.parentElement;
        } else if (e.target.parentElement.parentElement.classList.contains('queue-item')) {
            draggingEle = e.target.parentElement.parentElement;
        } else {
            draggingEle = null;
            return;
        }
        const rect = draggingEle.getBoundingClientRect();
        if (e.touches == null || e.touches[0] == null) {
            x = e.pageX - rect.left;
            y = e.pageY - rect.top;
        }
        else {
            x = e.touches[0].pageX - rect.left;
            y = e.touches[0].pageY - rect.top;
        }
        document.addEventListener('mousemove', mouseMoveHandler, { passive: true });
        document.addEventListener('touchmove', mouseMoveHandler, { passive: true });
        document.addEventListener('mouseup', mouseUpHandler, { passive: true });
        document.addEventListener('touchend', mouseUpHandler, { passive: true });
    };
    const mouseMoveHandler = function (e) {
        if (draggingEle == null || draggingEle.parentNode == null) {
            return;
        }
        draggingEle.style.width = draggingEle.clientWidth + 'px';
        if (!isDraggingStarted) {
            isDraggingStarted = true;
            placeholder = document.createElement('div');
            placeholder.classList.add('placeholder');
            draggingEle.parentNode.insertBefore(placeholder, draggingEle.nextSibling);
            placeholder.style.height = '46px';
        }
        draggingEle.style.position = 'absolute';
        draggingEle.style.background = 'var(--background-normal)';
        draggingEle.style.zIndex = '100';
        if (e.touches == null || e.touches[0] == null) {
            draggingEle.style.top = (e.pageY - y) + 'px';
            draggingEle.style.left = (e.pageX - x) + 'px';
        }
        else {
            draggingEle.style.top = (e.touches[0].pageY - y) + 'px';
            draggingEle.style.left = (e.touches[0].pageX - x) + 'px';
        }
        const prevEle = draggingEle.previousElementSibling;
        const nextEle = placeholder.nextElementSibling;
        if (prevEle && isAbove(draggingEle, prevEle)) {
            swap(placeholder, draggingEle);
            swap(placeholder, prevEle);
            return;
        }
        if (nextEle && isAbove(nextEle, draggingEle)) {
            swap(nextEle, placeholder);
            swap(nextEle, draggingEle);
        }
    };
    const mouseUpHandler = function () {
        const queueItems = document.getElementsByClassName('queue-item');
        player.queue = [];
        for (const item of queueItems) {
            const data = item.dataset;
            player.queue.push({
                track: data.id,
                album: data.album ? data.album : false,
            });
        }
        updateQueue();
    };
    [].slice.call(list.getElementsByClassName('queue-item')).forEach(function (item) {
        item.addEventListener('mousedown', mouseDownHandler, { passive: true });
        item.addEventListener('touchstart', mouseDownHandler, { passive: true });
    });
}

function textareaHeight(element, minHeight = 0) {
    element.style.height = '0px';
    const calculatedHeight = (element.scrollHeight < 50 ? 40 : element.scrollHeight) + 10;
    element.style.height = (calculatedHeight < minHeight ? minHeight : calculatedHeight) + 'px';
}
window.addEventListener('resize', function () {
    if (document.documentElement.clientWidth > 800 && document.getElementsByTagName('html')[0].scrollTop > 0) {
        document.getElementsByTagName('html')[0].scrollTo({ top: 0, behavior: 'instant' });
    }
    for (const element of document.getElementsByTagName('textarea')) {
        textareaHeight(element, 50);
    }
});

function updateExtraInfoPanel() {
    if (player.track == null) {
        return;
    }
    infoPanel.classList.add('has-content');
    queuePanel.classList.add('has-content');
    lyricButton.classList.add('has-content');
    document.getElementsByClassName('track-info')[0].classList.add('has-content');
    if (infoPanel.classList.contains('open')) {
        queueButton.classList.remove('active');
        info.classList.add('active');
    }
    infoPanel.getElementsByTagName('img')[0].src = tracks[player.track].pictures[0] ? (tracks[player.track].pictures[0].url + '?v=' + tracks[player.track].pictures[0].version) : 'svg/placeholder.svg';
    infoPanel.getElementsByTagName('h3')[0].innerText = tracks[player.track].meta.album ? tracks[player.track].meta.album : text('unknown_album');
    infoPanel.getElementsByClassName('song')[0].getElementsByTagName('input')[0].value = tracks[player.track].meta.title;
    infoPanel.getElementsByClassName('song')[0].getElementsByTagName('input')[1].value = tracks[player.track].meta.artist ? tracks[player.track].meta.artist : '';


    document.getElementById('metadata').innerText = '';
    const paragraph = document.createElement('textarea');
    paragraph.spellcheck = false;
    paragraph.placeholder = text('no_description');
    if (isContentEditable && !tracks[player.track].radio) {
        paragraph.addEventListener('focus', function () {
            if (this.style.opacity == '0.5') {
                this.value = '';
                this.style.opacity = '1';
            }
        });
        paragraph.addEventListener('blur', function () {
            const originalText = (tracks[player.track].meta.comment && tracks[player.track].meta.comment[0]) ? tracks[player.track].meta.comment[0].text : '';
            const hasDescription = this.value.replace(/\s/g, '') != '';
            if (hasDescription) {
                paragraph.style.opacity = '1';
            }
            else {
                paragraph.style.opacity = '0.5';
            }
            if (this.value != originalText) {
                if (tracks[player.track].meta.comment && tracks[player.track].meta.comment[0] && tracks[player.track].meta.comment[0].text) {
                    tracks[player.track].meta.comment[0].text = hasDescription ? this.value : null;
                }
                else if (hasDescription) {
                    tracks[player.track].meta.comment = [{ text: this.value }];
                }
                updateTrackData(player.track);
            }
        });

        paragraph.addEventListener('input', function () { textareaHeight(this, 50); });
    }
    if (tracks[player.track].meta.comment && tracks[player.track].meta.comment[0] && tracks[player.track].meta.comment[0].text) {
        paragraph.value = tracks[player.track].meta.comment[0].text;
    }
    else {
        paragraph.style.opacity = '0.5';
    }
    textareaHeight(paragraph, 50);
    document.getElementById('metadata').appendChild(paragraph);
    let keys = ['track', 'subtitle', 'genre', 'year', 'rating'];
    const taken_keys = ['size', 'bitrate', 'channels', 'type', 'duration'];
    function addField(key, value) {
        if (keys.includes(key) || taken_keys.includes(key)) {
            let html;
            if (key == 'track' && tracks[player.track].meta.track.no) {
                html = tracks[player.track].meta.track.no + (tracks[player.track].meta.track.of ? ' van ' + tracks[player.track].meta.track.of : '');
            }
            if (key == 'rating' && tracks[player.track].meta.rating[0].rating) {
                html = (tracks[player.track].meta.rating[0].rating * 100) + '%';
            }
            else if ((key == 'subtitle' || key == 'genre') && value[0]) {
                html = value.join(', ');
            }
            else if (key == 'year' || (key == 'type' && !tracks[player.track].radio)) {
                html = value;
            }
            else if (key == 'size' && !isNaN(value)) {
                html = (value / 1024 / 1024).toFixed(1) + ' MB';
            }
            else if (key == 'bitrate' && !isNaN(value)) {
                html = Math.round(value) + ' kbps';
            }
            else if (key == 'duration' && !isNaN(value)) {
                let duration = Math.floor(value);
                const hours = Math.floor(duration / 60 / 60);
                duration -= hours * 60 * 60;
                const minutes = Math.floor(duration / 60);
                duration -= minutes * 60;
                const seconds = duration;
                html = (hours != 0 ? hours + ':' : '') + ((hours != 0 && minutes < 10) ? '0' + minutes : minutes) + ':' + (seconds < 10 ? '0' + seconds : seconds);
            }
            else if (key == 'channels' && !isNaN(value)) {
                html = value + (value == 1 ? ' (mono)' : (value == 2 ? ' (stereo)' : ''));
            }
            if (html) {
                keys = keys.filter(item => item !== key);
                const meta_item = document.createElement('div');
                const meta_key = document.createElement('b');
                meta_key.innerText = key;
                meta_item.appendChild(meta_key);
                const meta_value = document.createElement('p');
                meta_value.innerText = html;
                meta_item.appendChild(meta_value);
                document.getElementById('metadata').appendChild(meta_item);
            }
        }
    }

    for (let [key, value] of Object.entries(tracks[player.track].meta)) {
        if (!taken_keys.includes(key)) {
            addField(key, value);
        }
    }
    if (tracks[player.track].name && tracks[player.track].name.indexOf('.') != -1) {
        addField('type', tracks[player.track].name.split('.').pop());
    }
    addField('size', tracks[player.track].size);
    addField('bitrate', tracks[player.track].format.bitrate / 1000);
    addField('channels', tracks[player.track].format.numberOfChannels);
    addField('duration', Math.floor(tracks[player.track].format.duration));
}

function setPageTitle() {
    if (player.track) {
        document.getElementsByTagName('title')[0].innerText = 'nottify // ' + (tracks[player.track].meta.title ? tracks[player.track].meta.title : text('unknown_title')) + ' // ' + (tracks[player.track].meta.artist ? tracks[player.track].meta.artist : text('unknown_artist'));
    }
    else if (openedArtist) {
        document.getElementsByTagName('title')[0].innerText = 'nottify // ' + openedArtist;
    }
    else if (openedAlbum) {
        document.getElementsByTagName('title')[0].innerText = 'nottify // ' + albums[openedAlbum].name;
    }
}
let previousUrl = '?page=' + new URLSearchParams(window.location.search).get('page');
function open(page) {
    let url = '?page=' + encodeURIComponent(page);
    console.log('opening ' + page);

    // Handle mobile bottom navigation bar button status
    const activeElement = page == 'home' ? navHome : (page == 'queue' ? navQueue : (page == 'library' ? navLibrary : null));
    navHome.classList.remove('active');
    navQueue.classList.remove('active');
    navLibrary.classList.remove('active');
    if (activeElement) {
        activeElement.classList.add('active');
    }

    // Handle mobile panel open status
    leftPanel.classList.remove('mobile-open');
    middlePanel.classList.remove('mobile-open');
    infoPanel.classList.remove('mobile-open');
    queuePanel.classList.remove('mobile-open');
    friendsPanel.classList.remove('mobile-open');
    const activePanel = page == 'track' ? infoPanel : (page == 'queue' ? queuePanel : (page == 'friends' ? friendsPanel : (page == 'library' ? leftPanel : middlePanel)));
    activePanel.classList.add('mobile-open');

    // Handle middle panel tab status
    const activeTab = page == 'home' ? homeTab : (page == 'album' ? albumTab : (page == 'lyrics' ? lyricsTab : (page == 'artist' ? artistTab : (page == 'profile' ? profileTab : null))));
    if (activeTab) {
        homeTab.classList.remove('open');
        albumTab.classList.remove('open');
        lyricsTab.classList.remove('open');
        artistTab.classList.remove('open');
        profileTab.classList.remove('open');
        activeTab.classList.add('open');
    }

    if (page == 'home') {
        while (albumList.getElementsByClassName('active')[0]) {
            albumList.getElementsByClassName('active')[0].classList.remove('active');
        }
    }

    if (page == 'track') {
        player.player.classList.add('mobile-expanded');
    }
    else {
        player.player.classList.remove('mobile-expanded');
    }

    if (document.documentElement.clientWidth <= 800) {
        if (page == 'queue') {
            queuePanel.classList.add('open');
            queueButton.classList.add('active');
        }
        else {
            queuePanel.classList.remove('open');
            queueButton.classList.remove('active');
        }

        if (page == 'track') {
            infoPanel.classList.add('open');
            info.classList.add('active');
        }
        else {
            infoPanel.classList.remove('open');
            info.classList.remove('active');
        }

        if (page == 'friends') {
            friendsPanel.classList.add('open');
            friendsButton.classList.add('active');
        }
        else {
            friendsPanel.classList.remove('open');
            friendsButton.classList.remove('active');
        }


    }
    else {
        if (page == 'queue' || page == 'friends') {
            if (info.classList.contains('active')) {
                infoPanel.classList.toggle('open');
                info.classList.toggle('active');
            }
        }
        if (page == 'queue' || page == 'track') {
            if (friendsButton.classList.contains('active')) {
                friendsPanel.classList.toggle('open');
                friendsButton.classList.toggle('active');
            }
        }
        if (page == 'track' || page == 'friends') {
            if (queueButton.classList.contains('active')) {
                queuePanel.classList.toggle('open');
                queueButton.classList.toggle('active');
            }
        }

        if (page == 'queue') {
            queuePanel.classList.toggle('open');
            queueButton.classList.toggle('active');
        }
        if (page == 'track') {
            infoPanel.classList.toggle('open');
            info.classList.toggle('active');
        }
        if (page == 'friends') {
            friendsPanel.classList.toggle('open');
            friendsButton.classList.toggle('active');
        }
    }

    if (page == 'lyrics' || lyricsTab.classList.contains('open')) {
        lyricButton.classList.add('active');
    }
    else {
        lyricButton.classList.remove('active');
    }

    if (!initialPageOpened) {
        return;
    }
    // Same page
    const isSamePage = previousUrl == url;
    previousUrl = url;
    if (openedAlbum && (page == 'album' || page == 'track' || page == 'lyrics')) {
        url += '&album=' + encodeURIComponent(openedAlbum);
    }
    if (player.track && (page == 'track' || page == 'lyrics')) {
        url += '&track=' + encodeURIComponent(player.track);
    }
    if (openedArtist) {
        if (page == 'artist') {
            url += '&artist=' + encodeURIComponent(openedArtist);
        }
        else if (page != 'lyrics') {
            openedArtist = null;
        }
    }
    if (window.history) {
        if (window.history.replaceState && isSamePage) {
            window.history.replaceState('', null, url);
        }
        else if (window.history.pushState) {
            window.history.pushState('', null, url);
        }
    }
    setPageTitle();
}
player.update();

if (document.getElementById('logo')) {
    document.getElementById('logo').addEventListener('click', function () {
        document.getElementById('search').value = '';
        document.getElementById('home-tab').classList.remove('search-results');
        open('home');
    });
}
if (document.getElementById('user-profile-picture')) {
    document.getElementById('user-profile-picture').addEventListener('click', function () {
        open('profile');
    });
}

function updateSpotifyEvents() {
    for (const element of document.getElementsByClassName('spotify-content')) {
        element.addEventListener('click', function () {
            openAlbum(element.dataset.id, true);
        });
    }
}
updateSpotifyEvents();

async function openArtist(artist) {
    artistTab.innerHTML = '<span class="loader"></span>';
    openedArtist = artist;
    open('artist');
    const response = await fetch('artist?q=' + encodeURIComponent(artist));
    artistTab.innerHTML = await response.text();
    updateSpotifyEvents();

    let searchTracks = [];
    let i = 1;
    for (const track in tracks) {
        if (searchObject(tracks[track], artist)) {
            searchTracks.push({
                id: track,
                number: i,
            });
            i++;
        }
    }
    openedAlbum = null;
    if (document.getElementById('in-library')) {
        const table = getTrackTable(searchTracks, null, true, true);
        document.getElementById('in-library').parentElement.insertBefore(table, document.getElementById('in-library').nextElementSibling);
        document.getElementById('in-library').innerText = text('in_library') + ' (' + searchTracks.length + ')';
    }
    if (document.getElementById('popular-tracks')) {
        document.getElementById('popular-tracks').innerText = text('popular_tracks');
    }
    if (document.getElementById('more-from-artist')) {
        document.getElementById('more-from-artist').innerText = text('more_from_artist');
    }

    if (document.getElementById('description-and-info-wrapper') && document.getElementById('description-and-info-wrapper').clientHeight > 250) {
        document.getElementById('description-and-info-wrapper').classList.add('collapsed');
        document.getElementById('description-and-info-wrapper').appendChild(document.createElement('div'));
        document.getElementById('description-and-info-wrapper').addEventListener('click', function () {
            this.classList.remove('collapsed');
        }, { once: true });
    }
}

function sendNotification(title, description, forever = false) {
    const notification = document.createElement('div');
    notification.classList.add('notification');
    const div = document.createElement('div');
    if (title) {
        const h3 = document.createElement('h3');
        h3.innerText = text(title);
        div.appendChild(h3);
    }
    if (description) {
        const p = document.createElement('p');
        p.innerText = text(description);
        div.appendChild(p);
    }
    notification.appendChild(div);
    const close = document.createElement('button');
    close.innerHTML = '&times;';
    close.addEventListener('click', function () {
        setTimeout(function () {
            if (notification) {
                notification.removeAttribute('style');
            }
        }, 1);
        setTimeout(function () {
            if (notification) {
                notification.remove();
            }
        }, 450);
    }, { once: true });
    notification.appendChild(close);
    document.getElementById('notification-container').appendChild(notification);
    setTimeout(function () {
        if (notification) {
            notification.style.transform = 'none';
            notification.style.opacity = '1';
        }
    }, 1);
    if (!forever) {
        setTimeout(function () {
            if (notification) {
                notification.getElementsByTagName('button')[0].click();
            }
        }, 7500);
    }
    return notification;
}

async function displayLyrics(lrc) {
    open('lyrics');
    lyricContainer.innerText = '';
    if (lrc && player.track) {
        let formData = new FormData();
        formData.append('lyrics', lrc);
        formData.append('track', player.track);
        const json = await request('lyrics', {
            method: 'POST',
            body: formData
        }, ['url']);
        if (json) {
            tracks[player.track].lyrics = json;
        }
    }
    else if (player.track && tracks[player.track].lyrics) {
        lyricsTab.classList.add('lyrics-open');
        const response = await fetch(tracks[player.track].lyrics.url + '?v=' + tracks[player.track].lyrics.version);
        lrc = await response.text();
    }
    else {
        lyricsTab.classList.remove('lyrics-open');
        return;
    }
    let lines = lrc.split(/\r?\n/);
    let seconds = 0;
    const isLRC = lrc.match(/^\[(\d+?):(\d+?)\.(\d+?)\]/m);
    lyricsTab.dataset.lrc = isLRC ? 'true' : 'false';
    lyricsTab.classList.add('lyrics-open');
    for (let line of lines) {
        if (line.replace(/\[[^\[\]]*?\]/g, '') != '') {
            const paragraph = document.createElement('p');
            let time = line.match(/^\[(\d+?):(\d+?)\.(\d+?)\]/);
            line = line.replace(/^\[[^\[\]]*?\]/g, '');
            let sections = line.match(/\<(\d+?):(\d+?)\.(\d+?)\>([^\<]+)/g);
            line = line.replace(/\<(\d+?):(\d+?)\.(\d+?)\>/g, '');
            if (isLRC) {
                if (time) {
                    seconds = parseInt(time[1]) * 60 + parseInt(time[2]) + parseFloat(time[3]) / 100;
                }
                paragraph.dataset.time = seconds;
            }
            else {
                paragraph.classList.add('active');
            }
            const secondsConst = seconds;
            if (sections) {
                for (const section of sections) {
                    const span = document.createElement('span');
                    const match = section.match(/\<(\d+?):(\d+?)\.(\d+?)\>([^\<]+)/);
                    const matchSeconds = parseInt(match[1]) * 60 + parseInt(match[2]) + parseFloat(match[3]) / 100;
                    span.innerText = match[4];
                    span.dataset.time = matchSeconds;
                    if (span.dataset.time != null) {
                        span.addEventListener('click', function () {
                            player.setTime(matchSeconds);
                        });
                    }
                    paragraph.appendChild(span);
                }
            }
            else {
                paragraph.innerText = line;
                if (paragraph.dataset.time != null) {
                    paragraph.addEventListener('click', function () {
                        player.setTime(secondsConst);
                    });
                }
            }
            lyricContainer.appendChild(paragraph);
        }
    }
    updateLyrics(true);
    if (lyricsInterval) {
        clearInterval(updateLyrics);
    }
    lyricsInterval = setInterval(updateLyrics, 50);
}

function updateLyrics(scroll = false) {
    const currentTime = player.audio.paused ? parseFloat(player.progressSlider.value) : player.audio.currentTime;
    //lrclib.net lyricsify.com
    if (lyricsTab.classList.contains('lyrics-open') && lyricsTab.dataset.lrc == 'true') {
        let element;
        let subElement;
        if (lyricContainer.getElementsByClassName('active')[0] && lyricContainer.children[0] && lyricContainer.children[0].dataset.time > currentTime) {
            while (lyricContainer.getElementsByClassName('active')[0]) {
                const scrollParent = document.documentElement.clientWidth <= 800 ? document.getElementsByTagName('html')[0] : middlePanel;
                scrollParent.scrollTo({
                    top: 0,
                    behavior: 'smooth',
                });
                lyricContainer.getElementsByClassName('active')[0].classList.remove('active');
            }
        }
        for (const paragraph of lyricContainer.children) {
            if (parseFloat(paragraph.dataset.time) <= currentTime) {
                element = paragraph;
                for (const span of paragraph.getElementsByTagName('span')) {
                    if (parseFloat(span.dataset.time) <= currentTime) {
                        subElement = span;
                    }
                }
            }
        }
        if (element) {
            const containsClass = element.classList.contains('active');
            while (lyricContainer.getElementsByClassName('active')[0]) {
                lyricContainer.getElementsByClassName('active')[0].classList.remove('active');
            }
            element.classList.add('active');
            if (subElement) {
                subElement.classList.add('active');
            }
            if (!containsClass) {
                const scrollParent = document.documentElement.clientWidth <= 800 ? document.getElementsByTagName('html')[0] : middlePanel;
                const scrollTop = element.offsetTop + element.clientHeight / 2 - scrollParent.clientHeight / 2;
                if (scroll || Math.abs(scrollParent.scrollTop - scrollTop) < 150) {
                    scrollParent.scrollTo({
                        top: scrollTop,
                        behavior: 'smooth',
                    });
                }
            }
        }
    }
}

document.getElementById('remove-lyrics').addEventListener('click', async function () {
    if (player.track) {
        let formData = new FormData();
        formData.append('track', player.track);
        const json = await request('lyrics', {
            method: 'POST',
            body: formData
        }, ['status']);
        if (json) {
            tracks[player.track].lyrics = null;
            displayLyrics();
        }
    }
});

document.getElementById('paste-lyrics').addEventListener('click', async function () {
    let text;
    try {
        text = await navigator.clipboard.readText();
    }
    catch (e) {
        sendNotification('paste_error', 'paste_error_description');
    }
    displayLyrics(text);
});

document.getElementById('lrc-upload').addEventListener('change', (event) => {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            displayLyrics(e.target.result);
        };
        reader.onerror = () => {
            sendNotification('error', 'read_error');
        };
        reader.readAsText(file);
    }
});

document.getElementById('lrclib-lookup').addEventListener('click', async function () {
    if (player.track == null || tracks[player.track] == null || tracks[player.track].meta.title == null || tracks[player.track].meta.artist == null) {
        sendNotification('lookup_failed', 'enter_track_and_artist');
        return;
    }
    // LRCLIB has 2 endpoints that could be used to get the lyrics in this scenario, the get-endpoint and the search-endpoint
    // The get-endpoint does return exactly what we want, but is quite strict, the song title and artist have to match exactly (and if given, the album name too)
    // This is why I've decided to use the search-endpoint, as it has a higher chance of finding the desired lyrics (although it does also have a higher chance of finding wrong lyrics)
    const artist = tracks[player.track].meta.artist.replace(/, [\s\S]+/, '').trim();
    const trackName = tracks[player.track].meta.title.replace(/\s*?\([\s\S]*?\)/g, '').replace(/\s*?- [\s\S]*/g, '').trim();
    if (artist == '' || trackName == '') {
        sendNotification('lookup_failed', 'enter_track_and_artist');
        return;
    }
    const notification = sendNotification('loading', 'requesting_lyrics');
    const json = await request('https://lrclib.net/api/search?artist_name=' + encodeURIComponent(artist) + '&track_name=' + encodeURIComponent(trackName));
    if (notification) {
        notification.getElementsByTagName('button')[0].click();
    }
    console.log(json);
    let lyrics;
    for (const result of json) {
        if (result.trackName && result.syncedLyrics && result.trackName.toLocaleLowerCase().trim() == tracks[player.track].meta.title.toLocaleLowerCase().trim()) {
            lyrics = result;
        }
    }
    if (lyrics == null && json.length > 0) {
        lyrics = json[0];
    }
    if (lyrics && lyrics.albumName && lyrics.albumName != '' && lyrics.albumName != '-' && tracks[player.track].meta.album == null) {
        tracks[player.track].meta.album = lyrics.albumName;
        updateTrackData(player.track);
    }
    if (lyrics && lyrics.syncedLyrics) {
        sendNotification('found', 'lookup_success');
        displayLyrics(lyrics.syncedLyrics);
    }
    else if (lyrics && lyrics.plainLyrics) {
        sendNotification('unsynced_found', 'lookup_success');
        displayLyrics(lyrics.plainLyrics);
    }
    else {
        sendNotification('not_found', 'lookup_not_found');
    }
});

// Drag panels
let moving;
for (const element of document.getElementsByClassName('resize')) {
    element.addEventListener('mousedown', function (e) {
        e.preventDefault();
        moving = element;
    });
    element.addEventListener('touchstart', function () {
        moving = element;
    }, { passive: true });
}
document.addEventListener('mousemove', function (e) {
    if (moving) {
        e.preventDefault();
    }
});
document.addEventListener('mouseup', function (e) {
    moving = null;
}, { passive: true });
document.addEventListener('touchend', function (e) {
    moving = null;
}, { passive: true });
if (localStorage.getItem('left-panel-width')) {
    document.documentElement.style.setProperty('--left-panel-width', localStorage.getItem('left-panel-width') + 'px');
}
if (localStorage.getItem('track-info-and-queue-width')) {
    document.documentElement.style.setProperty('--track-info-and-queue-width', localStorage.getItem('track-info-and-queue-width') + 'px');
}
function moveEventHandler(e) {
    if (moving) {
        let clientXInput, elementWidth;
        if (e.touches == null || e.touches[0] == null) {
            clientXInput = e.clientX;
        }
        else {
            clientXInput = e.touches[0].clientX;
        }
        if (moving.dataset.resize == 'left-panel-width') {
            elementWidth = clientXInput - 10;
        }
        else if (moving.dataset.resize == 'track-info-and-queue-width') {
            elementWidth = document.documentElement.clientWidth - clientXInput - 10;
        }
        if (elementWidth < 10) {
            elementWidth = 0;
            if (moving.dataset.resize == 'left-panel-width') {
                moving.style.marginLeft = '-8px';
            }
            else if (moving.dataset.resize == 'track-info-and-queue-width') {
                moving.style.marginRight = '-8px';
            }
        }
        else {
            moving.removeAttribute('style');
        }
        document.documentElement.style.setProperty('--' + moving.dataset.resize, elementWidth + 'px');
        localStorage.setItem(moving.dataset.resize, elementWidth);

        for (const element of document.getElementsByTagName('textarea')) {
            textareaHeight(element, 50);
        }
    }
}
document.addEventListener('mousemove', moveEventHandler, { passive: true });
document.addEventListener('touchmove', moveEventHandler, { passive: true });

document.getElementById('back').addEventListener('click', function () {
    const currentPage = new URLSearchParams(window.location.search).get('page');
    if (currentPage == 'lyrics') {
        open('track');
    }
    else if (currentPage == 'track') {
        open('album');
    }
    else if (currentPage == 'album') {
        open('library');
    }
    else if (currentPage == 'friends') {
        open('home');
    }
    openContentBasedOnQueryString();
});

function openContentBasedOnQueryString() {
    let params = new URLSearchParams(window.location.search);

    initialPageOpened = false;

    open('home');
    if (tracks[params.get('track')] && albums[params.get('album')]) {
        openedAlbum = params.get('album');
        if (player.track == null) {
            player.play(params.get('track'), openedAlbum);
        }
    }

    if (params.get('page') == 'album' && albums[params.get('album')]) {
        openAlbum(params.get('album'), true);
    }
    else if (params.get('page') == 'lyrics') {
        displayLyrics();
    }
    else if (params.get('page') == 'artist' && params.get('artist')) {
        openArtist(params.get('artist'));
    }
    else if (params.get('page') == 'friends') {
        openFriends();
    }
    else if (params.get('page')) {
        open(params.get('page'));
    }

    initialPageOpened = true;
    setPageTitle();
}
openContentBasedOnQueryString();