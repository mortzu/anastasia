<?php

/*
2014-2016, Moritz Kaspar Rudert (mortzu) <me@mortzu.de>.
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

/* Include multi request
 * CURL version
 */
require_once realpath(__DIR__ . '/../extlib/mcurl/vendor/autoload.php');

// Create client object
use MCurl\Client;
$client = new Client();

// Include config
require_once realpath(__DIR__ . '/../config.default.php');

/* If a custom config exists
 * include this too
 */
if (file_exists(realpath(__DIR__ . '/../config.php')))
  require_once realpath(__DIR__ . '/../config.php');

// Check if the noVNC folder exists
if (!is_dir(realpath(__DIR__ . '/..') . '/novnc_token'))
  webui_error("Make sure the directory " . realpath(__DIR__ . '/..') . '/novnc_token' . " exists!\n");

// Check if the noVNC folder is writable
if (!is_writable(realpath(__DIR__ . '/../novnc_token')))
  webui_error("Make sure " . realpath(__DIR__ . '/../novnc_token') . " is writable!\n");

// Include all files in functions directory
if (is_dir(realpath(__DIR__ . '/../functions')))
  foreach (glob(realpath(__DIR__ . '/../functions') . '/*') as $file)
    if (is_file($file))
      require_once $file;

// Variable of current user
$active_user = NULL;

// Do login
do {
  if (isset($_SERVER['PHP_AUTH_USER']) &&
      isset($_SERVER['PHP_AUTH_PW']) &&
      isset($config['user'][$_SERVER['PHP_AUTH_USER']]['password']) &&
      $config['user'][$_SERVER['PHP_AUTH_USER']]['password'] == hash('sha512', $_SERVER['PHP_AUTH_PW']))
    $active_user = $_SERVER['PHP_AUTH_USER'];
  else {
    header('WWW-Authenticate: Basic realm="Anastasia"');
    header('HTTP/1.0 401 Unauthorized');
    exit;
  }
} while ($active_user == NULL);

// Variables for status messages
$msg_error = '';
$msg_success = '';

/* Iterate over array
   get domain list from hosts
   and informations about virtual servers
*/
$domains = get_domain_data($config['domain_hosts'], $active_user);

// Action requested
if (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) {
  if (isset($_POST['action']) && strstr($_POST['action'], '|'))
    list($action, $domain_name) = explode('|', $_POST['action']);
  elseif (isset($_GET['domain_name']) && !empty($_GET['domain_name'])) {
    $action = $_GET['action'];
    $domain_name = $_GET['domain_name'];
  }

  if (isset($domain_name) && !empty($domain_name)) {
    if (domain_action($domain_name, $action, $active_user))
      $msg_success = 'Successful!';
    else
      $msg_error = 'Failed!';

    // Return content of noVNC
    if ($action == 'domain_console') {
      echo str_replace('{{ site.title }}', $config['title'], file_get_contents(realpath(__DIR__ . '/../templates/parts/vnc.tmpl')));
      // Terminate execution
      die();
    }
  }

  /* Get domain informations again
     after action has called
  */
  $domains = get_domain_data($config['domain_hosts'], $active_user);
}

$site_content = "<h2>Domains</h2>\n";

// Display status messages
if (isset($msg_success) && !empty($msg_success))
  $site_content .= "<div class=\"alert alert-success\" role=\"alert\">" . $msg_success . "</div>\n";
if (isset($msg_error) && !empty($msg_error))
  $site_content .= "<div class=\"alert alert-danger\" role=\"alert\">" . $msg_error . "</div>\n";

// Display page content
$site_content .= "<form method=\"post\" action=\"" . explode('?', $_SERVER['REQUEST_URI'], 2)[0] . "\">\n";
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

// Iterate over domains array
foreach ($domains as $host_hostname => $host_domains) {
  foreach ($host_domains as $domain_name => $domain_info) {
    $site_content .= "<tr>\n";

    if (isset($config['alias'][$domain_info['host_hostname']][$domain_name]) &&
      $config['alias'][$domain_info['host_hostname']][$domain_name] != '')
      $site_content .= "<td>" . $config['alias'][$domain_info['host_hostname']][$domain_name] . "</td>\n";
    else
      $site_content .= "<td>" . $domain_name . "</td>\n";

    $site_content .= "<td><span id=\"status\" class=\"label label-" . (($domain_info['state'] == true) ? 'success">running' : 'danger">stopped') . "</span></td>\n";
    $site_content .= "<td>" . $domain_info['vcpu'] . "</td>\n";
    $site_content .= "<td>" . (($domain_info['memory'] == NULL) ? ' - ' : formatBytes($domain_info['memory'] * 1024)) . "</td>\n";
    $site_content .= "<td>" . $domain_info['hypervisor'] . "</td>\n";

    $site_content .= '<td>';
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
    $site_content .= "<button type=\"submit\" name=\"action\" value=\"domain_start|" . $domain_name . "\" class=\"btn btn-default" . (($domain_info['state'] == true) ? ' disabled' : '') . "\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Boot\"> <span class=\"glyphicon glyphicon-play\"></span> </button>\n";
    $site_content .= "<button type=\"submit\" name=\"action\" value=\"domain_shutdown|" . $domain_name . "\" class=\"btn btn-default" . (($domain_info['state'] != true) ? ' disabled' : '') . "\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Shutdown\"> <span class=\"glyphicon glyphicon-off\"></span> </button>\n";
    $site_content .= "<button type=\"submit\" name=\"action\" value=\"domain_reboot|" . $domain_name . "\" class=\"btn btn-default\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Reboot\"> <span class=\"glyphicon glyphicon-refresh\"></span> </button>\n";
    $site_content .= "<button type=\"submit\" name=\"action\" value=\"domain_reset|" . $domain_name . "\" class=\"btn btn-default" . (($domain_info['hypervisor'] == 'OpenVZ') ? ' disabled' : '') . "\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Hard Reset\"> <span class=\"glyphicon glyphicon-fire\"></span> </button>\n";

    /* Display link to console only if domain has VNC port set
       and listen address is not localhost
     */
    if (isset($domain_info['console_type']) && $domain_info['console_type'] == 'VNC' && $domain_info['console_port'] != NULL && (isset($domain_info['console_address']) && $domain_info['console_address'] != '127.0.0.1'))
      $site_content .= "<a onclick=\"window.open('" . explode('?', $_SERVER['REQUEST_URI'], 2)[0] . "?action=domain_console&path=" . $config['vnc_path'] . "&port=" . $config['vnc_port'] . "&domain_name=" . $domain_name . "&token=" . substr(md5($domain_name), 0, 10) . "')\" class=\"btn btn-default\" data-container=\"body\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"Console\"> <span class=\"glyphicon glyphicon-blackboard\"></span> </a>\n";

    $site_content .= "</td>\n";
    $site_content .= "</tr>\n";
  }
}

$site_content .= "</table>\n";
$site_content .= "</form>\n";

// Get content of main template
$content = str_replace('{{ site.title }}', $config['title'], file_get_contents(realpath(__DIR__ . '/../templates/site/main.tmpl')));

// Display parsed template
echo str_replace('{{{SITE}}}', $site_content, $content);

?>
