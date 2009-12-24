<?php
/**
 * $Horde: whups/config/mime_drivers.php.dist,v 1.4 2005/03/14 13:27:10 jan Exp $
 *
 * Decide which output drivers you want to activate for the Whups application.
 * Settings in this file override settings in horde/config/mime_drivers.php.
 *
 * The available drivers are:
 * --------------------------
 * zip            ZIP attachments
 */
$mime_drivers_map['whups']['registered'] = array('zip');

/**
 * If you want to specifically override any MIME type to be handled by
 * a specific driver, then enter it here.  Normally, this is safe to
 * leave, but it's useful when multiple drivers handle the same MIME
 * type, and you want to specify exactly which one should handle it.
 */
$mime_drivers_map['whups']['overrides'] = array();

/**
 * Driver specific settings. See horde/config/mime_drivers.php for
 * the format.
 */

$mime_drivers['horde']['enscript']['handles'][] = 'text/html';

/**
 * Zip File Attachments settings
 */
$mime_drivers['whups']['zip']['inline'] = false;
$mime_drivers['whups']['zip']['handles'] = array(
    'x-extension/zip',
    'application/zip',
    'application/x-compressed',
    'application/x-zip-compressed');
$mime_drivers['whups']['zip']['icons'] = array(
    'default' => 'compressed.png');
