<?php
/**
 * @brief		All Blogs
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Blog
 * @since		03 Mar 2014
 */

namespace IPS\blog\modules\front\blogs;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * browse
 */
class _browse extends \IPS\Dispatcher\Controller
{
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		/* Featured stuff */
		$featured	= iterator_to_array( \IPS\blog\Entry::featured( 5, '_rand' ) );

		/* Blogs table */
		$table = new \IPS\blog\Blog\Table( \IPS\Http\Url::internal( 'app=blog&module=blogs&controller=browse', 'front', 'blogs' ) );
		$table->title = 'our_community_blogs';
		$table->classes = array( 'cBlogList', 'ipsAreaBackground', 'ipsDataList_large' );

		/* Filters */
		$table->filters = array(
			'my_blogs'				=> array( '(' . \IPS\Db::i()->findInSet( 'blog_groupblog_ids', \IPS\Member::loggedIn()->groups ) . ' OR ' . 'blog_member_id=? )', \IPS\Member::loggedIn()->member_id ),
			'blogs_with_content'	=> array( 'blog_count_entries>0' )
		);
		
		$blogs = \IPS\blog\Blog::loadByOwner( \IPS\Member::loggedIn(), array( array( 'blog_disabled=?', 0 ) ) );

		\IPS\Session::i()->setLocation( \IPS\Http\Url::internal( 'app=blog', 'front', 'blogs' ), array(), 'loc_blog_viewing_index' );
				
		/* Display */
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'front_browse.js', 'blog', 'front' ) );
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('blogs');
		\IPS\Output::i()->output	= \IPS\Theme::i()->getTemplate( 'browse' )->index( $table, $featured, $blogs );
	}
}