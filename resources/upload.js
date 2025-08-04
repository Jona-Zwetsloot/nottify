let mm;
const fileInput = document.getElementById('file');
fileInput.value = null;
async function calculateGain(file) {
    let audioGainCtx = new AudioContext();
    let gain = 1;
    let decodedData;
    // Decode mp3 files with decode-audio-data-fast, which is 2.5 times faster on average
    // Really makes a difference if you have a big file which would normally take 3 seconds to decode 
    if (file.name && file.name.split('.').pop() == 'mp3') {
        try {
            decodedData = await DADF.getFileAudioBuffer(file, audioGainCtx);
        }
        catch (e) {
            // Since the filename extension could be fake, fallback to browser audioBuffer if decoding with DADF fails
            console.warn(text('dadf_error'));
            try {
                const arrayBuffer = await file.arrayBuffer();
                decodedData = await audioGainCtx.decodeAudioData(arrayBuffer);
            }
            catch (e) {
                console.warn(text('normalize_error_description').replace('<filename>', file.name))
                sendNotification('normalize_error', text('normalize_error_description').replace('<filename>', file.name), true);
                return;
            }
        }
    }
    // We don't have a mp3 file, so decode it with the native API
    else {
        try {
            const arrayBuffer = await file.arrayBuffer();
            decodedData = await audioGainCtx.decodeAudioData(arrayBuffer);
        }
        catch (e) {
            console.warn(text('normalize_error_description').replace('<filename>', file.name))
            sendNotification('normalize_error', text('normalize_error_description').replace('<filename>', file.name), true);
            return;
        }
    }
    let decodedBuffer = decodedData.getChannelData(0);
    let sliceLen = Math.floor(decodedData.sampleRate * 0.05);
    let averages = [];
    let sum = 0.0;
    for (let i = 0; i < decodedBuffer.length; i++) {
        sum += decodedBuffer[i] ** 2;
        if (i % sliceLen === 0) {
            sum = Math.sqrt(sum / sliceLen);
            averages.push(sum);
            sum = 0;
        }
    }
    // Ascending sort of the averages array
    averages.sort(function (a, b) { return a - b; });
    // Take the average at the 95th percentile
    let a = averages[Math.floor(averages.length * 0.95)];

    gain = 1.0 / a;

    // ReplayGain uses pink noise for this one one but we just take
    // some arbitrary value... we're no standard
    // Important is only that we don't output on levels
    // too different from other websites
    gain = gain / 10.0;

    gain = Math.max(gain, 0.02);
    gain = Math.min(gain, 20);
    return gain;
}

async function uploadFile(file, lrc) {
    let gain;
    if (document.getElementsByTagName('body')[0].dataset.calculateGain == 'true') {
        // Skip gain calculation for large files (because otherwise computer does boom)
        if (file.size / 1000000 < 100) {
            gain = await calculateGain(file);
        }
    }

    if (mm == null) {
        mm = await import('./lib/music-metadata.js');
    }
    // Parse metadata using music-metadata
    const metadata = await mm.parseBlob(file);

    let meta = metadata.common;
    if (meta.title == null) {
        meta.title = file.name.replace(/\.[^.]+?$/, '');
    }

    let formData = new FormData();
    if (meta.picture && meta.picture.length > 0) {
        function Uint8ToString(u8a) {
            let CHUNK_SZ = 0x8000;
            let c = [];
            for (let i = 0; i < u8a.length; i += CHUNK_SZ) {
                c.push(String.fromCharCode.apply(null, u8a.subarray(i, i + CHUNK_SZ)));
            }
            return c.join("");
        }
        for (const picture of meta.picture) {
            let u8 = new Uint8Array(picture.data);
            formData.append('picture[]', btoa(Uint8ToString(u8)));
        }
    }

    formData.append('file', file);
    if (gain) {
        formData.append('gain', gain);
    }
    delete meta.picture;
    formData.append('meta', JSON.stringify(meta));
    formData.append('format', JSON.stringify(metadata.format));

    const json = await request('api/upload', {
        method: 'POST',
        body: formData
    }, ['track_key', 'track', 'album_key', 'album']);

    if (json) {
        tracks[json.track_key] = json.track;
        albums[json.album_key] = json.album;
        if (albums['all_tracks']) {
            albums['all_tracks'].tracks.push(json.track_key);
        }
        if (lrc) {
            const reader = new FileReader();
            reader.onload = async function (e) {
                let formData = new FormData();
                formData.append('lyrics', e.target.result);
                formData.append('track', json.track_key);
                const lrcJSON = await request('lyrics', {
                    method: 'POST',
                    body: formData
                }, ['url']);
                if (lrcJSON) {
                    tracks[json.track_key].lyrics = lrcJSON;
                }
            };
            reader.onerror = () => {
                sendNotification('error', 'read_error');
            };
            reader.readAsText(lrc);
        }
        addMissingMetaData();
        populateFeed();
        openAlbum(json.album_key);
    }
}

fileInput.addEventListener('change', async function () {
    const files = [...this.files];
    let startsWithNumbers = true;
    for (const file of files) {
        if (!file.name.match(/^\d/)) {
            startsWithNumbers = false;
            break;
        }
    }
    if (startsWithNumbers) {
        files.sort(function (a, b) {
            return a.name.localeCompare(b.name);
        });
    }
    let i = 1;
    for (const file of files) {
        if (file.name.endsWith('.lrc') || file.name.endsWith('.txt')) {
            continue;
        }
        const notification = sendNotification('processing_files', text('processing_files_description').replace('<count>', i).replace('<total>', files.length));
        let zipMimes = ['application/zip', 'application/octet-stream', 'application/x-zip-compressed', 'multipart/x-zip', 'application/zip-compressed'];
        if (zipMimes.includes(file.type)) {
            let j = 1;
            let zip;
            try {
                zip = await JSZip.loadAsync(file);
            }
            catch (e) {
                sendNotification('file_too_big', 'file_too_big_description');
                return;
            }
            for (const fileName in zip.files) {
                const subnotification = sendNotification('processing_zip', text('processing_zip_description').replace('<count>', j).replace('<total>', Object.keys(zip.files).length).replace('<filename>', file.name));
                if (fileName.endsWith('.lrc')) {
                    if (notification) {
                        notification.getElementsByTagName('button')[0].click();
                    }
                    i++;
                    continue;
                }
                const file = zip.files[fileName];
                if (!file.dir) {
                    let content = await file.async('blob');
                    content.name = fileName;
                    let lrcFile = null;
                    for (const lrcName in zip.files) {
                        if ((lrcName.endsWith('.lrc') || lrcName.endsWith('.txt')) && lrcName.replace(/\.[^.]+?$/, '') == fileName.replace(/\.[^.]+?$/, '')) {
                            lrcFile = await zip.files[lrcName].async('blob');
                            break;
                        }
                    }
                    await uploadFile(content, lrcFile);
                }
                if (subnotification) {
                    subnotification.getElementsByTagName('button')[0].click();
                }
                j++;
            }
        }
        else {
            let lrcFile = null;
            for (const lrc of files) {
                if ((lrc.name.endsWith('.lrc') || lrc.name.endsWith('.txt')) && (lrc.name.replace(/\.[^.]+?$/, '') == file.name.replace(/\.[^.]+?$/, '') || files.length == 2)) {
                    lrcFile = lrc;
                    break;
                }
            }
            await uploadFile(file, lrcFile);
        }
        if (notification) {
            notification.getElementsByTagName('button')[0].click();
        }
        i++;
    }
});