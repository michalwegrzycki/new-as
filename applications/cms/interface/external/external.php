<?php
/**
 * @brief		Pages External Block Gateway
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Content
 * @since		30 Jun 2015
 *
 */

require_once str_replace( 'applications/cms/interface/external/external.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
\IPS\Dispatcher\External::i();

$id = \IPS\Request::i()->blockid;
$k = \IPS\Request::i()->widgetid;
$block = \IPS\cms\Blocks\Block::display( $id );

\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_external.js', 'cms', 'front' ) );
\IPS\Output::i()->globalControllers[] = 'cms.front.external.communication';
\IPS\Output::i()->sendOutput( \IPS\Theme::i()->getTemplate( 'global', 'core' )->blankTemplate( $block ), 200, 'text/html' );