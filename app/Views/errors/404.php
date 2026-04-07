<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f1e8;
            --panel: #fffaf2;
            --text: #201a14;
            --muted: #6f6256;
            --accent: #c75b39;
            --border: #e3d5c5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at top, rgba(199, 91, 57, 0.16), transparent 38%),
                linear-gradient(180deg, var(--bg), #efe7db);
            color: var(--text);
            font-family: Georgia, "Times New Roman", serif;
        }

        main {
            max-width: 640px;
            padding: 40px 32px;
            border: 1px solid var(--border);
            border-radius: 24px;
            background: var(--panel);
            box-shadow: 0 20px 60px rgba(32, 26, 20, 0.08);
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(3rem, 10vw, 5.5rem);
            line-height: 0.9;
            color: var(--accent);
        }

        h2 {
            margin: 0 0 16px;
            font-size: clamp(1.5rem, 4vw, 2.2rem);
        }

        p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.7;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <main>
        <h1>404</h1>
        <h2>Halaman tidak ditemukan</h2>
        <p>Request yang Anda buka tidak cocok dengan route mana pun di aplikasi ini.</p>
    </main>
</body>
</html>
