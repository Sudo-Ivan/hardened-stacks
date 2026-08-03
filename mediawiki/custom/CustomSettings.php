<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

$logoUrl = getenv( 'MEDIAWIKI_LOGO_URL' ) ?: 'https://hardened-stacks.org/static/img/logo.svg';

$wgLogos = [
	'icon' => $logoUrl,
	'1x' => $logoUrl,
];
