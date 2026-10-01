<?php
declare(strict_types=1);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
?>
{
    "name": "Message Jar",
    "short_name": "Jars",
    "description": "A private jar of messages for my darling.",
    "start_url": "/dashboard.php",
    "scope": "/",
    "display": "standalone",
    "orientation": "portrait",
    "background_color": "#fff1f5",
    "theme_color": "#e75480",
    "icons": [
        {
            "src": "/assets/icons/icon-192.png",
            "sizes": "192x192",
            "type": "image/png",
            "purpose": "any maskable"
        },
        {
            "src": "/assets/icons/icon-512.png",
            "sizes": "512x512",
            "type": "image/png",
            "purpose": "any maskable"
        }
    ]
}