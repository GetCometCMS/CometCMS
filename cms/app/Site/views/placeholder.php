<!doctype html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="dark">
    <title><?= $title ?></title>
    <style>
        * { box-sizing: border-box; }
        html { min-height: 100%; background: #060914; }
        body { margin: 0; color: #fff; }
        .space {
            position: relative;
            display: grid;
            place-items: center;
            min-height: 100vh;
            min-height: 100svh;
            overflow: hidden;
            isolation: isolate;
            background:
                radial-gradient(ellipse at 50% 48%, #28367238, transparent 48%),
                radial-gradient(ellipse at 88% 100%, #48387930, transparent 55%),
                radial-gradient(ellipse at 0% 10%, #123c5326, transparent 50%),
                #060914;
        }
        .stars, .comets { position: absolute; inset: 0; pointer-events: none; z-index: -1; }
        .stars::before, .stars::after {
            content: "";
            position: absolute;
            inset: 0;
            background-repeat: repeat;
        }
        .stars::before {
            opacity: .48;
            background-image:
                radial-gradient(1px 1px at 19px 31px, #dce5ff 90%, transparent),
                radial-gradient(1px 1px at 137px 173px, #dce5ff 90%, transparent),
                radial-gradient(1px 1px at 239px 83px, #dce5ff 90%, transparent);
            background-size: 307px 269px, 419px 347px, 503px 433px;
        }
        .stars::after {
            opacity: .65;
            background-image:
                radial-gradient(1.5px 1.5px at 113px 87px, #eff4ff 60%, transparent),
                radial-gradient(1.5px 1.5px at 331px 219px, #bccdff 60%, transparent);
            background-size: 557px 449px, 713px 587px;
        }
        .orbit {
            position: absolute;
            z-index: -1;
            width: max(1100px, 130vw);
            aspect-ratio: 1;
            left: 30%;
            top: 72%;
            border: 1px solid #b5c5ff12;
            border-radius: 50%;
            box-shadow: 0 -15px 100px #6a7dc508, inset 0 35px 100px #6a7dc505;
            pointer-events: none;
        }
        .brand { margin: 0; padding: 40px 28px; }
        .brand img {
            display: block;
            width: clamp(220px, 38vw, 420px);
            max-width: 100%;
            height: auto;
            filter: drop-shadow(0 0 36px #b1c5ff20);
        }
        .comet {
            position: absolute;
            top: 12%;
            left: 65%;
            width: 180px;
            height: 1px;
            opacity: 0;
            background: linear-gradient(90deg, transparent, #a9caff80 85%, #fff);
            transform: rotate(145deg);
            animation: comet 14s linear 2s infinite;
        }
        .comet::after {
            content: "";
            position: absolute;
            right: 0;
            top: -1px;
            width: 3px;
            height: 3px;
            border-radius: 50%;
            background: #f4f8ff;
            box-shadow: 0 0 8px 2px #c9dcff80;
        }
        .comet:nth-child(2) { top: 35%; left: 95%; width: 120px; animation-delay: 9s; animation-duration: 19s; }
        @keyframes comet {
            0% { opacity: 0; transform: translate(0, 0) rotate(145deg); }
            2% { opacity: .85; }
            8% { opacity: 0; transform: translate(-380px, 266px) rotate(145deg); }
            100% { opacity: 0; transform: translate(-380px, 266px) rotate(145deg); }
        }
        @media (prefers-reduced-motion: reduce) {
            .comet { animation: none; }
        }
    </style>
</head>
<body>
    <main class="space">
        <div class="stars" aria-hidden="true"></div>
        <div class="orbit" aria-hidden="true"></div>
        <div class="comets" aria-hidden="true"><span class="comet"></span><span class="comet"></span></div>
        <h1 class="brand"><img src="data:image/png;base64,<?= $logo ?>" alt="CometCMS" width="1190" height="259"></h1>
    </main>
</body>
</html>
