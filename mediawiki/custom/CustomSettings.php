<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

$logoUrl = getenv( 'MEDIAWIKI_LOGO_URL' ) ?: '';
if ( $logoUrl === 'none' ) {
	$logoUrl = '';
}

$wgLogos = [
	'icon' => $logoUrl,
	'1x' => $logoUrl,
];
