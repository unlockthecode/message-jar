<?php
declare(strict_types=1);
/**
 * Expects:
 *   $jar  — array from jars_active() or jar_find()
 *   $href — optional URL to link the card to
 *   $wrap — 'a' (default) or 'div'
 */
$href  = $href ?? null;
$wrap  = $wrap ?? 'a';
$color = $jar['theme_color'] ?: '#ff8fab';

$tag = $wrap === 'a' && $href ? 'a' : 'div';
$tagAttrs = 'class="jar-card" style="--jar-color: ' . e($color) . ';"';
if ($tag === 'a') {
    $tagAttrs .= ' href="' . e($href) . '"';
}
?>
<<?= $tag ?> <?= $tagAttrs /* already escaped */ ?>>
    <div class="jar-shape" aria-hidden="true">
        <span class="jar-lid"></span>
        <span class="jar-emoji"><?= e($jar['emoji'] ?: '💌') ?></span>
    </div>
    <span class="jar-name"><?= e($jar['name']) ?></span>
    <?php if (!empty($jar['description'])): ?>
        <span class="jar-desc"><?= e($jar['description']) ?></span>
    <?php endif; ?>
</<?= $tag ?>>