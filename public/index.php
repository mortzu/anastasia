<?php

// include config
require_once realpath(__DIR__ . '/../config.default.php');
if (file_exists(realpath(__DIR__ . '/../config.php')))
  require_once realpath(__DIR__ . '/../config.php');

// check if libvirt-php is installed
if (!function_exists('libvirt_connect')) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
  error_log("libvirt-php is not installed!\n");
  exit(1);
}

// if remote user not set send 500 HTTP status
if (!isset($_SERVER['REMOTE_USER'])) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
  error_log("Make sure \$_SERVER['REMOTE_USER'] is set!\n");
  exit(1);
}

// check if the noVNC folder is writable
if (!is_writable(realpath(__DIR__ . '/../novnc_token'))) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
  error_log("Make sure " . realpath(__DIR__ . '/../novnc_token') . " is writable!\n");
  exit(1);
}

// include all files in functions directory
if (is_dir(realpath(__DIR__ . '/../functions')))
  foreach (glob(realpath(__DIR__ . '/../functions') . '/*') as $file)
    if (is_file($file))
      require_once $file;

function formatBytes($bytes, $precision = 2) {
  $units = array('B', 'KB', 'MB', 'GB', 'TB');

  $bytes = max($bytes, 0);
  $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
  $pow = min($pow, count($units) - 1);
  $bytes /= pow(1024, $pow);

  return round($bytes, $precision) . ' ' . $units[$pow];
}

// variables for status messages
$msg_error = '';
$msg_success = '';

// variable for virtual servers
$domains = array();

/* iterate over array
   get domain list from hosts
   and informations about virtual servers
*/
foreach ($config['domain_hosts'] as $domain_host) {
  // get domain list
  $domain_names = wrap_libvirt_domain_list($domain_host);

  // get informations about the host
  $domain_host_info = @wrap_libvirt_conn_informations($domain_host);

  // get hostname of the host
  $domain_host_hostname = wrap_libvirt_conn_hostname($domain_host);

  // iterate over domains of host
  foreach ($domain_names as $domain_name) {
    /* check if user is staff
       or has the right to administrate this domain
    */
    if ((!isset($config['rights'][$domain_host_hostname][$domain_name]) ||
         !is_array($config['rights'][$domain_host_hostname][$domain_name]) ||
         !in_array($_SERVER['REMOTE_USER'], $config['rights'][$domain_host_hostname][$domain_name])) &&
         !in_array($_SERVER['REMOTE_USER'], $config['staff_member']))
      continue;

    // get informations about domain
    $domain_info = wrap_libvirt_domain_info($domain_host, $domain_name);

    // get XML description
    $domain_xml_string = wrap_libvirt_domain_xml($domain_host, $domain_name);

    // and parse it
    $domain_xml = new SimpleXMLElement($domain_xml_string);

    // set variables
    $domains[$domain_name]['status'] = wrap_libvirt_domain_running($domain_host, $domain_name);
    $domains[$domain_name]['host_uri'] = $domain_host;
    $domains[$domain_name]['host_hostname'] = $domain_host_hostname;
    $domains[$domain_name]['hypervisor'] = $domain_host_info['hypervisor'];
    $domains[$domain_name]['vcpu_current'] = $domain_info['nrVirtCpu'];
    $domains[$domain_name]['vcpu_max'] = $domain_host_info['hypervisor_maxvcpus'];
    $domains[$domain_name]['memory_current'] = ($domain_host_info['hypervisor'] == 'OpenVZ') ? NULL : $domain_info['memory'];
    $domains[$domain_name]['memory_max'] = ($domain_host_info['hypervisor'] == 'OpenVZ') ? NULL : $domain_info['maxMem'];
    $domains[$domain_name]['vnc_port'] = isset($domain_xml->devices[0]->graphics['port']) ? $domain_xml->devices[0]->graphics['port'] : NULL;
    $domains[$domain_name]['vnc_listen'] = isset($domain_xml->devices[0]->graphics['listen']) ? $domain_xml->devices[0]->graphics['listen'] : NULL;

    // unset variables
    unset($domain_info);
    unset($domain_xml_string);
    unset($domain_xml);
  }

  // unset variables
  unset($domain_names);
  unset($domain_host_info);
  unset($domain_host_hostname);
}

// sort array by keys
ksort($domains);

// action requested
if (isset($_GET['domain_name']) && isset($_GET['action'])) {
  $domain_name = $_GET['domain_name'];

  // check if host of domain is set
  if (!isset($domains[$domain_name]['host_hostname']))
    $msg_error = 'Host of domain not set!';
  else {
    $domain_host_hostname = $domains[$domain_name]['host_hostname'];

    /* check if user is staff
       or has the right to administrate this domain
    */
    if ((!isset($config['rights'][$domain_host_hostname][$domain_name]) ||
         !is_array($config['rights'][$domain_host_hostname][$domain_name]) ||
         !in_array($_SERVER['REMOTE_USER'], $config['rights'][$domain_host_hostname][$domain_name])) &&
         !in_array($_SERVER['REMOTE_USER'], $config['staff_member']))
      $msg_error = 'You don\'t have permission to do this!';
    else {
      switch($_GET['action']) {
        case 'domain_start':
          if (wrap_libvirt_domain_start($domains[$domain_name]['host_uri'], $domain_name))
            $msg_success = 'Domain started successfully!';
          else
            $msg_error = 'Failed to start domain!';
          break;
        case 'domain_shutdown':
          if (wrap_libvirt_domain_shutdown($domains[$domain_name]['host_uri'], $domain_name))
            $msg_success = 'Domain shutdowned successfully!';
          else
            $msg_error = 'Failed to shutdown domain!';
          break;
        case 'domain_reboot':
          if (wrap_libvirt_domain_reboot($domains[$domain_name]['host_uri'], $domain_name))
            $msg_success = 'Domain rebooted successfully!';
          else
            $msg_error = 'Failed to reboot domain!';
          break;
        case 'domain_reset':
          if (wrap_libvirt_domain_reset($domains[$domain_name]['host_uri'], $domain_name))
            $msg_success = 'Domain resetted successfully!';
          else
            $msg_error = 'Failed to reset domain!';
          break;
        case 'domain_console':
          // write token file for noVNC
          file_put_contents(realpath(__DIR__ . '/../novnc_token') . '/' . substr(md5($domain_name), 0, 10), substr(md5($domain_name), 0, 10) . ': ' . $domain_host_hostname . ':' . $domains[$domain_name]['vnc_port']);

          // return content of noVNC
          echo file_get_contents(realpath(__DIR__ . '/../templates/parts/vnc.tmpl'));

          // terminate execution
          die();
          break;
      }
    }
  }
}

$site_content = '<h2>Domains</h2>';

// display status messages
if (isset($msg_success) && $msg_success != '')
  $site_content .= '<div class="alert alert-success" role="alert">' . $msg_success . "</div>\n";
if (isset($msg_error) && $msg_error != '')
  $site_content .= '<div class="alert alert-danger" role="alert">' . $msg_error . "</div>\n";

$site_content .= "<table class=\"table table-striped\">\n";

$site_content .= "<tr>\n";
$site_content .= "  <th>Hostname</th>\n";
$site_content .= "  <th>Status</th>\n";
$site_content .= "  <th>VCPUs</th>\n";
$site_content .= "  <th>Memory</th>\n";
$site_content .= "  <th>Hypervisor</th>\n";
$site_content .= "  <th>Tasks</th>\n";
$site_content .= "</tr>\n";

// iterate over domains array
foreach ($domains as $domain_name => $domain_info) {
  $site_content .= "<tr>\n";
  $site_content .= '<td>' . $domain_name . "</td>\n";
  $site_content .= '<td><span id="status" class="label label-' . (($domain_info['status'] == true) ? 'success">running' : 'danger">stopped') . "</span></td>\n";
  $site_content .= '<td>' . $domain_info['vcpu_current'] . ' / ' . $domain_info['vcpu_max'] . "</td>\n";
  $site_content .= '<td>' . (($domain_info['memory_current'] == NULL) ? ' - ' : formatBytes($domain_info['memory_current'] * 1024) . (($domain_info['memory_current'] != $domain_info['memory_max']) ? ' / ' . formatBytes($domain_info['memory_max'] * 1024) : '')) . "</td>\n";
  $site_content .= '<td>' . $domain_info['hypervisor'] . "</td>\n";

  $site_content .= "<td>\n";
  $site_content .= "<a href=\"" . $_SERVER['REQUEST_URI'] . "?action=domain_start&domain_name=" . $domain_name . "\" class=\"btn btn-default" . (($domain_info['status'] == true) ? ' disabled' : '') . "\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Boot\"> <span class=\"glyphicon glyphicon-play\"></span> </a>\n";
  $site_content .= "<a href=\"" . $_SERVER['REQUEST_URI'] . "?action=domain_shutdown&domain_name=" . $domain_name . "\" class=\"btn btn-default" . (($domain_info['status'] != true) ? ' disabled' : '') . "\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Shutdown\"> <span class=\"glyphicon glyphicon-off\"></span> </a>\n";
  $site_content .= "<a href=\"" . $_SERVER['REQUEST_URI'] . "?action=domain_reboot&domain_name=" . $domain_name . "\" class=\"btn btn-default\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Reboot\"> <span class=\"glyphicon glyphicon-refresh\"></span> </a>\n";
  $site_content .= "<a href=\"" . $_SERVER['REQUEST_URI'] . "?action=domain_reset&domain_name=" . $domain_name . "\" class=\"btn btn-default\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Hard Reset\"> <span class=\"glyphicon glyphicon-fire\"></span> </a>\n";

  /* display link to console only if domain has VNC port set
     and listen address is not localhost
   */
  if ($domain_info['vnc_port'] != NULL && (isset($domain_info['vnc_listen']) && $domain_info['vnc_listen'] != '127.0.0.1'))
    $site_content .= "<a onclick=\"window.open('" . $_SERVER['REQUEST_URI'] . "?action=domain_console&domain_name=" . $domain_name . "&token=" . substr(md5($domain_name), 0, 10) . "')\" class=\"btn btn-default\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Console\"> <span class=\"glyphicon glyphicon-blackboard\"></span> </a>\n";

  $site_content .= "</td>\n";
  $site_content .= "</tr>\n";
}

$site_content .= "</table>\n";

// get content of main template
$content = str_replace('{{{TITLE}}}', $config['title'], file_get_contents(realpath(__DIR__ . '/../templates/site/main.tmpl')));

// display parsed template
echo str_replace('{{{SITE}}}', $site_content, $content);

?>
