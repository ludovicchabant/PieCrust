<!doctype html>
<html>
<head>
    <title>{$site.title} &mdash; {$page.title}</title>
    <meta name="generator" content="PieCrust" />
    <meta name="template-engine" content="Dwoo" />
</head>
<body>
    <div id="container">
        <div id="header">
            <h1>{$site.title}</h1>
        </div>
        <div id="content">
            {$content}
        </div>
        <div id="sidebar">
            <ul>
                <li>{a $site.root}Home{/}</li>
                <li>{pca about}About{/}</li>
                <li>{pca support}Support{/}</li>
            </ul>
        </div>
        <div id="footer">
            <p>Baked with <em>PieCrust {$piecrust.version}</em>.</p>
        </div>
    </div>
</body>
</html>
