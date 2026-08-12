<?php
// Simple ffmpeg/ffprobe availability test
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>FFmpeg Test</title>
  <style>body{background:#0b0f10;color:#0ff;font-family:monospace;padding:20px}pre{background:#001; padding:10px; display:block; white-space:pre-wrap;}</style>
</head>
<body>
<h1>FFmpeg / FFprobe Test</h1>
<p><strong>which ffmpeg:</strong> <?php echo trim(shell_exec('which ffmpeg')); ?></p>
<p><strong>ffmpeg -version:</strong><pre><?php echo htmlspecialchars(shell_exec('ffmpeg -version')); ?></pre></p>
<p><strong>which ffprobe:</strong> <?php echo trim(shell_exec('which ffprobe')); ?></p>
<p><strong>ffprobe -version:</strong><pre><?php echo htmlspecialchars(shell_exec('ffprobe -version')); ?></pre></p>
<p><strong>disabled_functions:</strong> <?php echo ini_get('disable_functions'); ?></p>
<p>To verify Apache user can run binaries, run in terminal: <code>sudo -u www-data which ffmpeg</code> (replace <em>www-data</em> with your Apache user if different).</p>
</body>
</html>
