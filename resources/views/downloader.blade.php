<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ranulfo YT Downloader</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #1e1b4b, #4c1d95, #7c1d6f, #1e1b4b);
            background-size: 400% 400%;
            animation: bgShift 15s ease infinite;
            color: #fff;
        }

        @keyframes bgShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Top bar with animated logo */
        .topbar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 28px;
            background: rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(6px);
        }

        .logo-icon {
            font-size: 26px;
            display: inline-block;
            animation: spinPulse 3s ease-in-out infinite;
        }

        @keyframes spinPulse {
            0%   { transform: rotate(0deg) scale(1); }
            25%  { transform: rotate(15deg) scale(1.15); }
            50%  { transform: rotate(0deg) scale(1); }
            75%  { transform: rotate(-15deg) scale(1.15); }
            100% { transform: rotate(0deg) scale(1); }
        }

        .logo-text {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.5px;
            background: linear-gradient(90deg, #ff6ec4, #7873f5, #4ade80, #ff6ec4);
            background-size: 300% auto;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shine 4s linear infinite;
        }

        @keyframes shine {
            0%   { background-position: 0% center; }
            100% { background-position: 300% center; }
        }

        /* Main container */
        .container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px 60px;
        }

        h1.page-title {
            text-align: center;
            font-size: 28px;
            margin-bottom: 30px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.4);
        }

        .search-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
        }

        .search-row {
            display: flex;
            gap: 10px;
        }

        input[type=text] {
            flex: 1;
            padding: 12px 14px;
            border-radius: 10px;
            border: none;
            outline: none;
            font-size: 15px;
        }

        button {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            background: linear-gradient(90deg, #ff6ec4, #7873f5);
            color: white;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(120, 115, 245, 0.5);
        }

        button:active {
            transform: translateY(0);
        }

        #statusMsg {
            margin-top: 14px;
            color: #ffd6d6;
            font-weight: 500;
            min-height: 20px;
        }

        #result {
            margin-top: 26px;
            display: none;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 16px;
            padding: 20px;
            backdrop-filter: blur(10px);
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        #result img {
            max-width: 100%;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }

        .title-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .title-row h2 {
            margin: 0;
            font-size: 19px;
            flex: 1;
        }

        #copyBtn {
            background: linear-gradient(90deg, #4ade80, #22c55e);
            padding: 8px 14px;
            font-size: 13px;
        }

        #desc {
            white-space: pre-wrap;
            color: #e5e5e5;
            font-size: 14px;
            max-height: 150px;
            overflow-y: auto;
            margin-top: 10px;
            line-height: 1.5;
        }

        .controls-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        select {
            padding: 10px 14px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        #downloadBtn {
            background: linear-gradient(90deg, #f59e0b, #ef4444);
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 4px; }
    </style>
</head>
<body>

    <div class="topbar">
        <span class="logo-icon">▶</span>
        <span class="logo-text">Ranulfo YT Downloader</span>
    </div>

    <div class="container">
        <h1 class="page-title">Download YouTube Videos in HD</h1>

        <div class="search-card">
            <div class="search-row">
                <input type="text" id="ytUrl" placeholder="Paste YouTube link here...">
                <button id="scanBtn">Scan</button>
            </div>
            <p id="statusMsg"></p>
        </div>

        <div id="result">
            <img id="thumb" src="" alt="thumbnail">
            <div class="title-row">
                <h2 id="videoTitle"></h2>
                <button id="copyBtn">Copy Title</button>
            </div>
            <p id="desc"></p>

            <div class="controls-row">
                <label for="resolution">Resolution:</label>
                <select id="resolution"></select>
                <button id="downloadBtn">Download</button>
            </div>
        </div>
    </div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let currentUrl = '';

document.getElementById('scanBtn').addEventListener('click', async () => {
    const url = document.getElementById('ytUrl').value.trim();
    const statusMsg = document.getElementById('statusMsg');
    statusMsg.textContent = '';

    if (!url) {
        statusMsg.textContent = 'Please paste a YouTube link.';
        return;
    }

    statusMsg.textContent = 'Scanning...';

    try {
        const res = await fetch("{{ route('yt.scan') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ url }),
        });

        const data = await res.json();

        if (!res.ok) {
            statusMsg.textContent = data.error || 'Something went wrong.';
            document.getElementById('result').style.display = 'none';
            return;
        }

        currentUrl = data.url;
        statusMsg.textContent = '';
        document.getElementById('thumb').src = data.thumbnail;
        document.getElementById('videoTitle').textContent = data.title;
        document.getElementById('desc').textContent = data.description;

        const select = document.getElementById('resolution');
        select.innerHTML = '';
        data.resolutions.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r;
            opt.textContent = r + 'p';
            select.appendChild(opt);
        });

        document.getElementById('result').style.display = 'block';
    } catch (err) {
        statusMsg.textContent = 'Error scanning video.';
    }
});

document.getElementById('copyBtn').addEventListener('click', () => {
    const title = document.getElementById('videoTitle').textContent;
    navigator.clipboard.writeText(title).then(() => {
        const btn = document.getElementById('copyBtn');
        const original = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = original, 1500);
    });
});

document.getElementById('downloadBtn').addEventListener('click', async () => {
    const resolution = document.getElementById('resolution').value;
    const statusMsg = document.getElementById('statusMsg');
    statusMsg.textContent = 'Downloading... this can take a while for higher resolutions.';

    try {
        const res = await fetch("{{ route('yt.download') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ url: currentUrl, resolution }),
        });

        if (!res.ok) {
            const data = await res.json();
            statusMsg.textContent = data.error || 'Download failed.';
            return;
        }

        const blob = await res.blob();
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'video.mp4';
        link.click();
        statusMsg.textContent = '';
    } catch (err) {
        statusMsg.textContent = 'Error downloading video.';
    }
});
</script>

</body>
</html>