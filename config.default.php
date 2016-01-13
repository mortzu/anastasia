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

// title of page
$config['title'] = 'anastasia';

/*
 * set domain hosts in form of:
 * $vm_hosts[] = 'qemu+ssh://username@hostname/system?keyfile=path to SSH private key';
 */
$config['domain_hosts'] = array();

// VNC websocket port
$config['vnc_path'] = '';
$config['vnc_port'] = 62002;

/*
 * set staff member in form of:
 * $staff_member[] = 'username';
 */
$config['staff_member'] = array();

/*
 * set users:
 * $config['user']['username'] = array('password' => 'SHA512 hash', 'token' => 'Token');
 */
$config['user'] = array();

/*
 * set rights to access a domain:
 * $rights['hostname of domain host']['hostname of domain'] = array('username', 'maybe another username');
 */
$config['rights'] = array();

/*
 alias hostnames for VPS
 $config['alias']['hostname of domain host']['hostname of domain'] = array('username');
 */
$config['alias'] = array();

/*
 * IP assignment to a VM:
 * $config['ip_assignment']['hostname of domain'] = array('ip address');
 */
$config['ip_assignment'] = array();

?>
