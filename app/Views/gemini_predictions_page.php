<?php
// app/Views/gemini_predictions_page.php
$pageTitle = 'Scommetto.AI - Pronostici Futuri Gemini';
require __DIR__ . '/layout/top.php';
?>

<div id="htmx-container" hx-get="/gemini-predictions" hx-trigger="load">
    <div class="flex items-center justify-center py-20">
        <div class="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
    </div>
</div>

<?php
require __DIR__ . '/layout/bottom.php';
?>
