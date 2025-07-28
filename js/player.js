const repeat = {
    OFF: 'off',
    CONTEXT: 'context',
    TRACK: 'track',
};

class Player {
    // Switch between no repeat, context repeat and track repeat
    toggleRepeat(times = 1) {
        if (isNaN(times)) {
            times = 1;
        }
        for (let i = 0; i < times; i++) {
            this.audio.loop = false;
            if (this.repeat == repeat.TRACK) {
                // No loop
                this.repeat = repeat.OFF;
                this.buttons.toggleRepeat.classList.remove('one');
                this.buttons.toggleRepeat.classList.remove('active');
            }
            else if (this.repeat == repeat.CONTEXT) {
                // Loop track
                this.repeat = repeat.TRACK;
                this.buttons.toggleRepeat.classList.add('one');
                this.buttons.toggleRepeat.classList.add('active');
                this.audio.loop = true;
            }
            else if (this.repeat == repeat.OFF) {
                // Loop context
                this.repeat = repeat.CONTEXT;
                this.buttons.toggleRepeat.classList.remove('one');
                this.buttons.toggleRepeat.classList.add('active');
                this.loopQueue = this.queue.slice();
                if (this.track) {
                    this.loopQueue.unshift({
                        track: this.track,
                        album: false,
                    });
                }
            }
            localStorage.setItem('repeat', this.repeat);
        }
    }

    // Play next track in queue
    queueNext(spotifySkipPlay = false) {
        console.log('queueNext');
        if (this.queue.length == 0 && this.repeat == repeat.CONTEXT) {
            this.queue = this.loopQueue.slice();
        }
        if (this.queue.length == 0) {
            this.audio.pause();
            if (!isNaN(this.audio.duration) && isFinite(this.audio.duration)) {
                this.setTime(this.audio.duration);
            }
        }
        else {
            const item = this.queue[0];
            this.previousQueue.unshift(this.track);
            this.queue.shift();
            // Do not use this.play() if already in Spotify queue, as this will reupload the whole queue to Spotify and cause audio stutter
            if (!tracks[item.track].name.startsWith('spotify:') || !spotifySkipPlay) {
                this.play(item.track, item.album);
            }
            else {
                this.audio.pause();
            }
        }
        this.update();
    }

    // Play previous track
    queuePrevious(spotifySkipPlay) {
        if (this.audio.currentTime > 5) {
            this.setTime(0);
            if (this.audio.paused) {
                this.audio.play();
            }
        }
        else if (this.previousQueue.length > 0) {
            const item = this.previousQueue[0];
            this.queue.unshift({
                track: this.track,
                album: false,
            });
            this.previousQueue.shift();
            // Do not use this.play() if already in Spotify queue, as this will reupload the whole queue to Spotify and cause audio stutter
            if (!tracks[item].name.startsWith('spotify:') || !spotifySkipPlay) {
                this.play(item);
            }
            else {
                this.audio.pause();
            }
        }
        this.update();
    }

    // Setup the audio context for sound normalizing
    initializeAudioContext() {
        this.audioContext = new AudioContext();
        this.mediaElementSource = this.audioContext.createMediaElementSource(this.audio);
        this.gainNode = this.audioContext.createGain();
        if (tracks[this.track].gain) {
            this.gainNode.gain.value = tracks[this.track].gain;
        }
        else {
            this.gainNode.gain.value = 1.0;
        }
        const self = this;
        function playGain() {
            self.mediaElementSource.connect(self.gainNode);
            self.gainNode.connect(self.audioContext.destination);
        }
        function pauseGain() {
            self.mediaElementSource.disconnect(self.gainNode);
            self.gainNode.disconnect(self.audioContext.destination);
        }
        this.audio.addEventListener('play', playGain, true);
        this.audio.addEventListener('pause', pauseGain, true);
    }

    // Set audio current time
    setTime(seconds) {
        this.audio.currentTime = seconds;
        this.progressSlider.value = seconds;
        this.player.style.setProperty('--seek-before-width', (seconds / this.audio.duration * 100) + '%');
        this.currentTime.textContent = this.calculateTime(seconds);
    }

    // Stop playing
    stop() {
        this.track = null;
        this.album = null;
        this.radio = null;
        this.audio.pause();
        this.setTime(0);
        infoPanel.classList.remove('has-content');
        queuePanel.classList.remove('has-content');
        lyricButton.classList.remove('has-content');
        this.player.getElementsByClassName('track-info')[0].classList.remove('has-content');
        for (const element of document.getElementsByClassName('radio tile active')) {
            element.classList.remove('active');
            element.classList.remove('now-playing');
        }
        this.update();
    }

    // Play a track
    async play(track, album) {
        console.log('playing ' + track);
        if (tracks[track] == null) {
            return;
        }
        if (album) {
            this.album = album;
        }

        // Radio
        this.radio = tracks[track].radio;
        if (this.radio) {
            this.player.classList.add('playing-radio');
        }
        else {
            this.player.classList.remove('playing-radio');
            for (const element of document.getElementsByClassName('radio tile active')) {
                element.classList.remove('active');
                element.classList.remove('now-playing');
            }
        }
        if (tracks[track].name.startsWith('spotify:')) {
            this.player.classList.add('playing-spotify');
        }
        else {
            this.player.classList.remove('playing-spotify');
        }
        const isCurrentTrack = this.track != null && (tracks[track].name == null || tracks[this.track].name == tracks[track].name);
        this.track = track;
        if (!isCurrentTrack) {
            if (albums['favorites'] == null) {
                this.buttons.toggleFavorite.style.display = 'none';
            } else {
                if (albums['favorites'].tracks.includes(track)) {
                    this.buttons.toggleFavorite.classList.add('active');
                }
                else {
                    this.buttons.toggleFavorite.classList.remove('active');
                }
            }
            setPageTitle();
        }

        updateExtraInfoPanel();
        this.update();
        if (lyricsTab.classList.contains('open')) {
            displayLyrics();
        }

        if (!isCurrentTrack) {
            if (tracks[track].name.startsWith('spotify:')) {
                this.audio.pause();
                if (typeof playSpotify === 'function') {
                    await playSpotify(tracks[track].name);
                }
                else {
                    sendNotification('error', 'spotify_disabled');
                    return;
                }
            }
            else {
                if (this.audio.src != tracks[track].name && document.getElementsByTagName('body')[0].dataset.lastfmScrobble == 'true') {
                    let artist;
                    if (tracks[track].meta.artist) {
                        if (tracks[track].meta.artist.indexOf(', ') != -1) {
                            artist = tracks[track].meta.artist.split(', ')[0];
                        }
                        else {
                            artist = tracks[track].meta.artist;
                        }
                    }
                    if (artist && tracks[track].meta.title) {
                        fetch('scrobble?artist=' + artist + '&track=' + tracks[track].meta.title);
                    }
                }
                this.audio.src = tracks[track].name;
                if (typeof pauseSpotify === 'function') {
                    pauseSpotify();
                }
            }
        }

        if (initialPageOpened) {
            if (this.normalize && this.audioContext == null) {
                this.initializeAudioContext();
            }
            if (!tracks[track].name.startsWith('spotify:')) {
                this.audio.currentTime = 0;
                this.audio.play();
                this.audio.playbackRate = this.tempSpeed;
            }
        }
        this.update();
    }

    // Toggle player playback
    togglePlay(e) {
        if (e && e.stopPropagation) {
            e.stopPropagation();
        }
        if (this.track == null) {
            return;
        }
        if (this.normalize && this.audioContext == null) {
            this.initializeAudioContext();
        }
        if (tracks[this.track].name.startsWith('spotify:')) {
            spotifyPlayer.togglePlay();
        }
        else {
            if (this.audio.paused) {
                this.audio.play();
            } else {
                this.audio.pause();
            }
        }
        this.update();
    }

    // Mute the player
    toggleMuted() {
        audio.muted = !audio.muted;
        if (audio.muted) {
            this.buttons.toggleMuted.classList.add('muted');
        } else {
            this.buttons.toggleMuted.classList.remove('muted');
        }
    }

    // Increase playback speed
    faster() {
        this.playbackRate(this.audio.playbackRate + 0.25);
    }

    // Decrease playback speed
    slower() {
        this.playbackRate(this.audio.playbackRate - 0.25);
    }

    // Set playback speed
    playbackRate(number) {
        this.audio.playbackRate = number;
        this.tempSpeed = number;
        localStorage.setItem('speed', this.audio.playbackRate);
        if (this.player.getElementsByClassName('playbackRate')[0]) {
            this.player.getElementsByClassName('playbackRate')[0].innerText = this.audio.playbackRate.toFixed(2) + 'x';
        }
    }

    // Set volume
    volume(e) {
        const value = e.target ? e.target.value : e;
        if (e.target) {
            this.showRangeProgress(e.target);
        }
        localStorage.setItem('volume', value);
        this.audio.volume = value / 100;
    }

    showRangeProgress(rangeInput) {
        this.player.style.setProperty('--' + (rangeInput === this.progressSlider ? 'seek' : 'volume') + '-before-width', rangeInput.value / rangeInput.max * 100 + '%');
    }

    calculateTime(secs) {
        if (secs == undefined || !isFinite(secs) || (this.track && this.radio)) {
            return '';
        }
        const minutes = Math.floor(secs / 60);
        const seconds = Math.floor(secs % 60);
        const returnedSeconds = seconds < 10 ? '0' + seconds : seconds;
        return minutes + ':' + returnedSeconds;
    }

    displayDuration(duration) {
        this.duration.textContent = this.calculateTime(duration ? duration : this.audio.duration);
    }

    setSliderMax(max) {
        this.progressSlider.max = Math.floor(max ? max : this.audio.duration);
    }

    displayBufferedAmount() {
        const bufferedAmount = this.audio.buffered.length - 1 < 0 ? 0 : Math.floor(this.audio.buffered.end(this.audio.buffered.length - 1));
        this.player.style.setProperty('--buffered-width', ((bufferedAmount / this.progressSlider.max) * 100) + '%');
    }

    whilePlaying(self) {
        if (self.audio.paused) {
            return;
        }
        if (self.track && self.radio) {
            self.progressSlider.max = self.audio.currentTime;
        }
        self.currentTime.textContent = self.calculateTime(self.progressSlider.value);
        self.progressSlider.value = Math.floor(self.audio.currentTime);
        self.player.style.setProperty('--seek-before-width', (self.progressSlider.value / self.progressSlider.max * 100) + '%');
        self.player.style.setProperty('--progress', (self.progressSlider.value / self.progressSlider.max * 100) + '%');
        self.raf = requestAnimationFrame(function () {
            self.whilePlaying(self);
        });;
    }

    toggleRandom() {
        this.random = !this.random;
        if (this.random) {
            this.buttons.toggleRandom.classList.add('active');
        }
        else {
            this.buttons.toggleRandom.classList.remove('active');
        }
        localStorage.setItem('random', this.random ? 'true' : 'false');
        if (document.getElementById('album-random')) {
            if (this.random) {
                document.getElementById('album-random').classList.add('active');
            }
            else {
                document.getElementById('album-random').classList.remove('active');
            }
        }
    }

    update() {
        this.playing = (!this.audio.paused) || (typeof playSpotify !== 'undefined' && !spotifyPaused);
        for (const element of albumList.children) {
            const isActiveTrack = this.track && element.dataset.id == this.album && albums[this.album] && albums[this.album].tracks.includes(player.track);
            if (isActiveTrack) {
                element.classList.add('now-playing');
            }
            else {
                element.classList.remove('now-playing');
            }
            if (isActiveTrack && !this.playing) {
                element.classList.add('paused');
            }
            else {
                element.classList.remove('paused');
            }
        }
        for (const element of document.getElementsByClassName('play')) {
            if (this.track && this.album == element.dataset.id && this.playing) {
                element.classList.add('playing');
            }
            else {
                element.classList.remove('playing');
            }
        }
        if (document.getElementById('album-track-list')) {
            if (this.track && document.getElementById('album-play') && this.album == openedAlbum) {
                if (this.playing) {
                    document.getElementById('album-play').classList.add('playing');
                }
                else {
                    document.getElementById('album-play').classList.remove('playing');
                }
            }
            for (const element of document.getElementById('album-track-list').getElementsByTagName('tbody')[0].getElementsByTagName('tr')) {
                const isActiveTrack = player.track && element.dataset.id == player.track && albums[this.album] && albums[this.album].tracks.includes(player.track);
                if (isActiveTrack) {
                    element.classList.add('now-playing');
                }
                else {
                    element.classList.remove('now-playing');
                }
                if (isActiveTrack && !this.playing) {
                    element.classList.add('paused');
                }
                else {
                    element.classList.remove('paused');
                }
            }
        }
        if (albums['favorites'] && albums['favorites'].tracks.includes(this.track)) {
            player.buttons.toggleFavorite.classList.add('active');
        }
        else {
            player.buttons.toggleFavorite.classList.remove('active');
        }

        if (this.track == null || !tracks[this.track].name.startsWith('spotify:')) {
            if (this.audio.src == null || this.audio.src == '' || Math.floor(this.audio.duration) == 0) {
                this.progressSlider.value = 0;
            }
            else {
                this.progressSlider.value = this.audio.currentTime;
            }
        }
        this.currentTime.textContent = this.calculateTime(this.progressSlider.value);

        const self = this;
        if (this.playing) {
            for (const element of this.player.querySelectorAll('[data-event="togglePlay"]')) {
                element.classList.add('playing');
            }
            this.raf = requestAnimationFrame(function () {
                self.whilePlaying(self);
            });
        } else {
            for (const element of this.player.querySelectorAll('[data-event="togglePlay"]')) {
                element.classList.remove('playing');
            }
            cancelAnimationFrame(this.raf);
        }

        const value = this.volumeSlider.value;
        this.audio.volume = value / 100;

        updateQueue();
        if (this.track) {
            albumImage.src = tracks[this.track].pictures[0] ? (tracks[this.track].pictures[0].url + '?v=' + tracks[this.track].pictures[0].version) : 'svg/placeholder.svg';
            albumImage.alt = tracks[this.track].meta.album ? tracks[this.track].meta.album : text('unknown_album');
            this.player.getElementsByClassName('name')[0].innerText = tracks[this.track].meta.title;
            artistLinks(this.player.getElementsByClassName('artist')[0], this.track);
            updateFullscreenContainer();
            updateMediaSession(this.track);
        }
    }

    async toggleFavorite() {
        const favoriteButton = player.buttons.toggleFavorite;
        if (albums['favorites'] == null) {
            favoriteButton.style.display = 'none';
            return;
        }
        if (player.track == null) {
            return;
        }

        favoriteButton.classList.toggle('active');
        const remove = !favoriteButton.classList.contains('active');
        const track = player.track;
        if (remove) {
            albums['favorites'].tracks = albums['favorites'].tracks.filter(item => item !== track);
        }
        else {
            albums['favorites'].tracks.push(track);
        }
        populateFeed();
        if (openedAlbum && openedAlbum == 'favorites') {
            openAlbum('favorites');
        }

        let formData = new FormData();
        formData.append('album', 'favorites');
        for (const albumTrack of albums['favorites'].tracks) {
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
        }
    }

    constructor(player) {
        this.player = player;
        this.audio = this.player.getElementsByTagName('audio')[0];
        this.speed = this.player.getElementsByClassName('playbackRate')[0];
        this.playbackRate(isNaN(parseFloat(localStorage.getItem('playbackRate'))) ? 1 : parseFloat(localStorage.getItem('playbackRate')));

        this.track = null;
        this.album = null;
        this.radio = null;
        this.playing = false;

        this.queue = [];
        this.loopQueue = [];
        this.previousQueue = [];

        this.progress = player.getElementsByClassName('progress')[0];

        const self = this;
        this.buttons = [];
        for (const element of this.player.querySelectorAll('[data-event]')) {
            if (this[element.dataset.event]) {
                element.addEventListener('click', this[element.dataset.event].bind(self));
                this.buttons[element.dataset.event] = element;
            }
        }


        this.repeat = localStorage.getItem('repeat') ? localStorage.getItem('repeat') : repeat.OFF;
        if (localStorage.getItem('repeat') == repeat.CONTEXT) {
            this.toggleRepeat(1);
        }
        else if (localStorage.getItem('repeat') == repeat.TRACK) {
            this.toggleRepeat(2);
        }
        this.random = localStorage.getItem('random') == 'true';
        if (localStorage.getItem('random') == 'true') {
            this.toggleRandom();
        }

        // Normalization
        this.audioContext = null;
        this.mediaElementSource = null;
        this.gainNode = null;
        this.normalize = true;

        this.audio.addEventListener('error', function () {
            self.stop();
        });
        this.audio.addEventListener('ended', function () {
            self.queueNext();
        });
        this.audio.addEventListener('progress', function () {
            self.displayBufferedAmount();
        });
        this.audio.addEventListener('loadedmetadata', function () {
            self.displayDuration();
            self.setSliderMax();
            self.displayBufferedAmount();
        });

        this.progressSlider = this.player.getElementsByClassName('progress')[0];
        this.progressSlider.addEventListener('change', function (e) {
            self.audio.currentTime = this.value;
            self.showRangeProgress(e.target);
            self.currentTime.textContent = self.calculateTime(this.value);
            if (!self.audio.paused) {
                self.raf = requestAnimationFrame(function () {
                    self.whilePlaying(self);
                });;
            }
        });


        this.volumeSlider = this.player.getElementsByClassName('volume')[0];
        this.volumeSlider.addEventListener('input', function (e) {
            self.volume(e);
        });
        this.volume(isNaN(parseFloat(localStorage.getItem('volume'))) ? 1 : parseFloat(localStorage.getItem('volume')));

        this.currentTime = this.player.getElementsByClassName('currentTime')[0];
        this.duration = this.player.getElementsByClassName('duration')[0];
    }
}

let player = new Player(document.getElementById('player'));
player.normalize = document.getElementsByTagName('body')[0].dataset.normalize == 'true';

async function updateMediaSession(data) {
    const response = await fetch(tracks[data].pictures[0] ? tracks[data].pictures[0].url : 'svg/placeholder.svg');
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    navigator.mediaSession.metadata = new MediaMetadata({
        title: tracks[data].meta.title ? tracks[data].meta.title : text('unknown_title'),
        artist: tracks[data].meta.artist ? tracks[data].meta.artist : text('unknown_artist'),
        album: tracks[data].meta.album ? tracks[data].meta.album : text('unknown_album'),
        artwork: [
            { src: url, type: tracks[data].pictures[0] ? tracks[data].pictures[0].mime : 'image/svg+xml' }
        ]
    });
}
/* Implementation of the Media Session API */
if ('mediaSession' in navigator) {
    navigator.mediaSession.setActionHandler('play', function () { player.togglePlay(); });
    navigator.mediaSession.setActionHandler('pause', function () { player.togglePlay(); });
    navigator.mediaSession.setActionHandler('seekbackward', function (details) {
        player.setTime(player.audio.currentTime - (details.seekOffset || 10));
    });
    navigator.mediaSession.setActionHandler('seekforward', function (details) {
        player.setTime(player.audio.currentTime + (details.seekOffset || 10));
    });
    navigator.mediaSession.setActionHandler('nexttrack', function () { player.queueNext(); });
    navigator.mediaSession.setActionHandler('previoustrack', function () { player.queuePrevious(); });
    navigator.mediaSession.setActionHandler('seekto', function (details) {
        if (details.fastSeek && 'fastSeek' in audio) {
            player.audio.fastSeek(details.seekTime);
            return;
        }
        player.setTime(details.seekTime);
    });
    navigator.mediaSession.setActionHandler('stop', function () {
        this.audio.pause();
        player.setTime(0);
        if (player.playing) {
            for (const element of this.player.querySelectorAll('[data-event="togglePlay"]')) {
                element.classList.remove('playing');
            }
        }
    });
}