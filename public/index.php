<?php

/*
2014, Moritz Kaspar Rudert (mortzu) <mr@planetcyborg.de>.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are
permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this list of
  conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice, this list
  of conditions and the following disclaimer in the documentation and/or other materials
  provided with the distribution.

* The names of its contributors may not be used to endorse or promote products derived
  from this software without specific prior written permission.

* Feel free to send Club Mate to support the work.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS
AND CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
*/

// Include config
require_once realpath(__DIR__ . '/../config.default.php');
if (file_exists(realpath(__DIR__ . '/../config.php')))
  require_once realpath(__DIR__ . '/../config.php');

if (!isset($_SERVER['REMOTE_USER']) && isset($_SERVER['PHP_AUTH_USER']))
  $_SERVER['REMOTE_USER'] = $_SERVER['PHP_AUTH_USER'];

// if remote user not set send 500 HTTP status
if (!isset($_SERVER['REMOTE_USER'])) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
  error_log("Make sure \$_SERVER['REMOTE_USER'] is set!\n");
  exit(1);
}

// check if the noVNC folder exists
if (!is_dir(realpath(__DIR__ . '/..') . '/novnc_token')) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
  error_log("Make sure the directory " . realpath(__DIR__ . '/..') . '/novnc_token' . " exists!\n");
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

function ip_is_private($ip) {
  $ip = explode('.', $ip);
  $a = (int) $ip[0];
  $b = (int) $ip[1];
  $c = (int) $ip[2];
  $d = (int) $ip[3];

  if ($a === 10)
    return true;

  if ($a === 192 && $b === 168)
    return true;

  if ($a !== 172)
    return false;

  if ($b >= 16 && $b <= 31)
    return true;

  return false;
}

function api_call($url) {
  // create a new cURL resource
  $ch = curl_init();

  // set URL
  curl_setopt($ch, CURLOPT_URL, $url);

  // return the content
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  // grab URL and pass it to the browser
  $data = curl_exec($ch);

  // close cURL resource, and free up system resources
  curl_close($ch);

  return $data;
}

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
  // grab URL and pass it to the browser
  $domain_host_data = @json_decode(api_call($domain_host . '/?action=get_domains'), true);

  // if decoding failed continue
  if (!is_array($domain_host_data))
    continue;

  // iterate over domains of host
  foreach ($domain_host_data as $domain) {
    // check if this is a domain
    if (!isset($domain['state']))
      continue;

    /* check if user is staff
       or has the right to administrate this domain
    */
    if ((!isset($config['rights'][$domain_host_data['hostname']][$domain['name']]) ||
         !is_array($config['rights'][$domain_host_data['hostname']][$domain['name']]) ||
         !in_array($_SERVER['REMOTE_USER'], $config['rights'][$domain_host_data['hostname']][$domain['name']])) &&
         !in_array($_SERVER['REMOTE_USER'], $config['staff_member']))
      continue;

    // set variables
    $domains[$domain_host_data['hostname']][$domain['name']]['state'] = ($domain['state'] == 'running' ? true : false);
    $domains[$domain_host_data['hostname']][$domain['name']]['id'] = $domain['id'];
    $domains[$domain_host_data['hostname']][$domain['name']]['host_uri'] = $domain_host;
    $domains[$domain_host_data['hostname']][$domain['name']]['host_hostname'] = $domain['name'];
    $domains[$domain_host_data['hostname']][$domain['name']]['hypervisor'] = $domain_host_data['hypervisor'];
    $domains[$domain_host_data['hostname']][$domain['name']]['vcpu'] = $domain['vcpu'];
    $domains[$domain_host_data['hostname']][$domain['name']]['memory'] = $domain['memory'];
    $domains[$domain_host_data['hostname']][$domain['name']]['vnc_port'] = isset($domain['vnc_port']) ? $domain['vnc_port'] : NULL;
    $domains[$domain_host_data['hostname']][$domain['name']]['vnc_address'] = isset($domain['vnc_address']) ? $domain['vnc_address'] : NULL;

    if (isset($domain['ip']) && is_array($domain['ip']))
      $domains[$domain_host_data['hostname']][$domain['name']]['ip_assignment'] = $domain['ip'];
    elseif (isset($config['ip_assignment'][$domain['name']]) && is_array($config['ip_assignment'][$domain['name']]))
      $domains[$domain_host_data['hostname']][$domain['name']]['ip_assignment'] = $config['ip_assignment'][$domain['name']];
    else
      $domains[$domain_host_data['hostname']][$domain['name']]['ip_assignment'] = NULL;
  }

  // unset variables
  unset($domain_host_data);
}

// sort array by keys
ksort($domains);

// action requested
if (isset($_GET['domain_name']) && isset($_GET['action'])) {
  $domain_name = $_GET['domain_name'];

  foreach ($domains as $host_hostname => $domain)
    foreach ($domain as $tmp_domain_name => $tmp_domain_info)
      if ($tmp_domain_name == $domain_name) {
        $domain_host_hostname = $host_hostname;
        $domain_host_uri = $tmp_domain_info['host_uri'];
      }

  // check if host of domain is set
  if (!empty($domain_host_hostname)) {
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
        case 'domain_shutdown':
        case 'domain_reboot':
        case 'domain_reset':
          if ($domains[$domain_host_hostname][$domain_name]['hypervisor'] == 'OpenVZ')
            $data = api_call($domain_host_uri . '/?action=' . $_GET['action'] . '&name=' . $domains[$domain_host_hostname][$domain_name]['id']);
          else
            $data = api_call($domain_host_uri . '/?action=' . $_GET['action'] . '&name=' . $domain_name);

          if ($data['type'] == 'success')
            $msg_success = 'Successful!';
          else
            $msg_error = 'Failed!';
          break;
        case 'domain_console':
          // write token file for noVNC
          file_put_contents(realpath(__DIR__ . '/../novnc_token') . '/' . substr(md5($domain_name), 0, 10), substr(md5($domain_name), 0, 10) . ': ' . $domain_host_hostname . ':' . $domains[$domain_host_hostname][$domain_name]['vnc_port']);

          // return content of noVNC
          echo str_replace('{{{TITLE}}}', $config['title'], file_get_contents(realpath(__DIR__ . '/../templates/parts/vnc.tmpl')));

          // terminate execution
          die();
          break;
      }
    }
  } else
    $msg_error = 'Host of domain not set!';
}

$site_content = '<h2>Domains</h2>';

// display status messages
if (isset($msg_success) && !empty($msg_success))
  $site_content .= '<div class="alert alert-success" role="alert">' . $msg_success . "</div>\n";
if (isset($msg_error) && !empty($msg_error))
  $site_content .= '<div class="alert alert-danger" role="alert">' . $msg_error . "</div>\n";

$site_content .= "<table class=\"table table-striped\">\n";

$site_content .= "<tr>\n";
$site_content .= "  <th>Hostname</th>\n";
$site_content .= "  <th>Status</th>\n";
$site_content .= "  <th>VCPUs</th>\n";
$site_content .= "  <th>Memory</th>\n";
$site_content .= "  <th>Hypervisor</th>\n";
$site_content .= "  <th>IPs</th>\n";
$site_content .= "  <th>Tasks</th>\n";
$site_content .= "</tr>\n";

// iterate over domains array
foreach ($domains as $host_hostname => $host_domains) {
  foreach ($host_domains as $domain_name => $domain_info) {
    $site_content .= "<tr>\n";

    if (isset($config['alias'][$domain_info['host_hostname']][$domain_name]) &&
      $config['alias'][$domain_info['host_hostname']][$domain_name] != '')
      $site_content .= '<td>' . $config['alias'][$domain_info['host_hostname']][$domain_name] . "</td>\n";
    else
      $site_content .= '<td>' . $domain_name . "</td>\n";

    $site_content .= '<td><span id="status" class="label label-' . (($domain_info['state'] == true) ? 'success">running' : 'danger">stopped') . "</span></td>\n";
    $site_content .= '<td>' . $domain_info['vcpu'] . "</td>\n";
    $site_content .= '<td>' . (($domain_info['memory'] == NULL) ? ' - ' : formatBytes($domain_info['memory'] * 1024)) . "</td>\n";
    $site_content .= '<td>' . $domain_info['hypervisor'] . "</td>\n";

    $site_content .= "<td>\n";
    if (is_array($domain_info['ip_assignment']))
      foreach ($domain_info['ip_assignment'] as $address)
        if (!ip_is_private($address))
          $site_content .= '<span data-toggle="tooltip" data-placement="top" title="rDNS: ' . gethostbyaddr($address) . '">' . $address . '</span><br />';
        else
          $site_content .= $address . '<br />';
    else
      $site_content .= ' - ';
    $site_content .= "</td>\n";

    $site_content .= "<td>\n";
    $site_content .= "<a href=\"" . $_SERVER['REQUEST_URI'] . "?action=domain_start&domain_name=" . $domain_name . "\" class=\"btn btn-default" . (($domain_info['state'] == true) ? ' disabled' : '') . "\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Boot\"> <span class=\"glyphicon glyphicon-play\"></span> </a>\n";
    $site_content .= "<a href=\"" . $_SERVER['REQUEST_URI'] . "?action=domain_shutdown&domain_name=" . $domain_name . "\" class=\"btn btn-default" . (($domain_info['state'] != true) ? ' disabled' : '') . "\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Shutdown\"> <span class=\"glyphicon glyphicon-off\"></span> </a>\n";
    $site_content .= "<a href=\"" . $_SERVER['REQUEST_URI'] . "?action=domain_reboot&domain_name=" . $domain_name . "\" class=\"btn btn-default\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Reboot\"> <span class=\"glyphicon glyphicon-refresh\"></span> </a>\n";
    $site_content .= "<a href=\"" . $_SERVER['REQUEST_URI'] . "?action=domain_reset&domain_name=" . $domain_name . "\" class=\"btn btn-default" . (($domain_info['hypervisor'] == 'OpenVZ') ? ' disabled' : '') . "\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Hard Reset\"> <span class=\"glyphicon glyphicon-fire\"></span> </a>\n";

    /* display link to console only if domain has VNC port set
       and listen address is not localhost
     */
    if ($domain_info['vnc_port'] != NULL && (isset($domain_info['vnc_address']) && $domain_info['vnc_address'] != '127.0.0.1'))
      $site_content .= "<a onclick=\"window.open('" . $_SERVER['REQUEST_URI'] . "?action=domain_console&domain_name=" . $domain_name . "&token=" . substr(md5($domain_name), 0, 10) . "')\" class=\"btn btn-default\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Console\"> <span class=\"glyphicon glyphicon-blackboard\"></span> </a>\n";

    $site_content .= "</td>\n";
    $site_content .= "</tr>\n";
  }
}

$site_content .= "</table>\n";

// get content of main template
$content = str_replace('{{{TITLE}}}', $config['title'], file_get_contents(realpath(__DIR__ . '/../templates/site/main.tmpl')));

// display parsed template
echo str_replace('{{{SITE}}}', $site_content, $content);

?>
