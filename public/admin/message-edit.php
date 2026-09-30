<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

$user = require_admin();

$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = $id ? message_find($id) : null;

if ($id && !$msg) {
    abort(404, 'Message not found.');
}

if ($msg) {
    $jar = jar_find((int)$msg['jar_id']);
} else {
    $jarId = isset($_GET['jar_id']) ? (int)$_GET['jar_id'] : 0;
    $jar   = $jarId ? jar_find($jarId) : null;
}
if (!$jar) {
    abort(404, 'Jar not found.');
}

$isNew = $msg === null;

if ($isNew) {
    $msg = [
        'id'           => 0,
        'jar_id'       => (int)$jar['id'],
        'body'         => '',
        'image_url'    => '',
        'youtube_id'   => '',
        'external_url' => '',
        'unlock_at'    => '',
        'expires_at'   => '',
        'is_active'    => 1,
    ];
}

$errors = $_SESSION['form_errors'] ?? [];
$posted = $_SESSION['form_values'] ?? null;
unset($_SESSION['form_errors'], $_SESSION['form_values']);
if ($posted) {
    $msg = array_merge($msg, $posted);
}

$youtubeInput = $msg['youtube_id'] ?? '';
if ($youtubeInput && strlen($youtubeInput) === 11) {
    $youtubeInput = 'https://www.youtube.com/watch?v=' . $youtubeInput;
}

$title  = $isNew ? 'New message' : 'Edit message';
$active = 'jars';
require __DIR__ . '/../../templates/header.php';
require __DIR__ . '/../../templates/admin_header.php';
?>
<main class="admin">

    <div class="page-title-row">
        <h1><?= $isNew ? 'New message' : 'Edit message' ?></h1>
        <div>
            <a href="/admin/messages.php?jar_id=<?= (int)$jar['id'] ?>" class="link">
                Back to <?= e($jar['name']) ?>
            </a>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert">
            <ul>
                <?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/message-save.php" class="form"
      data-csrf="<?= e(csrf_token()) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$msg['id'] ?>">
        <input type="hidden" name="jar_id" value="<?= (int)$jar['id'] ?>">

        <label>
            Message
            <textarea name="body" rows="6" required maxlength="5000"
                      placeholder="Baby, I know you're missing me right now..."><?= e((string)$msg['body']) ?></textarea>
        </label>

        <label class="image-field">
            Image <span class="muted small">(optional)</span>
            <div class="image-input-wrap">
                <input type="url" name="image_url" id="image-url-input" maxlength="500"
                       value="<?= e((string)($msg['image_url'] ?? '')) ?>"
                       placeholder="https://ik.imagekit.io/... or upload →">
                <input type="file" id="image-file-input" accept="image/*" class="hidden-file-input">
                <button type="button" class="btn-secondary" id="image-upload-btn">
                    Upload
                </button>
            </div>
            <div id="image-upload-status" class="muted small"></div>
            <div id="image-upload-preview" class="image-preview"></div>
        </label>

        <label>
            YouTube URL <span class="muted small">(optional)</span>
            <input type="text" name="youtube_url" maxlength="500"
                   value="<?= e($youtubeInput) ?>"
                   placeholder="https://www.youtube.com/watch?v=...">
        </label>

        <label>
            External link <span class="muted small">(optional, must be HTTPS)</span>
            <input type="url" name="external_url" maxlength="500"
                   value="<?= e((string)($msg['external_url'] ?? '')) ?>"
                   placeholder="https://open.spotify.com/track/...">
        </label>

        <div class="form-row">
            <label>
                Unlock at <span class="muted small">(optional)</span>
                <input type="datetime-local" name="unlock_at"
                       value="<?= e(datetime_local_value((string)($msg['unlock_at'] ?? ''))) ?>">
            </label>

            <label>
                Expires at <span class="muted small">(optional)</span>
                <input type="datetime-local" name="expires_at"
                       value="<?= e(datetime_local_value((string)($msg['expires_at'] ?? ''))) ?>">
            </label>
        </div>

        <label class="checkbox">
            <input type="checkbox" name="is_active" value="1"
                <?= !empty($msg['is_active']) ? 'checked' : '' ?>>
            Active (eligible to be drawn)
        </label>

        <div class="form-actions">
            <button type="submit" class="btn">Save</button>
            <a href="/admin/messages.php?jar_id=<?= (int)$jar['id'] ?>" class="link">Cancel</a>
        </div>
    </form>
</main>
<?php
/**
 * Convert a UTC MySQL DATETIME string to 'Y-m-d\TH:i' in the
 * display timezone, for use in <input type="datetime-local">.
 * Empty stays empty.
 */
function datetime_local_value(string $dbValue): string
{
    if ($dbValue === '') {
        return '';
    }
    return format_display_time($dbValue, 'Y-m-d\TH:i');
}
require __DIR__ . '/../../templates/footer.php';