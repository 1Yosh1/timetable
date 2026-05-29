<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($token ?? ''); ?>">
    <title><?php echo htmlspecialchars($title ?? 'Timetable System'); ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($baseUri ?? ''); ?>/css/styles.css">
</head>
<body class="<?php echo htmlspecialchars($bodyClass ?? ''); ?>">

    <?php if (isset($msg) && $msg !== ''): ?>
        <div class="alert-toast <?php echo isset($ok) && $ok ? 'alert-success' : 'alert-danger'; ?>" role="alert">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <?php echo $content; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="<?php echo htmlspecialchars($baseUri ?? ''); ?>/js/script.js"></script>
</body>
</html>
