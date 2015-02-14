<?php

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
