<?php
declare(strict_types=1);

/**
 * Rendering helpers for the message card.
 * Every function returns escaped HTML.
 */

/**
 * Render the entire message card HTML.
 */
function render_message_card(array $msg): string
{
    $html  = '<article class="msg-card">';

    $html .= '<div class="msg-body">' . nl2br(e($msg['body'])) . '</div>';

    if (!empty($msg['image_url'])) {
        $html .= render_message_image((string)$msg['image_url']);
    }

    if (!empty($msg['youtube_id'])) {
        $html .= render_message_youtube((string)$msg['youtube_id']);
    }

    if (!empty($msg['external_url'])) {
        $html .= render_message_link((string)$msg['external_url']);
    }

    $html .= '</article>';
    return $html;
}

/**
 * Image: re-validate at render time too.
 * The DB could contain an old row saved before validation tightened.
 */
function render_message_image(string $url): string
{
    if (!imagekit_url_ok($url)) {
        return '';
    }
    return '<figure class="msg-image">'
         . '<img src="' . e($url) . '" alt="" loading="lazy" decoding="async">'
         . '</figure>';
}

/**
 * YouTube: only embed if the ID passes the strict regex.
 * Uses youtube-nocookie.com to reduce third-party tracking.
 */
function render_message_youtube(string $id): string
{
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
        return '';
    }
    $src = 'https://www.youtube-nocookie.com/embed/' . $id;
    return '<figure class="msg-video">'
         . '<iframe src="' . e($src) . '" '
         . 'title="Video message" '
         . 'allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" '
         . 'allowfullscreen loading="lazy" '
         . 'referrerpolicy="strict-origin-when-cross-origin"></iframe>'
         . '<figcaption class="msg-video-caption">A little video for you ♫</figcaption>'
         . '</figure>';
}

/**
 * External link: HTTPS-only, target=_blank with noopener + noreferrer.
 * Show the hostname as the link text so the destination is obvious.
 */
function render_message_link(string $url): string
{
    if (!is_https_url($url)) {
        return '';
    }
    $label = link_label_for($url);
    return '<p class="msg-link">'
         . '<a href="' . e($url) . '" '
         . 'target="_blank" rel="noopener noreferrer">'
         . e($label)
         . '</a></p>';
}