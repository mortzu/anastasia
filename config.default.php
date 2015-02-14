<?php

// title of page
$config['title'] = 'anastasia';

/*
 set domain hosts in form of:
 $vm_hosts[] = 'qemu+ssh://username@hostname/system?keyfile=path to SSH private key';
 */
$config['domain_hosts'] = array();

/*
 set staff member in form of:
 $staff_member[] = 'username';
 */
$config['staff_member'] = array();

/*
 set rights to access a domain:
 $rights['hostname of domain host']['hostname of domain'] = array('username', 'maybe another username');
 */
$config['rights'] = array();

?>
