<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Green Forensics - Redirecting...</title>
    <script>
        (function() {
            var isMobile = window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            if (isMobile && window.innerWidth <= 1024) {
                window.location.replace("mobile.php");
            } else {
                window.location.replace("desktop.php");
            }
        })();
    </script>
</head>

<body>
    <noscript>
        <p>Please enable JavaScript to view this application, or go directly to <a href="desktop.php">Desktop
                Version</a> or <a href="mobile.php">Mobile Version</a>.</p>
    </noscript>
</body>

</html>