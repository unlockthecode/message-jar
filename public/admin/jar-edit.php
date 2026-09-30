<?php
declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';

$user = require_admin();

$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$jar = $id ? jar_find($id) : null;

if ($id && !$jar) {
    abort(404, 'Jar not found.');
}

$isNew = $jar === null;

if ($isNew) {
    $jar = [
        'id'            => 0,
        'name'          => '',
        'category'      => '',
        'description'   => '',
        'emoji'         => '',
        'theme_color'   => '#ff8fab',
        'is_active'     => 1,
        'display_order' => jar_next_order(),
    ];
}

$errors = $_SESSION['form_errors'] ?? [];
$posted = $_SESSION['form_values'] ?? null;
unset($_SESSION['form_errors'], $_SESSION['form_values']);

if ($posted) {
    $jar = array_merge($jar, $posted);
}

$title  = $isNew ? 'New jar' : 'Edit jar';
$active = 'jars';
require __DIR__ . '/../../templates/header.php';
require __DIR__ . '/../../templates/admin_header.php';
?>
<main class="admin">

    <div class="page-title-row">
        <h1><?= $isNew ? 'New jar' : 'Edit jar' ?></h1>
        <div>
            <a href="/admin/jars.php" class="link">Back to jars</a>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert">
            <ul>
                <?php foreach ($errors as $msg): ?>
                    <li><?= e($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/jar-save.php" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$jar['id'] ?>">

        <label>
            Name
            <input type="text" name="name" required maxlength="100"
                   value="<?= e((string)$jar['name']) ?>">
        </label>

        <label>
            Category <span class="muted small">(optional — used to group jars in the admin list)</span>
            <input type="text" name="category" list="category-list" maxlength="50"
                   value="<?= e((string)($jar['category'] ?? '')) ?>"
                   placeholder="e.g. Feelings, Time of Day, Milestones">
            <datalist id="category-list">
                <?php foreach (jar_categories() as $cat): ?>
                    <option value="<?= e($cat) ?>">
                <?php endforeach; ?>
            </datalist>
        </label>

        <label>
            Description <span class="muted small">(optional, 255 chars max)</span>
            <input type="text" name="description" maxlength="255"
                   value="<?= e((string)$jar['description']) ?>">
        </label>

        <div class="form-row">
            <label class="emoji-field">
                Emoji
                <div class="emoji-input-wrap">
                    <input type="text" name="emoji" id="emoji-input" maxlength="8"
                           value="<?= e((string)$jar['emoji']) ?>" placeholder="❤️"
                           autocomplete="off">
                    <button type="button" class="emoji-trigger" id="emoji-trigger"
                            aria-label="Pick an emoji">😊</button>
                </div>
                <div class="emoji-picker" id="emoji-picker" hidden></div>
            </label>

            <label>
                Theme color
                <input type="color" name="theme_color"
                       value="<?= e((string)($jar['theme_color'] ?: '#ff8fab')) ?>">
            </label>

            <label>
                Order
                <input type="number" name="display_order" min="0" max="100000"
                       value="<?= (int)$jar['display_order'] ?>">
            </label>
        </div>

        <label class="checkbox">
            <input type="checkbox" name="is_active" value="1"
                <?= !empty($jar['is_active']) ? 'checked' : '' ?>>
            Active (visible to the user)
        </label>

        <div class="form-actions">
            <button type="submit" class="btn">Save</button>
            <a href="/admin/jars.php" class="link">Cancel</a>
        </div>
    </form>
</main>
<?php require __DIR__ . '/../../templates/footer.php'; ?>