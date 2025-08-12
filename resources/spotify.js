// Spotify integration
// Be aware that the variable "player" refers to the nottify player, while "spotifyPlayer" refers to the Spotify player

const script = document.createElement('script');
script.src = 'https://sdk.scdn.co/spotify-player.js';
script.async = true;
document.body.appendChild(script);

let interval;
var currentPosition, currentDuration;
let spotifyPaused = true;
let deviceId;
let lastDate;
let spotifyPlayer;
let previousTrack, nextTrack;
let token = body.dataset.spotifyToken;
let ready = false;

// Spotify volume is louder than nottify volume, compensate for this by dividing by 5
let spotifyVolumeCorrection = player.normalize ? 5 : 1;

function pauseSpotify() {
    if (spotifyPlayer == null || spotifyPaused) {
        return;
    }
    spotifyPlayer.pause();
}

// Play Spotify track, also add items to queue
async function playSpotify(uri, offset) {
    if (!ready) {
        const notification = sendNotification('loading', 'spotify_not_ready', true);
        while (!ready) {
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        if (notification) {
            notification.getElementsByTagName('button')[0].click();
        }
    }
    let content;
    if (!uri.startsWith('spotify:')) {
        return;
    }
    let uris = [];
    if (uri.startsWith('spotify:track')) {
        if (uri == null && player.track && tracks[player.track].name.startsWith('spotify:track')) {
            uri = tracks[player.track].name;
        }
        if (uri != null) {
            uris.push(uri);
        }
        let i = 1;
        for (const item of player.queue) {
            if (i >= 50) {
                break;
            }
            // Only add Spotify tracks
            if (tracks[item.track].name.startsWith('spotify:track')) {
                uris.push(tracks[item.track].name);
            }
            // When encountering nottify track, break
            // playSpotify will be called again to add later Spotify tracks after the nottify tracks have completed playing
            else {
                break;
            }
            i++;
        }
        content = { uris: uris };
        if (offset) {
            content.position_ms = offset;
        }
    }
    else {
        uris.push(uri);
        content = { context_uri: uri };
    }
    let table = [];
    for (const uri of uris) {
        const id = uri.replace('spotify:track:', '');
        table.push({
            uri: uri,
            name: getNameById(id)
        });
    }
    logMessage('🟢 uploading queue to Spotify');
    console.table(table);
    fetch('https://api.spotify.com/v1/me/player/play?device_id=' + deviceId, {
        method: 'PUT',
        body: JSON.stringify(content),
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token,
        },
    });
}

window.onSpotifyWebPlaybackSDKReady = () => {
    spotifyPlayer = new Spotify.Player({
        name: 'nottify',
        getOAuthToken: async (cb) => {
            // If token expires within 10 seconds, request a new one
            if (parseInt(body.dataset.spotifyTokenExpires) - (Date.now() / 1000) < 10) {
                const json = await request('api/refresh-token.php', { method: 'GET' }, ['token']);
                if (json.token) {
                    token = json.token;
                }
                else {
                    token = null;
                }
            }
            cb(token);
        },
        volume: 0.5
    });

    function addTrack(current_track) {
        tracks[current_track.id] = {
            "name": current_track.uri,
            "added": null,
            "pictures": [],
            "meta": {
                "track": {
                    "no": null, "of": null
                },
                "title": current_track.name,
                "album": current_track.album.name
            },
            "format": {},
            "source": "spotify",
        }
        let artists = [];
        for (const artist of current_track.artists) {
            artists.push(artist.name);
        }
        tracks[current_track.id].meta.artist = artists.join(', ');
        let biggest = 0;
        let biggestImage;
        for (const image of current_track.album.images) {
            const pixels = image.height * image.width;
            if (pixels > biggest) {
                biggest = pixels;
                biggestImage = image.url;
            }
        }
        if (biggestImage) {
            tracks[current_track.id].pictures[0] = {
                'url': biggestImage,
                'version': 1,
            };
        }
    }

    function updateSpotifyRepeat() {
        if (token == null) {
            return;
        }
        fetch('https://api.spotify.com/v1/me/player/repeat?state=' + player.repeat + '&device_id=' + deviceId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token,
            },
        });
    }

    // Ready
    spotifyPlayer.addListener('ready', async function ({ device_id }) {
        ready = true;
        deviceId = device_id;
        player.volumeSlider.value = isNaN(parseFloat(localStorage.getItem('volume'))) ? 100 : parseFloat(localStorage.getItem('volume'));
        spotifyPlayer.setVolume((isNaN(parseFloat(localStorage.getItem('volume'))) ? 1 : parseFloat(localStorage.getItem('volume')) / 100) / spotifyVolumeCorrection);
        updateSpotifyRepeat();
    });

    // Not Ready
    spotifyPlayer.addListener('not_ready', ({ device_id }) => {
        logMessage('Device ID has gone offline', device_id);
    });
    spotifyPlayer.addListener('initialization_error', ({ message }) => {
        console.error(message);
    });

    spotifyPlayer.addListener('authentication_error', ({ message }) => {
        console.error(message);
    });

    spotifyPlayer.addListener('account_error', ({ message }) => {
        console.error(message);
    });
    spotifyPlayer.connect();

    for (const button of player.player.querySelectorAll('[data-event="toggleRepeat"]')) {
        button.addEventListener('click', updateSpotifyRepeat);
    }
    for (const button of player.player.querySelectorAll('[data-event="toggleFavorite"]')) {
        button.addEventListener('click', function () {
            if (player.track == null) {
                return;
            }
            const remove = !button.classList.contains('active');
            if (remove) {
                fetch('https://api.spotify.com/v1/me/tracks', {
                    method: 'DELETE',
                    body: JSON.stringify({ 'ids': [player.track] }),
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token,
                    },
                });
            }
            else {
                fetch('https://api.spotify.com/v1/me/tracks', {
                    method: 'PUT',
                    body: JSON.stringify({
                        'timestamped_ids': [
                            {
                                'id': player.track,
                                'added_at': new Date().toISOString(),
                            }
                        ]
                    }),
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token,
                    },
                });
            }
        });
    }
    player.volumeSlider.addEventListener('input', function (e) {
        spotifyPlayer.setVolume(e.target.value / 100 / spotifyVolumeCorrection);
    });

    player.progressSlider.addEventListener('input', function (e) {
        if (player.track && tracks[player.track].name.startsWith('spotify:')) {
            spotifyPlayer.seek(e.target.value * 1000);
        }
    })

    spotifyPlayer.addListener('player_state_changed', function (webPlaybackState) {
        if (webPlaybackState == null) {
            return;
        }

        // Sync nottify repeat with Spotify repeat
        while (
            (webPlaybackState.repeat_mode == 0 && player.repeat != repeat.OFF) ||
            (webPlaybackState.repeat_mode == 1 && player.repeat != repeat.CONTEXT) ||
            (webPlaybackState.repeat_mode == 2 && player.repeat != repeat.TRACK)
        ) {
            player.toggleRepeat();
        }

        lastDate = new Date();
        const position = webPlaybackState.position;
        const duration = webPlaybackState.duration;
        const current_track = webPlaybackState.track_window.current_track;
        spotifyPaused = webPlaybackState.paused;
        if (tracks[current_track.id] == null) {
            addTrack(current_track);
        }

        // Detect when Spotify track stops playing, and make sure the correct next track starts
        if (!spotifyPaused && current_track.id != player.track) {
            logMessage('🏁 track ' + getNameById(player.track) + ' has finished playing');

            // Queue items can either be Nottify or Spotify, but the Spotify queue only contains Spotify tracks
            // Whenever Spotify starts playing a new track, check if this track should be played according to the full Nottify+Spotify queue

            // If the next item is a Spotify track, and it is also the next item in the Spotify queue, Spotify can just keep playing
            // Otherwise, we have to call player.play() again in player.queueNext() or player.queuePrevious() to make sure we play the correct track

            // TODO: This is a naive approach which assumes:
            // - The current track is not the same as the next track (item will be skipped)
            // - The previous track is not the same as the next track (is handled correctly, but not optimal as queue is unnecessarily updated once)
            // This is acceptable for now but could be improved

            let isNextItemSpotify = true;
            if (current_track.id == nextTrack) {
                isNextItemSpotify = player.queue[0] && current_track.id == player.queue[0].track;
                player.queueNext(isNextItemSpotify);
            }
            else if (current_track.id == previousTrack) {
                isNextItemSpotify = player.previousQueue[0] && current_track.id == player.previousQueue[0];
                player.queuePrevious(isNextItemSpotify);
            }
            if (isNextItemSpotify) {
                logMessage('▶️ ' + ((tracks[current_track.id] && tracks[current_track.id].meta && tracks[current_track.id].meta.title) ? tracks[current_track.id].meta.title : current_track.id), 'color: #5a5cfd;');
                player.track = current_track.id;
                fetch('api/data.php?track=' + current_track.id + ((current_track.album && current_track.album['name']) ? ('&album=' + current_track.album['name']) : '') + '&duration=' + (duration / 1000) + '&hour=' + (new Date().getHours()) + '&month=' + (new Date().getMonth()));
            }
            if (lyricsTab.classList.contains('open')) {
                setTimeout(openLyrics, 100);
            }
            previousTrack = webPlaybackState.track_window.previous_tracks[0]?.id;
            nextTrack = webPlaybackState.track_window.next_tracks[0]?.id;
        }
        else if (webPlaybackState.repeat_mode == 0 && webPlaybackState.track_window.next_tracks.length == 0 && webPlaybackState.paused && webPlaybackState.position == 0 && player.track && current_track && current_track.id && player.queue.length != 0) {
            logMessage('🏁 track ' + getNameById(player.track) + ' has finished playing');
            player.queueNext();
        }
        updateMediaSession(player.track);
        updateExtraInfoPanel();
        player.update();
        if (spotifyPaused) {
            for (const element of player.player.querySelectorAll('[data-event="togglePlay"]')) {
                element.classList.remove('playing');
            }
        }
        else {
            if (duration != 0) {
                currentPosition = position / 1000;
                currentDuration = duration / 1000;
                player.seconds = duration / 1000;
                player.displayDuration(duration / 1000);
                player.setSliderMax(duration / 1000);
            }
            for (const element of player.player.querySelectorAll('[data-event="togglePlay"]')) {
                element.classList.add('playing');
            }
        }
        setPageTitle();
    });
    if (interval) {
        clearInterval(updateTime);
    }
    function updateTime() {
        if (spotifyPaused || currentPosition == null || currentDuration == null) {
            return;
        }
        player.currentTime.textContent = player.calculateTime(currentPosition);
        player.progressSlider.value = Math.floor(currentPosition);
        player.player.style.setProperty('--seek-before-width', (currentPosition / currentDuration * 100) + '%');
        player.player.style.setProperty('--progress', (currentPosition / currentDuration * 100) + '%');
        const now = new Date();
        currentPosition += (now - lastDate) / 1000;
        lastDate = now;
    }
    interval = setInterval(updateTime, 100);

    spotifyPlayer.on('initialization_error', ({ message }) => {
        sendNotification('unsupported_browser', message);
    });
    spotifyPlayer.on('authentication_error', ({ message }) => {
        sendNotification('invalid_token', message);
    });
    spotifyPlayer.on('account_error', ({ message }) => {
        sendNotification('spotify_premium_required', message);
    });
    spotifyPlayer.on('playback_error', ({ message }) => {
        sendNotification('playback_failed', message);
    });
};