<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PriviMetrics Updater</title>
    <link rel="stylesheet" href="../styles.css">
    <meta name="robots" content="noindex,nofollow">
    <style>
        @font-face {
            font-family: 'Sora';
            src: url('../fonts/sora.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            margin: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 24px;
            display: flex;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: white;
            background: var(--accent);
        }

        .container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 100px 20px;
            gap: 24px;
        }

        h1 {
            font-size: 2.5rem;
            color: var(--accent);
            margin-bottom: 16px;
        }

        p {
            font-size: 1.2rem;
            color: var(--text-secondary);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            color: white;
            background: var(--accent);
            transition: all 0.2s;
        }

        .btn:hover {
            background: var(--accent-hover);
        }

        .footer {
            text-align: center;
            padding: 24px;
            font-size: 14px;
            color: var(--text-secondary);
            background: var(--bg-tertiary);
            border-top: 1px solid var(--border-color);
        }

        .steps {
            margin-top: 32px;
            text-align: left;
            max-width: 500px;
        }

        .steps h2 {
            font-size: 1.4rem;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .steps ol {
            padding-left: 20px;
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .steps li {
            margin-bottom: 8px;
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="logo">
            <div>PriviMetrics</div>
        </div>
    </header>

    <div class="container">
        <h1>PriviMetrics Updater</h1>
        <p>Download the updater to enable automatic updates and backups for your PriviMetrics installation.</p>
        <a class="btn" href="https://dl.wbsrv.icu/?file=privimetrics-updater" target="_blank">Download Updater</a>

    <div class="steps">
        <h2>How to download</h2>
        <ol>
            <li>Download PriviMetrics Updater</li>
            <li>Replace this folder with the downloaded Updater</li>
            <li>Refresh this page</li>
        </ol>
    </div>

    </div>

    <div class="footer">
        &copy; <?= date('Y') ?> PriviMetrics. Made with ❤️ by WebOrbiton.
    </div>
</body>

</html>
