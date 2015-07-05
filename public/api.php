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

/* Include multi request
 * CURL version
 */
require_once realpath(__DIR__ . '/../extlib/mcurl/vendor/autoload.php');

// Create client object
use MCurl\Client;
$client = new Client();

// Include config
require_once realpath(__DIR__ . '/../config.default.php');
if (file_exists(realpath(__DIR__ . '/../config.php')))
  require_once realpath(__DIR__ . '/../config.php');

// Include all files in functions directory
if (is_dir(realpath(__DIR__ . '/../functions')))
  foreach (glob(realpath(__DIR__ . '/../functions') . '/*') as $file)
    if (is_file($file))
      require_once $file;

$active_user = NULL;

if (!isset($_GET['token']) || empty($_GET['token']))
  api_return_json(array('type' => 'fatal', 'message' => 'Forbidden'), 403);

foreach ($config['user'] as $username => $user)
  if ($user['token'] == $_GET['token']) {
    $active_user = $username;
    break;
  }

if ($active_user == NULL)
  api_return_json(array('type' => 'fatal', 'message' => 'Forbidden'), 403);

if (!isset($_GET['action']))
  api_return_json(array('type' => 'fatal', 'message' => 'Not found'), 404);

/* Iterate over array
 * Get domain list from hosts
 * and informations about virtual servers
 */
$domains = get_domain_data($config['domain_hosts'], $active_user);

switch ($_GET['action']) {
  case 'get_domains':
    api_return_json($domains);
    break;
  case 'domain_start':
    break;
  case 'domain_shutdown':
    break;
  case 'domain_reboot':
    break;
  default:
    api_return_json(array('type' => 'fatal', 'message' => 'Not found'), 404);
    break;
}

?>
