<script src="<?= url('assets/js/app.js') ?>?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<?php foreach ($pageScripts ?? [] as $script): ?>
<script src="<?= url('assets/js/' . $script) ?>?v=<?= filemtime(__DIR__ . '/../assets/js/' . $script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
