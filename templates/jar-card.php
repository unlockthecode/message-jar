<?php
declare(strict_types=1);

/**
 * Jar card partial.
 *
 * Expects:
 *   $jar  — array from jars_active() or jar_find()
 *   $href — optional URL to link the card to
 *   $wrap — 'a' (default) or 'div'
 */

if (!isset($jar) || !is_array($jar)) {
    throw new RuntimeException('jar-card.php: $jar must be an array.');
}

$href  = $href ?? null;
$wrap  = $wrap ?? 'a';
$color = css_hex_color($jar['theme_color'] ?? null);

$tag = $wrap === 'a' && $href ? 'a' : 'div';
$tagAttrs = $tag === 'a'
    ? 'class="jar-card" href="' . e($href) . '"'
    : 'class="jar-card"';
?>
<<?= $tag ?> <?= $tagAttrs ?>>
    <div class="jar-wrap" aria-hidden="true">
        <span class="jar-lid" data-jar-color="<?= $color ?>"></span>
        <div class="jar-shape" data-jar-color="<?= $color ?>">
            <span class="jar-emoji"><?= e($jar['emoji'] ?: '💌') ?></span>
        </div>
    </div>
    <span class="jar-name"><?= e($jar['name']) ?></span>
    <?php if (!empty($jar['description'])): ?>
        <span class="jar-desc"><?= e($jar['description']) ?></span>
    <?php endif; ?>
</<?= $tag ?>>