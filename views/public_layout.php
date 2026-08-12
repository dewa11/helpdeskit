<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Helpdesk IT - Client</title>
    <!-- Bootstrap CSS -->
    <link rel="icon" type="image/png" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/public/images/RVL.png'; ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/public/css/style.css'; ?>">
</head>
<body>
    <div class="content-public">
        <div class="container py-4">
            <?php echo $content; ?>
        </div>
    </div>
    <script>var APP_BASE_PATH = '<?php echo APP_BASE_PATH; ?>';</script>
    <script src="<?php echo (APP_BASE_PATH !== '' ? APP_BASE_PATH : '') . '/public/js/script.js'; ?>"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>