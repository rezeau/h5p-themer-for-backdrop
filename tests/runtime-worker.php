<?php
/** Private CLI filesystem worker; no Backdrop bootstrap. GPL-2.0-or-later. */
if (PHP_SAPI !== 'cli') { exit('CLI only.'); }
$root = isset($argv[1]) ? realpath($argv[1]) : FALSE;
$css = isset($argv[2]) ? base64_decode($argv[2], TRUE) : FALSE;
$temporary = realpath(sys_get_temp_dir());
if (!$root || !is_string($css) || $css === '' || basename($root) !== 'concurrent' ||
    preg_match('/\Ah5p-themer-runtime-[0-9a-f]{24}\z/', basename(dirname($root))) !== 1 ||
    strcasecmp(str_replace('\\', '/', realpath(dirname(dirname($root)))), str_replace('\\', '/', $temporary)) !== 0) {
  fwrite(STDERR, "Invalid private fixture.\n"); exit(2);
}
function t($message) { return $message; }
class H5PThemerWorkerPublicWrapper {
  public function getDirectoryPath() { return 'files'; }
  public function realpath() { return $GLOBALS['root']; }
}
function file_stream_wrapper_get_instance_by_uri($uri) {
  return $uri === 'public://' ? new H5PThemerWorkerPublicWrapper() : FALSE;
}
require dirname(__DIR__) . '/h5p_themer.runtime.inc';
for ($attempt = 0; $attempt < 1000 && !is_file($root . '/start'); $attempt++) { usleep(1000); }
$result = array('data' => NULL, 'errors' => array('Publication did not complete.'));
for ($attempt = 0; $attempt < 20; $attempt++) {
  $result = h5p_themer_runtime_asset($css);
  if (!$result['errors']) { break; }
  usleep(10000);
}
echo json_encode($result);
exit($result['errors'] ? 1 : 0);
