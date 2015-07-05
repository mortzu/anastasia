<?php

/*
2015, Moritz Kaspar Rudert (mortzu) <me@mortzu.de>.
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

function anastasia_api_call($url) {
  // Create a new cURL resource
  $ch = curl_init();

  // Set URL
  curl_setopt($ch, CURLOPT_URL, $url);

  // Return the content
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  // Grab URL and pass it to the browser
  $data = curl_exec($ch);

  // Close cURL resource, and free up system resources
  curl_close($ch);

  return $data;
}

function get_domain_data($domain_hosts, $active_user = NULL) {
  global $client, $config;

  // Variable for virtual servers
  $domains = array();

  /* Iterate over array
     get domain list from hosts
     and informations about virtual servers
   */
  $domain_hosts_new = array_map(function($value) { return $value . '/?action=get_domains'; }, $domain_hosts);

  // Start parallel client
  $results = $client->get($domain_hosts_new, [CURLOPT_RETURNTRANSFER => 1]);

  // Iterate over results
  foreach($results as $result) {
    if ($result->hasError())
      continue;

    // Grab URL and pass it to the browser
    $domain_host_data = @json_decode($result->body, true);

    // Get URL of API backend
    $domain_host = $result->info['url'];

    // If decoding fails continue anyway
    if (!is_array($domain_host_data))
      return array();

    // Iterate over domains of host
    foreach ($domain_host_data as $domain) {
      // Check if this is a domain
      if (!isset($domain['state']))
        continue;

      /* Check if user is staff
       * or has the right to administrate this domain
       */
      if ((!isset($config['rights'][$domain_host_data['hostname']][$domain['name']]) ||
           !is_array($config['rights'][$domain_host_data['hostname']][$domain['name']]) ||
           !in_array($active_user, $config['rights'][$domain_host_data['hostname']][$domain['name']])) &&
           !in_array($active_user, $config['staff_member']) &&
           ($active_user != NULL))
        continue;

      // Set variables
      $domains[$domain_host_data['hostname']][$domain['name']]['state'] = ($domain['state'] == 'running' ? true : false);
      $domains[$domain_host_data['hostname']][$domain['name']]['id'] = $domain['id'];
      $domains[$domain_host_data['hostname']][$domain['name']]['host_uri'] = $domain_host;
      $domains[$domain_host_data['hostname']][$domain['name']]['host_hostname'] = $domain['name'];
      $domains[$domain_host_data['hostname']][$domain['name']]['hypervisor'] = $domain_host_data['hypervisor'];
      $domains[$domain_host_data['hostname']][$domain['name']]['vcpu'] = $domain['vcpu'];
      $domains[$domain_host_data['hostname']][$domain['name']]['memory'] = $domain['memory'];
      $domains[$domain_host_data['hostname']][$domain['name']]['console_type'] = isset($domain['console_type']) ? $domain['console_type'] : NULL;
      $domains[$domain_host_data['hostname']][$domain['name']]['console_port'] = isset($domain['console_port']) ? $domain['console_port'] : NULL;
      $domains[$domain_host_data['hostname']][$domain['name']]['console_address'] = isset($domain['console_address']) ? $domain['console_address'] : NULL;

      if (isset($domain['ip']) && is_array($domain['ip']))
        $domains[$domain_host_data['hostname']][$domain['name']]['ip_assignment'] = $domain['ip'];
      elseif (isset($config['ip_assignment'][$domain['name']]) && is_array($config['ip_assignment'][$domain['name']]))
        $domains[$domain_host_data['hostname']][$domain['name']]['ip_assignment'] = $config['ip_assignment'][$domain['name']];
      else
        $domains[$domain_host_data['hostname']][$domain['name']]['ip_assignment'] = NULL;
    }

    // Unset variables
    unset($domain_host_data);
  }

  // Sort array by keys
  ksort($domains);

  return $domains;
}

function domain_action($domain_name, $action, $active_user = NULL) {
  global $config, $domains;

  if (!isset($domains) || !is_array($domains))
    return false;

  foreach ($domains as $host_hostname => $domain)
    foreach ($domain as $tmp_domain_name => $tmp_domain_info)
      if ($tmp_domain_name == $domain_name) {
        $domain_host_hostname = $host_hostname;
        $domain_host_uri = $tmp_domain_info['host_uri'];
      }

  // check if host of domain is set
  if (empty($domain_host_hostname))
    return false;

  /* check if user is staff
   * or has the right to administrate this domain
   */
  if ((!isset($config['rights'][$domain_host_hostname][$domain_name]) ||
       !is_array($config['rights'][$domain_host_hostname][$domain_name]) ||
       !in_array($active_user, $config['rights'][$domain_host_hostname][$domain_name])) &&
       !in_array($active_user, $config['staff_member']) &&
       ($active_user != NULL))
    return false;

  switch($action) {
    case 'domain_start':
    case 'domain_shutdown':
    case 'domain_reboot':
    case 'domain_reset':
      if ($domains[$domain_host_hostname][$domain_name]['hypervisor'] == 'OpenVZ')
        $data = anastasia_api_call($domain_host_uri . '/?action=' . $action . '&name=' . $domains[$domain_host_hostname][$domain_name]['id']);
      else
        $data = anastasia_api_call($domain_host_uri . '/?action=' . $action . '&name=' . $domain_name);

      if ($data['type'] == 'success')
        return true;
      else
        return false;
      break;

    case 'domain_console':
      // write token file for noVNC
      file_put_contents(realpath(__DIR__ . '/../novnc_token') . '/' . substr(md5($domain_name), 0, 10), substr(md5($domain_name), 0, 10) . ': ' . $domain_host_hostname . ':' . $domains[$domain_host_hostname][$domain_name]['console_port']);
      return true;
      break;
  }
}

?>
