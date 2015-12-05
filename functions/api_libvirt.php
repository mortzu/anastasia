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

function wrap_libvirt_connection($vm_host) {
  return libvirt_connect($vm_host, false);
}

function wrap_libvirt_domain_start($vm_host, $vm_name) {
  if (NULL === $vm_resource = wrap_libvirt_get_resource($vm_host, $vm_name))
    return false;

  return libvirt_domain_create($vm_resource);
}

function wrap_libvirt_domain_reboot($vm_host, $vm_name) {
  if (NULL === $vm_resource = wrap_libvirt_get_resource($vm_host, $vm_name))
    return false;

  return libvirt_domain_reboot($vm_resource);
}

function wrap_libvirt_domain_shutdown($vm_host, $vm_name) {
  if (NULL === $vm_resource = wrap_libvirt_get_resource($vm_host, $vm_name))
    return false;

  return libvirt_domain_shutdown($vm_resource);
}

function wrap_libvirt_domain_reset($vm_host, $vm_name) {
  if (NULL === $vm_resource = wrap_libvirt_get_resource($vm_host, $vm_name))
    return false;

  libvirt_domain_destroy($vm_resource); // Test result?

  sleep(5);

  return libvirt_domain_create($vm_resource);
}

function wrap_libvirt_domain_running($vm_host, $vm_name) {
  if (NULL === $vm_resource = wrap_libvirt_get_resource($vm_host, $vm_name))
    return NULL;

  $active = libvirt_domain_is_active($vm_resource);

  if ($active === 1)
    return true;
  elseif ($active === 0)
    return false;

  return NULL;
}

function wrap_libvirt_domain_info($vm_host, $vm_name) {
  if (NULL === $vm_resource = wrap_libvirt_get_resource($vm_host, $vm_name))
    return NULL;

  return libvirt_domain_get_info($vm_resource);
}

function wrap_libvirt_domain_xml($vm_host, $vm_name) {
  if (NULL === $vm_resource = wrap_libvirt_get_resource($vm_host, $vm_name))
    return false;

  return libvirt_domain_get_xml_desc($vm_resource, NULL);
}

function wrap_libvirt_domain_list($vm_host) {
  global $libvirt_conn;

  if (NULL === $libvirt_conn = wrap_libvirt_connection($vm_host))
    return NULL;

  return libvirt_list_domains($libvirt_conn);
}

function wrap_libvirt_conn_hostname($vm_host) {
  global $libvirt_conn;

  if (NULL === $libvirt_conn = wrap_libvirt_connection($vm_host))
    return NULL;

  return libvirt_connect_get_hostname($libvirt_conn);
}

function wrap_libvirt_conn_informations($vm_host) {
  global $libvirt_conn;

  if (NULL === $libvirt_conn = wrap_libvirt_connection($vm_host))
    return NULL;

  return libvirt_connect_get_information($libvirt_conn);
}

function wrap_libvirt_get_resource($vm_host, $vm_name) {
  global $libvirt_conn;

  if (NULL === $libvirt_conn = wrap_libvirt_connection($vm_host))
    return NULL;

  return libvirt_domain_lookup_by_name($libvirt_conn, $vm_name);
}

?>
